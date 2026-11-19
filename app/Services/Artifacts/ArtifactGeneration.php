<?php

namespace App\Services\Artifacts;

use App\Enums\ArtifactType;
use App\Models\ArtifactVersion;
use App\Models\Client;
use App\Services\Artifacts\Generators\ArtifactGenerator;
use App\Services\Artifacts\Generators\BrandBriefGenerator;
use App\Services\Artifacts\Generators\ResearchPackGenerator;
use App\Services\Artifacts\Generators\VisualRulesGenerator;
use App\Services\Llm\LlmGateway;
use InvalidArgumentException;

/** Build role request → gateway → backend overrides → new draft version. */
class ArtifactGeneration
{
    public function __construct(private readonly LlmGateway $gateway, private readonly ArtifactService $artifacts) {}

    public function generate(Client $client, ArtifactType $type): ArtifactVersion
    {
        $generator = $this->generator($type);
        $request = $generator->request($client);
        $result = $this->gateway->run($request);
        $payload = $generator->finalize($result->payload, $client, $request);

        return $this->artifacts->createVersion(
            $client,
            $type,
            $payload,
            llmCallId: $result->llmCallId,
            changeNote: "Dibuat AI ({$result->modelId})",
        );
    }

    /** @return list<ArtifactType> */
    public static function supported(): array
    {
        return [ArtifactType::BrandBrief, ArtifactType::VisualRules, ArtifactType::ResearchPack];
    }

    private function generator(ArtifactType $type): ArtifactGenerator
    {
        return match ($type) {
            ArtifactType::BrandBrief => app(BrandBriefGenerator::class),
            ArtifactType::VisualRules => app(VisualRulesGenerator::class),
            ArtifactType::ResearchPack => app(ResearchPackGenerator::class),
            default => throw new InvalidArgumentException("No generator for {$type->value} yet."),
        };
    }
}
