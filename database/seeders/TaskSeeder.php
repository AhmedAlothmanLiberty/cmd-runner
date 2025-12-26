<?php

namespace Database\Seeders;

use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $doneTitles = [
            'Mailer Data V2',
            'Creditor Contacats',
            'EPF Paid',
            'EPF due',
            'Capital Report',
            'Jordan Expenses',
            'LLG Exec Admin',
            'Veritas',
            'Growth Model',
            'Lending USA prospects',
            'Client Submission Report',
            'Creditor Contacts',
            'Training Report',
            'Mailer Data V2',
            'LeaderBoard',
            "Invoice's",
        ];

        foreach ($doneTitles as $title) {
            Task::updateOrCreate(
                ['title' => $title, 'assigned_to' => 4],
                [
                    'description' => null,
                    'status' => 'done',
                    'priority' => 'medium',
                    'due_at' => null,
                    'completed_at' => now(),
                    'assigned_to' => 4,
                    'created_by' => 4,
                    'updated_by' => 5,
                ]
            );
        }

        $todoTitles = [
            'SyncDroppedStatua',
            'GenerateLegalReport',
            'GenerateReconsiderationReport',
            'GenerateCancellationReport',
            'GenerateDroppedReport',
            'WelcomeLetterReportRequest',
            'Lead Summary',
            'Contact Anaylsis',
            'Conversion Summary',
        ];

        foreach ($todoTitles as $index => $title) {
            $assignedTo = $index === 0 ? 2 : 4;

            Task::updateOrCreate(
                ['title' => $title, 'assigned_to' => $assignedTo],
                [
                    'description' => null,
                    'status' => 'todo',
                    'priority' => 'medium',
                    'due_at' => null,
                    'completed_at' => null,
                    'assigned_to' => $assignedTo,
                    'created_by' => $assignedTo,
                    'updated_by' => 5,
                ]
            );
        }

        $inProgressTitles = [
            'GenerateOfferAuthorizarinReport',
            'GenerateRetentionCommissionReport',
            'GeneratePacketReport',
            'Agent ROI',
            'Settlement Anaylsis',
            'Enrollment Model',
            'Agent Summary',
            'Sales Admin Report',
            'Settelment Admin Report',
            'Sales Manager Commision',
            'Sales Team Leader Commision',
        ];

        foreach ($inProgressTitles as $title) {
            Task::updateOrCreate(
                ['title' => $title, 'assigned_to' => 4],
                [
                    'description' => null,
                    'status' => 'in_progress',
                    'priority' => 'medium',
                    'due_at' => null,
                    'completed_at' => null,
                    'assigned_to' => 4,
                    'created_by' => 4,
                    'updated_by' => 5,
                ]
            );
        }

        $deployedTitles = [
            'GenerateEnrollmentFrequencyReport',
            'GenerateLDRPastDueReport',
        ];

        foreach ($deployedTitles as $title) {
            Task::updateOrCreate(
                ['title' => $title, 'assigned_to' => 4],
                [
                    'description' => null,
                    'status' => 'done',
                    'priority' => 'medium',
                    'due_at' => null,
                    'completed_at' => now(),
                    'assigned_to' => 4,
                    'created_by' => 4,
                    'updated_by' => 5,
                ]
            );
        }

        $automationDoneTitles = [
            'SyncSettlementData',
            'SyncSettledDebtData',
            'SyntEPFData',
            'SyncVerifiedDebts',
            'SyncEnrollmentStatus',
            'UpdateEPFRates',
        ];

        foreach ($automationDoneTitles as $title) {
            Task::updateOrCreate(
                ['title' => $title, 'assigned_to' => 2],
                [
                    'description' => null,
                    'status' => 'done',
                    'priority' => 'medium',
                    'due_at' => null,
                    'completed_at' => now(),
                    'assigned_to' => 2,
                    'created_by' => 2,
                    'updated_by' => 5,
                ]
            );
        }
    }
}
