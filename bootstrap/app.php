<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        app_path('Console/Commands'),

        // app commands
        \App\Console\Commands\TestAutomationCommand::class,
        \App\Console\Commands\RunAutomation::class,

        // package commands (register only if the class exists)
        ...array_values(array_filter([

            \Cmd\Reports\Console\Commands\SeedCmdReportPermissions::class,

            \Cmd\Reports\Console\Commands\TestDatabaseConnections::class,
            \Cmd\Reports\Console\Commands\SyncBalances::class,
            \Cmd\Reports\Console\Commands\SyncBalancesHistory::class,
            \Cmd\Reports\Console\Commands\SyncEnrollmentPlans::class,
            \Cmd\Reports\Console\Commands\SyncDebtAccounts::class,
            \Cmd\Reports\Console\Commands\SyncSubmittedDate::class,
            \Cmd\Reports\Console\Commands\SyncFirstPaymentDate::class,
            \Cmd\Reports\Console\Commands\SyncFirstPaymentClearedDate::class,
            \Cmd\Reports\Console\Commands\SyncTimeInProgram::class,
            \Cmd\Reports\Console\Commands\SyncEPFData::class,
            \Cmd\Reports\Console\Commands\UpdateEPFRates::class,
            \Cmd\Reports\Console\Commands\SyncSettlementData::class,
            \Cmd\Reports\Console\Commands\SyncSettledDebtsData::class,
            \Cmd\Reports\Console\Commands\SyncEnrollmentStatus::class,
            \Cmd\Reports\Console\Commands\SyncVerifiedDebts::class,
            \Cmd\Reports\Console\Commands\SyncContactsData::class,
            \Cmd\Reports\Console\Commands\SyncCollectionCompanies::class,
            \Cmd\Reports\Console\Commands\SyncLastDepositDate::class,
            \Cmd\Reports\Console\Commands\SyncVeritasTransactions::class,
            \Cmd\Reports\Console\Commands\SyncNegotiatorPayrollData::class,
            \Cmd\Reports\Console\Commands\SyncEnrollmentDataTemp::class,
            \Cmd\Reports\Console\Commands\GenerateWelcomeLetterReport::class,
            \Cmd\Reports\Console\Commands\GenerateWelcomePacketReport::class,
            \Cmd\Reports\Console\Commands\GenerateLookbackSummaryReport::class,
            \Cmd\Reports\Console\Commands\GenerateCompanyStatsReport::class,
            \Cmd\Reports\Console\Commands\GenerateLegalReport::class,
            \Cmd\Reports\Console\Commands\GenerateSyncSummary::class,


        ], fn(string $c) => class_exists($c))),
    ])
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'internal.basic' => \App\Http\Middleware\InternalBasicAuth::class,

        ]);

        $middleware->web(append: [
            \App\Http\Middleware\TrackUserActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
