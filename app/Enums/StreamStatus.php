<?php

namespace App\Enums;

enum StreamStatus: string
{
    case Offline = 'offline';
    case Live = 'live';
}
