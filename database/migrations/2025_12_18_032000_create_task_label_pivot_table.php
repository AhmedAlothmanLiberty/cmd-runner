<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_task_label', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('task_label_id')->constrained('task_labels')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'task_label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_task_label');
    }
};
