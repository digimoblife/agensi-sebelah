<?php

namespace App\Services\Activity;

use App\Models\ActivityLog;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/** F9.9. Callers must never pass secret values in $properties. */
class ActivityLogger
{
    /** @param  array<string, scalar|null>  $properties */
    public function log(string $action, ?string $subject = null, array $properties = []): void
    {
        ActivityLog::query()->create([
            'workspace_id' => Workspace::current()->id,
            'user_id' => Auth::id(),
            'action' => $action,
            'subject' => $subject,
            'properties' => $properties === [] ? null : $properties,
            'ip' => app()->runningInConsole() ? null : Request::ip(),
        ]);
    }
}
