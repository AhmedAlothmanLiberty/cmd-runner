<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;
use App\Models\Automation;
use App\Services\AutomationExecutor;
use Throwable;

class RunScheduledTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automation:run-legacy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Legacy automation runner (use automations:run instead)';

    /**
     * Hide legacy command from lists.
     *
     * @var bool
     */
    protected $hidden = true;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $executor = app(AutomationExecutor::class);

        $tasks = Automation::where('is_active', true)->get();

        foreach ($tasks as $task) {
            if ($task->shouldRunNow()) {
                $executor->run($task, 'scheduler');
            }
        }

        return Command::SUCCESS;
    }
}
