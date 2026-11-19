<?php

namespace App\Services\Publish;

use App\Models\PublishJob;

/** Tests (PUBLISHER=fake): publishes nothing; can be told to fail. */
final class FakePublisher implements Publisher
{
    public bool $available = true;

    public ?string $failWith = null;

    /** @var list<int> */
    public array $published = [];

    public function canPublish(PublishJob $job): bool
    {
        return $this->available && $job->platform === 'instagram';
    }

    public function publish(PublishJob $job): array
    {
        if ($this->failWith !== null) {
            throw new PublishFailed($this->failWith);
        }
        $this->published[] = $job->id;

        return ['id' => 'ig_'.$job->id, 'permalink' => 'https://instagram.example/p/'.$job->id];
    }
}
