<?php

namespace App\Enums;

enum EventAccess: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';
}
