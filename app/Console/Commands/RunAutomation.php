<?php

namespace App\Console\Commands;

use App\Models\Automation;
use App\Services\AutomationExecutor;
use Illuminate\Console\Command;

class RunAutomation extends Command
{
    protected $signature = 'automation:run';
    protected $aliases = ['automations:run'];
    protected $description = 'Run all scheduled automations';

    public function handle(): int
    {
        $executor = app(AutomationExecutor::class);

        foreach (Automation::where('is_active', true)->get() as $automation) {
            if ($automation->shouldRunNow()) {
                $executor->run($automation, 'scheduler');
            }
        }

        return self::SUCCESS;
    }
}
