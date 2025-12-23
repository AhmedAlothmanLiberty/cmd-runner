<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestAutomationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-automation-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simple test command used by Automation module';

    /**
     * Execute the console command.
     */                                                                                                             
    public function handle(): int
    {
        $this->info('Automation test command executed successfully at '.now()->toDateTimeString());

        return self::SUCCESS;
    }
}
