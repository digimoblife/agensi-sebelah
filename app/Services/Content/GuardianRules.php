<?php

namespace App\Services\Content;

/**
 * F3.3, T0 part of the Brand Guardian: checks code can do without an LLM. Major issues block approval
 * regardless of the LLM review.
 */
final class GuardianRules
{
    /** Practical caption limits per platform (characters). */
    public const CAPTION_LIMITS = ['instagram' => 2200, 'tiktok' => 2200, 'facebook' => 5000, 'google_business_profile' => 1500];

    public const HASHTAG_LIMITS = ['instagram' => 30, 'tiktok' => 10, 'facebook' => 10, 'google_business_profile' => 0];

    /** Numbers that look like facts (prices, percentages, dates, times, counts of seats/people). */
    private const FACT_PATTERN = '/(rp\.?\s?\d|\d+\s?%|\b\d{1,2}\s?(jan|feb|mar|apr|mei|jun|jul|agu|agt|sep|okt|nov|des)|\bjam\s?\d|\b\d{1,2}[.:]\d{2}\b|\b\d+\s?(kursi|orang|peserta|slot|seat))/iu';

    /**
     * @param  array<string, mixed>  $draft  content_item.v1
     * @param  array<string, mixed>  $brief  approved Brand Brief payload
     * @param  array<string, mixed>|null  $visualRules  approved Visual Rules payload
     * @return list<array{type: string, severity: string, note: string, source: string}>
     */
    public function check(array $draft, string $platform, string $format, array $brief, ?array $visualRules): array
    {
        $issues = [];
        $add = function (string $type, string $severity, string $note) use (&$issues) {
            $issues[] = ['type' => $type, 'severity' => $severity, 'note' => $note, 'source' => 'rule'];
        };

        $caption = (string) ($draft['caption'] ?? '');
        $limit = self::CAPTION_LIMITS[$platform] ?? 2200;
        if (mb_strlen($caption) > $limit) {
            $add('length', 'major', "Caption {$platform} maksimal {$limit} karakter (sekarang ".mb_strlen($caption).').');
        }

        $hashtags = (array) ($draft['hashtags'] ?? []);
        $maxTags = self::HASHTAG_LIMITS[$platform] ?? 30;
        if (count($hashtags) > $maxTags) {
            $add('length', 'major', "Hashtag {$platform} maksimal {$maxTags} (sekarang ".count($hashtags).').');
        }

        $text = $caption.' '.($draft['hook'] ?? '').' '.implode(' ', array_map(fn ($s) => ($s['headline'] ?? '').' '.($s['body'] ?? ''), (array) ($draft['slides'] ?? [])));
        foreach ((array) ($brief['voice']['banned_words'] ?? []) as $word) {
            if ($word !== '' && preg_match('/(?<![\p{L}\p{N}])'.preg_quote((string) $word, '/').'(?![\p{L}\p{N}])/iu', $text) === 1) {
                $add('tone', 'major', "Memakai kata terlarang \"{$word}\".");
            }
        }

        if (preg_match(self::FACT_PATTERN, $text) === 1 && ((array) ($draft['fact_refs'] ?? [])) === []) {
            $add('fact', 'major', 'Ada angka/harga/jadwal tanpa rujukan fakta (fact_refs kosong).');
        }

        if (($brief['voice']['emoji_policy'] ?? null) === 'none' && preg_match('/\p{Extended_Pictographic}/u', $text) === 1) {
            $add('tone', 'minor', 'Brand tidak memakai emoji.');
        }

        $maxHeadline = (int) ($visualRules['typography']['max_headline_chars'] ?? 45);
        $headlines = array_filter([($draft['visual']['headline'] ?? null), ...array_column((array) ($draft['slides'] ?? []), 'headline')]);
        foreach ($headlines as $headline) {
            if (mb_strlen((string) $headline) > $maxHeadline) {
                $add('length', 'major', 'Headline visual "'.mb_strimwidth((string) $headline, 0, 40, '…')."\" melebihi {$maxHeadline} karakter template.");
            }
        }

        if ($format === 'carousel' && count((array) ($draft['slides'] ?? [])) < 2) {
            $add('other', 'major', 'Carousel butuh minimal 2 slide.');
        }

        if (isset($draft['link']) && is_string($draft['link']) && ! str_starts_with($draft['link'], 'https://')) {
            $add('other', 'minor', 'Link sebaiknya https.');
        }

        return $issues;
    }
}
