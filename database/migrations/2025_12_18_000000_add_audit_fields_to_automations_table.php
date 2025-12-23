<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->string('created_by')->nullable()->after('notify_on_fail');
            $table->string('updated_by')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('automations', function (Blueprint $table): void {
            $table->dropColumn(['created_by', 'updated_by']);
        });
    }
};
