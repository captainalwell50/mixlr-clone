<?php

namespace App\Enums;

enum EventStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Paused = 'paused';
    case Ended = 'ended';
}
