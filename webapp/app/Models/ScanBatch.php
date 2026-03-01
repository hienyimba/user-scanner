<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScanStatus;
use App\Enums\ScanType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScanBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'target',
        'status',
        'options',
        'total_items',
        'processed_items',
        'error_count',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'options' => 'array',
        'type' => ScanType::class,
        'status' => ScanStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function results(): HasMany
    {
        return $this->hasMany(ScanResult::class, 'scan_batch_id');
    }
}
