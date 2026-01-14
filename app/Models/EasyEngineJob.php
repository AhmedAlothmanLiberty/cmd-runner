<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EasyEngineJob extends Model
{
    protected $fillable = [
        'user_id',
        'original_filename','csv_path','csv_sha256',
        'parquet_path','parquet_sha256',
        'state','drop_date',
        's3_bucket','s3_key',
        'status','error','meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'drop_date' => 'date',
    ];
}
