<?php

namespace App\Enums;

enum ClientStatus: string
{
    case Onboarding = 'onboarding';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
