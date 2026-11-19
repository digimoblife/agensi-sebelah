<?php

use Opis\JsonSchema\Validator;

/*
 * The fictional examples in resources/schemas/examples must always satisfy their schema, with the same
 * PHP validator the gateway will use (docs/ARTIFACTS.md). They double as FakeLlmProvider fixtures.
 */
function artifactValidator(): Validator
{
    $validator = new Validator;
    $validator->resolver()->registerPrefix(
        'https://agensi-sebelah.local/schemas/',
        resource_path('schemas/artifacts/'),
    );

    return $validator;
}

it('validates every example against its schema', function (string $example) {
    $schemaId = 'https://agensi-sebelah.local/schemas/'.str_replace('.json', '.schema.json', $example);
    $data = json_decode((string) file_get_contents(resource_path("schemas/examples/{$example}")));

    $result = artifactValidator()->validate($data, $schemaId);

    expect($result->isValid())->toBeTrue((string) json_encode($result->error()?->args()));
})->with(array_map('basename', glob(__DIR__.'/../../resources/schemas/examples/*.json') ?: []));

it('rejects unknown properties and malformed hashtags', function () {
    $data = json_decode((string) json_encode(['items' => [[
        'plan_item_key' => 'x1', 'platform' => 'instagram', 'format' => 'story', 'caption' => 'a',
        'hashtags' => ['nohash'], 'visual' => ['mode' => 'template'], 'extra' => 1,
    ]]]));

    $result = artifactValidator()->validate($data, 'https://agensi-sebelah.local/schemas/content_batch.v1.schema.json');

    expect($result->isValid())->toBeFalse();
});

it('accepts Unicode hashtags', function () {
    $item = json_decode((string) file_get_contents(resource_path('schemas/examples/content_batch.v1.json')));
    $item->items[0]->hashtags = ['#kerajinan_tangan', '#café'];

    expect(artifactValidator()->validate($item, 'https://agensi-sebelah.local/schemas/content_batch.v1.schema.json')->isValid())->toBeTrue();
});
