<?php

namespace App\Jobs;

use App\Enums\ContentStatus;
use App\Models\ContentItem;
use App\Services\Artifacts\PrerequisiteMissing;
use App\Services\Render\ContentRenderer;
use App\Services\Telegram\ApprovalSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * After the Brand Guardian passes: render the visual preview (T0) and send it to the client's approvers.
 * Rendering problems never block the approval request; the text still goes out.
 */
class RenderAndSend implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /** @param  list<int>  $itemIds */
    public function __construct(public readonly array $itemIds, public readonly bool $send = true)
    {
        $this->onQueue('render');
    }

    public function handle(ContentRenderer $renderer, ApprovalSender $sender): void
    {
        $items = ContentItem::query()->whereIn('id', $this->itemIds)->where('status', ContentStatus::AwaitingApproval)->get();

        foreach ($items as $item) {
            if ($renderer->supports($item) && ! $renderer->isCurrent($item)) {
                try {
                    $renderer->render($item);
                } catch (PrerequisiteMissing) {
                    // No approved Visual Rules yet: send the text-only request.
                } catch (Throwable $e) {
                    report($e);
                }
            }

            if ($this->send) {
                $sender->send($item->fresh() ?? $item);
            }
        }
    }
}
