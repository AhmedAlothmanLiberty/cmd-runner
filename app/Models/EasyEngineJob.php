<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EasyEngineJob extends Model
{
    protected $table = 'easyengine_jobs';

    protected $fillable = [
        'user_id',
        'original_filename',
        'csv_path',
        'csv_sha256',
        'parquet_path',
        'parquet_sha256',
        'state',
        'drop_date',
        's3_bucket',
        's3_key',
        'status',
        'error',
        'meta',
    ];

    protected $casts = [
        'drop_date' => 'date',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
