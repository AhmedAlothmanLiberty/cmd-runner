<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->json('schedule_frequencies')->nullable()->after('notify_on_fail');
            $table->json('run_times')->nullable()->after('schedule_frequencies');
            $table->json('weekly_days')->nullable()->after('run_times');
            $table->json('monthly_days')->nullable()->after('weekly_days');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->dropColumn(['schedule_frequencies', 'run_times', 'weekly_days', 'monthly_days']);
        });
    }
};
