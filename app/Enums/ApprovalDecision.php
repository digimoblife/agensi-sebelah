<?php

namespace App\Enums;

enum ApprovalDecision: string
{
    case Approved = 'approved';
    case Revise = 'revise';
    case Rejected = 'rejected';
}
