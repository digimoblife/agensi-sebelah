<?php

namespace App\Enums;

/** PRD §Prinsip hemat token: T0 no LLM, T1 cheap, T2 DeepSeek-class bulk, T3 premium. */
enum Tier: string
{
    case T0 = 't0';
    case T1 = 't1';
    case T2 = 't2';
    case T3 = 't3';
}
