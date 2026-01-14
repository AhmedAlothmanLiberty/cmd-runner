<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    public function up(): void
    {
        Schema::create('s3_upload_jobs', function (Blueprint $table) {
            $table->id();

            // Who triggered it (Basic Auth / internal)
            $table->string('uploader')->nullable();       // e.g. basic auth username
            $table->string('request_ip', 45)->nullable(); // IPv4/IPv6

            // Source file metadata
            $table->string('original_name');
            $table->string('stored_path')->nullable();    // local temp path (storage/app/...)
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('sha256', 64)->nullable();

            // Target S3
            $table->string('s3_bucket')->nullable();
            $table->string('s3_key')->nullable();         // full key inside bucket
            $table->string('s3_etag')->nullable();

            // Partition fields (optional but useful)
            $table->date('drop_date')->nullable();        // yyyy-mm-dd
            $table->string('state', 10)->nullable();      // e.g. CA

            // Status tracking
            $table->string('status', 20)->default('pending'); // pending|processing|uploaded|failed
            $table->text('error')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->json('meta')->nullable(); // any extras (counts, timings, etc.)
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['drop_date', 'state']);
            $table->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s3_upload_jobs');
    }
};
