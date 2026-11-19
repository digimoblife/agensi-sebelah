<?php

namespace App\Services\Publish;

use App\Models\PublishJob;

interface Publisher
{
    public function canPublish(PublishJob $job): bool;

    /**
     * @return array{id: string, permalink: ?string}
     *
     * @throws PublishFailed
     */
    public function publish(PublishJob $job): array;
}
