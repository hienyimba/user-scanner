<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Connector extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'category',
        'supports_username',
        'supports_email',
        'enabled',
        'timeout_seconds',
        'retry_limit',
        'health_status',
        'last_health_check_at',
    ];

    protected $casts = [
        'supports_username' => 'boolean',
        'supports_email' => 'boolean',
        'enabled' => 'boolean',
        'last_health_check_at' => 'datetime',
    ];
}
