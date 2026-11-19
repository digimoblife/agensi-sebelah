<?php

namespace App\Enums;

enum OfferingStatus: string
{
    case Active = 'active';
    case Upcoming = 'upcoming';
    case Archived = 'archived';
}
