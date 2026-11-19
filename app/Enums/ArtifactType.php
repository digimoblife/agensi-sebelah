<?php

namespace App\Enums;

enum ArtifactType: string
{
    case BrandBrief = 'brand_brief';
    case VisualRules = 'visual_rules';
    case ResearchPack = 'research_pack';
    case ContentPlan = 'content_plan';
    case KpiSnapshot = 'kpi_snapshot';

    public function schemaName(): string
    {
        return $this->value.'.v1';
    }
}
