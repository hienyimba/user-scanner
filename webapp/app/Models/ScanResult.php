<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ResultStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'scan_batch_id',
        'connector_key',
        'category',
        'site_name',
        'status',
        'reason',
        'checked_url',
        'confidence',
        'response_metadata',
    ];

    protected $casts = [
        'status' => ResultStatus::class,
        'response_metadata' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ScanBatch::class, 'scan_batch_id');
    }
}
