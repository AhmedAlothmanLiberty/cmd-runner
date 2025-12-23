<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->string('schedule_mode')->default('daily')->after('notify_on_fail');
            $table->json('day_times')->nullable()->after('schedule_mode');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->dropColumn(['schedule_mode', 'day_times']);
        });
    }
};
