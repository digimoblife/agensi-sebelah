<?php

namespace App\Enums;

/** PRD §Prinsip hemat token: T0 no LLM, T1 cheap, T2 DeepSeek-class bulk, T3 premium. */
enum Tier: string
{
    case T0 = 't0';
    case T1 = 't1';
    case T2 = 't2';
    case T3 = 't3';

    /** Model class to pick in Pengaturan > Model per peran (PRD §Prinsip hemat token, routing per tier). */
    public function modelClass(): string
    {
        return match ($this) {
            self::T0 => 'Tanpa AI (dikerjakan kode)',
            self::T1 => 'Murah',
            self::T2 => 'Menengah',
            self::T3 => 'High-end',
        };
    }

    public function examples(): string
    {
        return match ($this) {
            self::T0 => '—',
            self::T1 => 'mis. Claude Haiku 5.5, Gemini Flash-Lite',
            self::T2 => 'mis. DeepSeek, Claude Sonnet 5.5, Gemini Flash',
            self::T3 => 'mis. Claude Opus 5.5, Gemini Pro (cadangan: GPT)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::T3 => 'danger',
            self::T2 => 'warning',
            self::T1 => 'success',
            self::T0 => 'gray',
        };
    }
}
