<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('automations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('command');
            $table->string('cron_expression');
            $table->boolean('is_active')->default(true);
            $table->integer('timeout_seconds')->nullable();
            $table->enum('run_via', ['artisan', 'later'])->default('artisan');
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
            $table->unsignedBigInteger('last_runtime_ms')->nullable();
            $table->boolean('notify_on_fail')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automations');
    }
};
