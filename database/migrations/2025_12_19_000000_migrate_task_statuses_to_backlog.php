<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tasks')
            ->whereIn('status', ['blocked', 'on_hold'])
            ->update(['status' => 'backlog']);
    }

    public function down(): void
    {
        // Irreversible: original status for each task is not tracked.
    }
};
