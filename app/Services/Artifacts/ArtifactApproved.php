<?php

namespace App\Services\Artifacts;

use App\Models\ArtifactVersion;
use Illuminate\Foundation\Events\Dispatchable;

final class ArtifactApproved
{
    use Dispatchable;

    public function __construct(public readonly ArtifactVersion $version) {}
}
