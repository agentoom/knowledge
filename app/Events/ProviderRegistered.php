<?php

namespace App\Events;

use App\Knowledge\Models\Provider;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProviderRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Provider $provider) {}
}
