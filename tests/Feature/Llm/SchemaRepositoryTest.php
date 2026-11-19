<?php

use App\Services\Schemas\SchemaRepository;

it('builds a provider-safe schema without refs, unsupported keywords or meta', function (string $name) {
    $schema = app(SchemaRepository::class)->providerSchema($name);
    $json = json_encode($schema);

    expect($json)->not->toContain('$ref')->not->toContain('maxLength')->not->toContain('"pattern"')
        ->not->toContain('maxItems')->not->toContain('"minimum"')
        ->and($schema['properties'] ?? [])->not->toHaveKey('meta')
        ->and($schema['required'] ?? [])->not->toContain('meta');
})->with(['brand_brief.v1', 'visual_rules.v1', 'research_pack.v1', 'content_plan.v1', 'content_batch.v1', 'kpi_snapshot.v1']);

it('inlines nested and cross-file refs', function () {
    $schema = app(SchemaRepository::class)->providerSchema('content_batch.v1');

    $item = $schema['properties']['items']['items'];
    expect($item['properties']['platform']['enum'])->toContain('instagram')
        ->and($item['additionalProperties'])->toBeFalse();
});

it('reports readable errors with JSON pointers', function () {
    $errors = app(SchemaRepository::class)->errors('content_batch.v1', ['items' => [['plan_item_key' => 'x1']]]);

    expect($errors)->not->toBeEmpty()->and($errors[0])->toStartWith('/items/0');
});
