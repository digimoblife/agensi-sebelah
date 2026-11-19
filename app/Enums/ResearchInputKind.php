<?php

namespace App\Enums;

/** Mirrors research_pack.v1 sources[].kind. Only public or admin-provided sources (ANALISIS K7). */
enum ResearchInputKind: string
{
    case Website = 'website';
    case AdLibrary = 'ad_library';
    case GoogleTrends = 'google_trends';
    case Serp = 'serp';
    case SocialPublic = 'social_public';
    case ManualNote = 'manual_note';
}
