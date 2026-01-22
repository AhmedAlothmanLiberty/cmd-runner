<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\User;
use App\Notifications\AutomationEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AutomationAdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function payloadForRequest(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sync Balance History',
            'slug' => 'sync-balance-history',
            'command' => 'reports:sync-balance-history',
            'cron_expression' => '* * * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'timeout_seconds' => 60,
            'run_via' => 'artisan',
            'notify_on_fail' => false,
            'schedule_mode' => 'daily',
            'daily_times' => ['00:00'],
            'daily_time' => '00:00',
        ], $overrides);
    }

    private function payloadForModel(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sync Balance History',
            'slug' => 'sync-balance-history',
            'command' => 'reports:sync-balance-history',
            'cron_expression' => '* * * * *',
            'timezone' => 'UTC',
            'is_active' => true,
            'timeout_seconds' => 60,
            'run_via' => 'artisan',
            'notify_on_fail' => false,
            'schedule_mode' => 'daily',
            'daily_time' => '00:00',
            'day_times' => [],
            'weekly_days' => [],
            'run_times' => ['00:00'],
            'schedule_frequencies' => [],
        ], $overrides);
    }

    public function test_admins_get_notified_when_automation_is_created(): void
    {
        Notification::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::findOrCreate('admin');
        $superAdminRole = Role::findOrCreate('super-admin');
        $automationRole = Role::findOrCreate('automation');

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole($superAdminRole);

        $actor = User::factory()->create();
        $actor->assignRole($automationRole);

        $this
            ->actingAs($actor)
            ->post(route('admin.automations.store'), $this->payloadForRequest())
            ->assertRedirect(route('admin.automations.index'));

        Notification::assertSentTo([$admin, $superAdmin], AutomationEventNotification::class, function (AutomationEventNotification $notification) use ($admin): bool {
            $data = $notification->toArray($admin);

            return ($data['type'] ?? null) === 'automation_created';
        });

        Notification::assertNotSentTo($actor, AutomationEventNotification::class);
    }

    public function test_admins_get_notified_when_automation_is_updated(): void
    {
        Notification::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::findOrCreate('admin');
        $superAdminRole = Role::findOrCreate('super-admin');
        $automationRole = Role::findOrCreate('automation');

        $admin = User::factory()->create();
        $admin->assignRole($adminRole);
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole($superAdminRole);

        $actor = User::factory()->create();
        $actor->assignRole($automationRole);

        $automation = Automation::create(array_merge($this->payloadForModel([
            'slug' => 'seeded-automation',
        ]), [
            'created_by' => 'system',
            'updated_by' => 'system',
        ]));

        $this
            ->actingAs($actor)
            ->put(route('admin.automations.update', $automation), $this->payloadForRequest([
                'name' => 'Sync Balance History (Updated)',
                'slug' => 'seeded-automation',
            ]))
            ->assertRedirect(route('admin.automations.index'));

        Notification::assertSentTo([$admin, $superAdmin], AutomationEventNotification::class, function (AutomationEventNotification $notification) use ($admin): bool {
            $data = $notification->toArray($admin);

            return ($data['type'] ?? null) === 'automation_updated';
        });
    }
}
