<?php

namespace App\Enums;

enum AssetKind: string
{
    case Logo = 'logo';
    case Photo = 'photo';
    case Video = 'video';
    case Screenshot = 'screenshot';
    case Illustration = 'illustration';
    case Render = 'render';
    case AiImage = 'ai_image';
    case AiVideo = 'ai_video';
    case Package = 'package';
    case Report = 'report';
}
