<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackageUpdateLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'package',
        'branch',
        'env',
        'status',
        'output',
        'triggered_by',
    ];
}
