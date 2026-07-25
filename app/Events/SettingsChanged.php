<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SettingsChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $key,
        public readonly mixed $oldValue,
        public readonly mixed $newValue,
        public readonly string $type,
        public readonly ?int $userId,
    ) {}
}
