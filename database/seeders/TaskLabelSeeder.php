<?php

namespace Database\Seeders;

use App\Models\TaskLabel;
use Illuminate\Database\Seeder;

class TaskLabelSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            ['name' => 'Automation', 'color' => '#0EA5E9'],
            ['name' => 'Report', 'color' => '#2563EB'],
            ['name' => 'Bug', 'color' => '#EF4444'],
            ['name' => 'Feature', 'color' => '#6366F1'],
            ['name' => 'Improvement', 'color' => '#10B981'],
            ['name' => 'Research', 'color' => '#8B5CF6'],
            ['name' => 'Documentation', 'color' => '#F59E0B'],
            ['name' => 'Testing', 'color' => '#14B8A6'],
            ['name' => 'Deployment', 'color' => '#F97316'],
            ['name' => 'Design', 'color' => '#EC4899'],
        ];

        foreach ($labels as $label) {
            TaskLabel::updateOrCreate(
                ['name' => $label['name']],
                ['color' => $label['color']]
            );
        }
    }
}
