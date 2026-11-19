<?php

use App\Enums\ArtifactStatus;
use App\Enums\ArtifactType;
use App\Services\Artifacts\ArtifactService;

function examplePayload(string $name): array
{
    $data = json_decode(file_get_contents(resource_path("schemas/examples/{$name}.v1.json")), true);
    unset($data['meta']);

    return $data;
}

it('stores numbered versions with backend-written meta', function () {
    $client = client();
    $service = app(ArtifactService::class);

    $v1 = $service->createVersion($client, ArtifactType::BrandBrief, examplePayload('brand_brief'));
    $v2 = $service->createVersion($client, ArtifactType::BrandBrief, ['meta' => ['client_id' => 999]] + examplePayload('brand_brief'));

    expect($v1->version)->toBe(1)->and($v2->version)->toBe(2)
        ->and($v2->payload['meta']['client_id'])->toBe($client->id)
        ->and($v2->payload['meta']['version'])->toBe(2)
        ->and($v2->token_estimate)->toBeGreaterThan(100)
        ->and($v2->artifact->current_version_id)->toBe($v2->id);
});

it('rejects payloads that do not satisfy the schema', function () {
    app(ArtifactService::class)->createVersion(client(), ArtifactType::ContentPlan, ['month' => '2026-13', 'items' => []]);
})->throws(InvalidArgumentException::class, 'content_plan');

it('supersedes the previous approved version and returns the latest approved', function () {
    $client = client();
    $user = admin();
    $service = app(ArtifactService::class);
    $v1 = $service->approve($service->createVersion($client, ArtifactType::VisualRules, examplePayload('visual_rules')), $user);
    $v2 = $service->approve($service->createVersion($client, ArtifactType::VisualRules, examplePayload('visual_rules')), $user);

    expect($v1->fresh()->status)->toBe(ArtifactStatus::Superseded)
        ->and($service->approved($client, ArtifactType::VisualRules)?->id)->toBe($v2->id);
});

it('never approves a version twice', function () {
    $service = app(ArtifactService::class);
    $version = $service->approve($service->createVersion(client(), ArtifactType::VisualRules, examplePayload('visual_rules')), admin());

    $service->approve($version, admin());
})->throws(LogicException::class);
