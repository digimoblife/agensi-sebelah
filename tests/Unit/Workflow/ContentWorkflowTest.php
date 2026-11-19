<?php

use App\Enums\ContentStatus;
use App\Services\Workflow\ContentWorkflow;

it('defines transitions for every status', function () {
    foreach (ContentStatus::cases() as $status) {
        expect(ContentWorkflow::MATRIX)->toHaveKey($status->value);
    }
});

it('locks the key transitions of the matrix', function (string $from, ContentStatus $to, bool $allowed) {
    expect(in_array($to, ContentWorkflow::MATRIX[$from], true))->toBe($allowed);
})->with([
    'plan to drafting' => ['planned', ContentStatus::Drafting, true],
    'no approval without the guardian' => ['drafted', ContentStatus::Approved, false],
    'no skipping approval' => ['awaiting_approval', ContentStatus::Rendered, false],
    'published is final' => ['published', ContentStatus::Cancelled, false],
    'failed publish is not retried silently' => ['publish_failed', ContentStatus::Published, false],
    'revision goes back to drafting' => ['revision_requested', ContentStatus::Drafting, true],
]);
