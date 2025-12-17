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
        Schema::create('package_update_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('package');
            $table->string('branch');
            $table->string('env');
            $table->string('status');
            $table->longText('output')->nullable();
            $table->string('triggered_by')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_update_logs');
    }
};
