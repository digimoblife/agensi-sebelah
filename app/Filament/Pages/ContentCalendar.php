<?php

namespace App\Filament\Pages;

use App\Enums\ContentStatus;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Models\Client;
use App\Models\ContentItem;
use App\Models\Moment;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** F2.3: month calendar of content items with Indonesian moments (F9.7). */
class ContentCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Kalender konten';

    protected static ?string $title = 'Kalender konten';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.content-calendar';

    #[Url]
    public ?string $month = null;

    #[Url]
    public ?int $client = null;

    public function mount(): void
    {
        $this->month ??= CarbonImmutable::now('Asia/Jakarta')->format('Y-m');
    }

    public function shift(int $months): void
    {
        $this->month = $this->start()->addMonths($months)->format('Y-m');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $start = $this->start();
        $gridStart = $start->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $start->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        $items = ContentItem::query()->with('client')
            ->where('month', $start->format('Y-m'))
            ->where('status', '!=', ContentStatus::Cancelled)
            ->when($this->client, fn ($q) => $q->where('client_id', $this->client))
            ->orderBy('publish_time')->get()
            ->groupBy(fn (ContentItem $i) => $i->publish_date->toDateString());

        $moments = Moment::query()
            ->where('date_start', '<=', $gridEnd->toDateString())->where('date_end', '>=', $gridStart->toDateString())->get();

        $days = [];
        for ($day = $gridStart; $day <= $gridEnd; $day = $day->addDay()) {
            $date = $day->toDateString();
            $days[] = [
                'date' => $day,
                'inMonth' => $day->month === $start->month,
                'items' => $items->get($date, collect()),
                'moments' => $moments->filter(fn (Moment $m) => $m->date_start->toDateString() <= $date && $m->date_end->toDateString() >= $date && $m->kind !== 'payday')->pluck('name'),
                'payday' => $moments->contains(fn (Moment $m) => $m->kind === 'payday' && $m->date_start->toDateString() === $date),
            ];
        }

        return [
            'days' => $days,
            'label' => $this->monthLabel($start),
            'clients' => Client::query()->orderBy('name')->pluck('name', 'id'),
            'listUrl' => ContentItemResource::getUrl('index'),
        ];
    }

    private function start(): CarbonImmutable
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $this->month) === 1 ? (string) $this->month : CarbonImmutable::now('Asia/Jakarta')->format('Y-m');

        return CarbonImmutable::parse($month.'-01', 'Asia/Jakarta')->startOfMonth();
    }

    private function monthLabel(CarbonImmutable $start): string
    {
        /** @var CarbonImmutable $local */
        $local = $start->locale('id');

        return $local->translatedFormat('F Y');
    }
}
