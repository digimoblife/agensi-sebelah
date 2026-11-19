<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Normal = 'normal';
    case Priority = 'priority';
    case Critical = 'critical'; // e.g. a revision the client asked for
}
