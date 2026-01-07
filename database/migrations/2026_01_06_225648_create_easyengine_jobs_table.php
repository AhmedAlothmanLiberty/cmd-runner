<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('easyengine_jobs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('original_filename');
            $table->string('csv_path');          // storage local path relative
            $table->string('csv_sha256', 64);

            $table->string('parquet_path')->nullable();
            $table->string('parquet_sha256', 64)->nullable();

            $table->string('state', 10)->nullable();          // e.g. CA
            $table->date('drop_date')->nullable();            // partition date

            $table->string('s3_bucket')->nullable();
            $table->string('s3_key')->nullable();

            $table->string('status')->default('uploaded'); // uploaded|converted|uploaded_s3|failed
            $table->text('error')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['status']);
            $table->index(['drop_date', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easyengine_jobs');
    }
};
