<?php

namespace App\Services\Reports;

use App\Enums\ArtifactType;
use App\Enums\AssetKind;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Report;
use App\Services\Analytics\KpiSnapshotBuilder;
use App\Services\Analytics\MetricsPuller;
use App\Services\Artifacts\Generators\ClientContext;
use App\Services\Assets\AssetLibrary;
use App\Services\Llm\Exceptions\LlmGatewayException;
use App\Services\Llm\LlmGateway;
use App\Services\Llm\RoleRequest;
use App\Services\Render\HtmlRenderer;
use App\Services\Telegram\AdminNotifier;
use App\Services\Telegram\TelegramClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\View;
use Throwable;

/**
 * F8.1 weekly report: pull data → KPI Snapshot (T0) → Report Writer comment (T2, optional) → PDF → Telegram.
 * One report per client per ISO week (unique), so the hourly scheduler can catch up safely.
 */
class WeeklyReports
{
    public function __construct(
        private readonly MetricsPuller $puller,
        private readonly KpiSnapshotBuilder $snapshots,
        private readonly LlmGateway $gateway,
        private readonly ClientContext $context,
        private readonly HtmlRenderer $renderer,
        private readonly AssetLibrary $assets,
        private readonly TelegramClient $telegram,
        private readonly AdminNotifier $admins,
    ) {}

    /** Monday of the last complete week in the client's timezone. */
    public function lastWeekStart(Client $client, ?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->setTimezone($client->timezone)->startOfWeek(CarbonImmutable::MONDAY)->subWeek()->startOfDay();
    }

    public function exists(Client $client, CarbonImmutable $weekStart): bool
    {
        return Report::query()->where(['client_id' => $client->id, 'period_type' => 'week', 'period_start' => $weekStart->toDateString()])
            ->whereIn('status', ['ready', 'sent'])->exists();
    }

    public function generate(Client $client, CarbonImmutable $weekStart, bool $send = true): Report
    {
        $weekEnd = $weekStart->addDays(6);
        $this->puller->pull($client);
        $snapshot = $this->snapshots->build($client, $weekStart, $weekEnd, $weekStart->format('o-\WW'));
        $kpi = $snapshot->payload;
        unset($kpi['meta']);

        $narrative = null;
        $llmCallId = null;
        $brief = $this->context->approved($client, ArtifactType::BrandBrief);
        try {
            $result = $this->gateway->run(new RoleRequest(
                clientId: $client->id,
                roleKey: 'report_writer',
                taskRef: 'weekly_report:'.$client->ulid.':'.$weekStart->toDateString(),
                artifacts: array_filter(['brand_brief' => $brief, 'kpi_snapshot' => $kpi]),
                task: ['instruksi' => 'Tulis komentar laporan mingguan.', 'nama_klien' => $client->name],
            ));
            $narrative = $result->payload;
            $llmCallId = $result->llmCallId;
        } catch (LlmGatewayException) {
            // The numbers still go out; the commentary is optional (PRD principle 7).
        }

        $html = View::make('reports.weekly', [
            'client' => $client, 'narrative' => $narrative, 'period' => $kpi['period'], 'rows' => $kpi['rows'], 'quality' => $kpi['data_quality'] ?? [],
        ])->render();
        $pdf = $this->assets->storeContents($client, $this->renderer->pdf($html), 'pdf', [
            'kind' => AssetKind::Report, 'source' => 'system', 'title' => "Laporan {$weekStart->toDateString()}",
        ]);
        $pdf->update(['mime' => 'application/pdf']);

        $report = Report::query()->updateOrCreate(
            ['client_id' => $client->id, 'period_type' => 'week', 'period_start' => $weekStart->toDateString()],
            ['workspace_id' => $client->workspace_id, 'period_end' => $weekEnd->toDateString(), 'kpi_snapshot_version_id' => $snapshot->id,
                'narrative' => $narrative, 'pdf_asset_id' => $pdf->id, 'llm_call_id' => $llmCallId, 'status' => 'ready'],
        );

        if ($send) {
            $this->send($report);
        }

        return $report;
    }

    public function send(Report $report): int
    {
        /** @var Client $client */
        $client = $report->client()->firstOrFail();
        $pdf = $report->pdf;
        if ($pdf === null) {
            return 0;
        }

        $summary = __('bot.report_ready', [
            'client' => e($client->name),
            'period' => $report->period_start->format('d/m').' – '.$report->period_end->format('d/m/Y'),
            'summary' => e((string) ($report->narrative['summary'] ?? 'Angka lengkap ada di PDF terlampir.')),
        ]);
        $name = 'laporan-'.$client->slug.'-'.$report->period_start->toDateString().'.pdf';
        $contents = $this->assets->contents($pdf);

        $sent = 0;
        $chats = ClientContact::query()->where('client_id', $client->id)->whereNotNull('telegram_chat_id')->pluck('telegram_chat_id');
        foreach ($chats as $chatId) {
            try {
                $this->telegram->sendMessage((int) $chatId, $summary);
                $this->telegram->sendDocument((int) $chatId, $name, $contents);
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }
        $this->admins->send($summary);
        $report->update(['status' => 'sent', 'sent_at' => now()]);

        return $sent;
    }
}
