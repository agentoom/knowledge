<?php

namespace App\Knowledge\Enums;

enum ProviderStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Error = 'error';
    case Syncing = 'syncing';
}
