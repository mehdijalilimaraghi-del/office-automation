<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Audit;

use App\Core\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_service_records_actor_and_change_payload(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create(['name' => 'Before']);

        $this->actingAs($actor);

        app(\App\Core\Audit\Contracts\AuditServiceInterface::class)->record(
            'user.updated',
            $user,
            ['name' => 'Before'],
            ['name' => 'After'],
            ['source' => 'test'],
        );

        $audit = AuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertSame('user.updated', $audit->event);
        $this->assertSame(User::class, $audit->auditable_type);
        $this->assertSame($user->id, (int) $audit->auditable_id);
        $this->assertSame(['name' => 'Before'], $audit->old_values);
        $this->assertSame(['name' => 'After'], $audit->new_values);
        $this->assertSame(['source' => 'test'], $audit->metadata);
    }

    public function test_user_update_creates_business_audit_entry(): void
    {
        $actor = User::factory()->create();
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $this->actingAs($actor);

        $user->update([
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

        app(\App\Core\Audit\Contracts\AuditServiceInterface::class)->record(
            'user.updated',
            $user->refresh(),
            ['name' => 'Old Name', 'email' => 'old@example.com'],
            ['name' => 'New Name', 'email' => 'new@example.com'],
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'event' => 'user.updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }
}
