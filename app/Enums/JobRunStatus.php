<?php

namespace App\Enums;

enum JobRunStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case NeedsManual = 'needs_manual';
}
