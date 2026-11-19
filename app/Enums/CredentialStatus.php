<?php

namespace App\Enums;

enum CredentialStatus: string
{
    case Untested = 'untested';
    case Ok = 'ok';
    case Failed = 'failed';
}
