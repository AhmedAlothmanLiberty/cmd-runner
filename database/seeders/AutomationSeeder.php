<?php

namespace Database\Seeders;

use App\Models\Automation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AutomationSeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake();

        $timeOptions = ['01:00', '03:30', '06:00', '09:15', '12:00', '15:30', '18:00', '21:45'];
        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $commands = [
            'reports:sync-balances',
            'reports:test-connections',
            'sync:first-payment-cleared-date',
            'app:clear-cache',
            'queue:restart',
        ];

        for ($i = 1; $i <= 20; $i++) {
            $name = "Automation {$i}";
            $slug = Str::slug($name) . "-{$i}";
            $isDaily = $i % 2 === 0;

            $runTimes = [];
            $dayTimes = [];
            $weeklyDays = [];

            if ($isDaily) {
                $runTimes = $faker->randomElements($timeOptions, $faker->numberBetween(1, 3));
                sort($runTimes);
            } else {
                foreach ($faker->randomElements($dayKeys, $faker->numberBetween(2, 4)) as $day) {
                    $times = $faker->randomElements($timeOptions, $faker->numberBetween(1, 2));
                    sort($times);
                    $dayTimes[$day] = $times;
                    $weeklyDays[] = $day;
                }
            }

            $lastRunAt = Carbon::now()->subMinutes($faker->numberBetween(0, 72 * 60));

            Automation::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'command' => $faker->randomElement($commands),
                    'cron_expression' => '*/30 * * * *',
                    'timezone' => config('app.timezone'),
                    'daily_time' => $isDaily ? ($runTimes[0] ?? '08:00') : null,
                    'run_times' => $isDaily ? $runTimes : [],
                    'schedule_mode' => $isDaily ? 'daily' : 'custom',
                    'day_times' => $isDaily ? [] : $dayTimes,
                    'weekly_days' => $isDaily ? [] : array_values(array_unique($weeklyDays)),
                    'is_active' => $faker->boolean(80),
                    'timeout_seconds' => $faker->randomElement([null, 30, 60, 120]),
                    'run_via' => $faker->randomElement(['artisan', 'later']),
                    'last_run_at' => $lastRunAt,
                    'last_run_status' => $faker->randomElement(['success', 'failed']),
                    'last_runtime_ms' => $faker->numberBetween(500, 5000),
                    'notify_on_fail' => $faker->boolean(25),
                    'created_by' => 'seeder@example.com',
                    'updated_by' => 'seeder@example.com',
                ]
            );
        }
    }
}
