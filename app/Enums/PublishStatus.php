<?php

namespace App\Enums;

enum PublishStatus: string
{
    case Scheduled = 'scheduled';
    case Publishing = 'publishing';
    case Published = 'published';
    case Failed = 'failed';
    case Missed = 'missed';
    case Packaged = 'packaged';
    case ManuallyUploaded = 'manually_uploaded';
    case Cancelled = 'cancelled';
}
