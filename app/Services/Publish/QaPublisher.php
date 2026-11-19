<?php

namespace App\Services\Publish;

use App\Enums\PublishStatus;
use App\Models\ContentItem;
use App\Models\PublishJob;
use App\Services\Content\GuardianRules;
use App\Services\Render\ContentRenderer;
use Carbon\CarbonImmutable;

/** F4.3 QA Publisher (T0): last checks before a post is scheduled. Major issues block scheduling. */
class QaPublisher
{
    public function __construct(private readonly ContentRenderer $renderer, private readonly LinkChecker $links) {}

    /** @return list<array{severity: string, note: string}> */
    public function check(ContentItem $item, CarbonImmutable $at, ?int $ignoreJobId = null): array
    {
        $at = $at->utc();
        $issues = [];
        $add = function (string $severity, string $note) use (&$issues) {
            $issues[] = ['severity' => $severity, 'note' => $note];
        };
        $draft = (array) $item->draft;

        if ($this->renderer->supports($item) && ! $this->renderer->isCurrent($item)) {
            $add('major', 'Visual belum dirender atau tidak sesuai draf terakhir.');
        }
        if ($at->lt(now()->subMinutes(5))) {
            $add('major', 'Waktu terbit sudah lewat.');
        }

        $limit = GuardianRules::CAPTION_LIMITS[$item->platform] ?? 2200;
        $caption = $this->caption($item);
        if (mb_strlen($caption) > $limit) {
            $add('major', "Caption + hashtag melebihi {$limit} karakter.");
        }
        if ($item->format === 'carousel' && count($item->render['assets'] ?? []) > 10) {
            $add('major', 'Carousel Instagram maksimal 10 gambar.');
        }

        if (is_string($draft['link'] ?? null) && $draft['link'] !== '') {
            match ($this->links->status($draft['link'])) {
                'broken' => $add('major', 'Link di konten tidak bisa dibuka.'),
                'unknown' => $add('minor', 'Link tidak bisa dicek sekarang.'),
                default => null,
            };
        }

        $neighbours = PublishJob::query()
            ->where(['client_id' => $item->client_id, 'platform' => $item->platform])
            ->whereIn('status', [PublishStatus::Scheduled, PublishStatus::Publishing])
            ->when($ignoreJobId !== null, fn ($q) => $q->where('id', '!=', $ignoreJobId))
            ->whereBetween('scheduled_at', [$at->subHours(3), $at->addHours(3)])
            ->get();
        foreach ($neighbours as $job) {
            $gap = abs($job->scheduled_at->diffInMinutes($at));
            $add($gap < 60 ? 'major' : 'minor', "Jadwal berdekatan dengan konten lain di {$item->platform} (selisih ".(int) $gap.' menit).');
        }

        return $issues;
    }

    public function caption(ContentItem $item): string
    {
        $draft = (array) $item->draft;
        $tags = implode(' ', (array) ($draft['hashtags'] ?? []));

        return trim(((string) ($draft['caption'] ?? '')).($tags !== '' ? "\n\n".$tags : ''));
    }

    /** @param  list<array{severity: string, note: string}>  $issues */
    public static function blocks(array $issues): bool
    {
        return in_array('major', array_column($issues, 'severity'), true);
    }
}
