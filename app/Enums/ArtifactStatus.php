<?php

namespace App\Enums;

enum ArtifactStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Superseded = 'superseded';
    case Rejected = 'rejected';
}
