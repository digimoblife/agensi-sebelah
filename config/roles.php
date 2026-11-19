<?php

use App\Roles\Validators\ContentBatchValidator;
use App\Roles\Validators\ContentPlanValidator;
use App\Roles\Validators\GuardianReviewValidator;
use App\Roles\Validators\ResearchPackValidator;
use App\Roles\Validators\WeeklyReportValidator;

/*
| AI role registry (PRD §Struktur tim AI). A role is a prompt + output schema, not a running agent.
| Model choice per role lives in role_configs (dashboard); these are the fixed parts.
|
| capabilities: what the model must support (checked against model_catalog).
| artifact_inputs: the only artifacts the role may receive (narrow input contract, PRD principle 6).
| priority roles may still run (one tier down) when the client budget is degraded.
*/

return [
    'brand_strategist' => [
        'why' => 'Menentukan arah brand; jarang dipanggil (onboarding, per kuartal), kualitas penalaran paling penting.',
        'label' => 'Brand Strategist',
        'tier' => 't3',
        'prompt' => 'brand_strategist',
        'prompt_version' => 1,
        'output_schema' => 'brand_brief.v1',
        'artifact_inputs' => ['research_pack'],
        'uses_facts' => true,
        'capabilities' => ['text'],
        'effort' => 'high',
        'max_output_tokens' => 8000,
        'expected_output_tokens' => 2500,
        'allow_downgrade' => false,
    ],
    'art_director' => [
        'why' => 'Menetapkan aturan visual sekali per klien; butuh selera dan konsistensi.',
        'label' => 'Art Director',
        'tier' => 't3',
        'prompt' => 'art_director',
        'prompt_version' => 1,
        'output_schema' => 'visual_rules.v1',
        'artifact_inputs' => ['brand_brief'],
        'uses_facts' => false,
        'capabilities' => ['text'],
        'effort' => 'medium',
        'max_output_tokens' => 4000,
        'expected_output_tokens' => 1200,
        'allow_downgrade' => false,
    ],
    'researcher' => [
        'why' => 'Merangkum bahan riset; teks panjang tapi tidak butuh penalaran premium.',
        'label' => 'Researcher',
        'tier' => 't2',
        'prompt' => 'researcher',
        'prompt_version' => 1,
        'output_schema' => 'research_pack.v1',
        'validator' => ResearchPackValidator::class,
        'artifact_inputs' => ['brand_brief'],
        'uses_facts' => true,
        'capabilities' => ['text'],
        'effort' => 'medium',
        'max_output_tokens' => 8000,
        'expected_output_tokens' => 3000,
        'allow_downgrade' => true,
    ],
    'content_strategist' => [
        'why' => 'Menyusun rencana bulanan dari Brand Brief; struktur rapi, sebulan sekali.',
        'label' => 'Content Strategist',
        'tier' => 't2',
        'prompt' => 'content_strategist',
        'prompt_version' => 1,
        'output_schema' => 'content_plan.v1',
        'validator' => ContentPlanValidator::class,
        'artifact_inputs' => ['brand_brief', 'research_pack'],
        'uses_facts' => true,
        'capabilities' => ['text'],
        'effort' => 'medium',
        'max_output_tokens' => 12000,
        'expected_output_tokens' => 5000,
        'allow_downgrade' => true,
    ],
    'copywriter' => [
        'why' => 'Menulis banyak caption sekaligus setiap minggu; volume tinggi, biaya paling terasa.',
        'label' => 'Copywriter',
        'tier' => 't2',
        'prompt' => 'copywriter',
        'prompt_version' => 1,
        'output_schema' => 'content_batch.v1',
        'validator' => ContentBatchValidator::class,
        'artifact_inputs' => ['brand_brief', 'visual_rules'],
        'uses_facts' => true,
        'capabilities' => ['text'],
        'effort' => 'low',
        'max_output_tokens' => 16000,
        'expected_output_tokens' => 8000,
        'allow_downgrade' => true,
    ],
    'brand_guardian' => [
        'why' => 'Memeriksa draf (tone, klaim, typo); tugas pendek dan sering, cukup model murah.',
        'label' => 'Brand Guardian',
        'tier' => 't1',
        'prompt' => 'brand_guardian',
        'prompt_version' => 1,
        'output_schema' => 'guardian_review.v1',
        'validator' => GuardianReviewValidator::class,
        'artifact_inputs' => ['brand_brief'],
        'uses_facts' => true,
        'capabilities' => ['text'],
        'effort' => 'low',
        'max_output_tokens' => 6000,
        'expected_output_tokens' => 1500,
        'allow_downgrade' => true,
    ],
    'report_writer' => [
        'why' => 'Menulis komentar laporan dari angka yang sudah dihitung; singkat, mingguan.',
        'label' => 'Report Writer',
        'tier' => 't2',
        'prompt' => 'report_writer',
        'prompt_version' => 1,
        'output_schema' => 'weekly_report.v1',
        'validator' => WeeklyReportValidator::class,
        'artifact_inputs' => ['brand_brief', 'kpi_snapshot'],
        'uses_facts' => false,
        'capabilities' => ['text'],
        'effort' => 'low',
        'max_output_tokens' => 3000,
        'expected_output_tokens' => 800,
        'allow_downgrade' => true,
    ],
];
