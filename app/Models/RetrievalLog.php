<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetrievalLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'query',
        'execution_plan',
        'fused_results',
        'metadata',
        'latency_ms',
    ];

    protected $casts = [
        'execution_plan' => 'array',
        'fused_results' => 'array',
        'metadata' => 'array',
    ];
}
