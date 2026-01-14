<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('s3_upload_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('s3_upload_jobs', 'parquet_path')) {
                $table->string('parquet_path')->nullable()->after('stored_path');
            }
            if (!Schema::hasColumn('s3_upload_jobs', 'parquet_sha256')) {
                $table->string('parquet_sha256', 64)->nullable()->after('sha256');
            }
            if (!Schema::hasColumn('s3_upload_jobs', 'meta')) {
                $table->json('meta')->nullable()->after('error');
            }
        });
    }

    public function down(): void {
        Schema::table('s3_upload_jobs', function (Blueprint $table) {
            $table->dropColumn(['parquet_path','parquet_sha256','meta']);
        });
    }
};