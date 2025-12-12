<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->string('timezone')->nullable()->after('cron_expression');
            $table->string('daily_time')->nullable()->after('timezone'); // format HH:MM
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->dropColumn(['timezone', 'daily_time']);
        });
    }
};
