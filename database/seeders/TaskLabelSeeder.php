<?php

namespace Database\Seeders;

use App\Models\TaskLabel;
use Illuminate\Database\Seeder;

class TaskLabelSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            ['name' => 'report', 'color' => '#2563EB'],
            ['name' => 'automation', 'color' => '#0EA5E9'],
            ['name' => 'admin', 'color' => '#8B5CF6'],
            ['name' => 'backend', 'color' => '#10B981'],
            ['name' => 'bug', 'color' => '#EF4444'],
            ['name' => 'cron', 'color' => '#F59E0B'],
            ['name' => 'enhance', 'color' => '#22C55E'],
            ['name' => 'feature', 'color' => '#6366F1'],
            ['name' => 'onhold', 'color' => '#F97316'],
            ['name' => 'task', 'color' => '#64748B'],
        ];

        foreach ($labels as $label) {
            TaskLabel::updateOrCreate(
                ['name' => $label['name']],
                ['color' => $label['color']]
            );
        }
    }
}
