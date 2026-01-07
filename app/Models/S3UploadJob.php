<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class S3UploadJob extends Model
{
    protected $table = 's3_upload_jobs';

    protected $fillable = [
        'uploader','request_ip',
        'original_name','stored_path','mime','size','sha256',
        's3_bucket','s3_key','s3_etag',
        'drop_date','state',
        'status','error',
        'started_at','finished_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'drop_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'size' => 'integer',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_QUEUED = 'queued';
}
