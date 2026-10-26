<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CorrespondenceCopyAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_copy_recipient_is_audited_once_and_duplicate_attachment_is_ignored(): void
    {
        $actor = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'واحد رونوشت '.Str::upper(Str::random(5)),
            'code' => 'CC-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه محرمانه برای آزمون رونوشت',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/009991',
            'created_by' => $actor->id,
        ]);

        $service = app(CorrespondenceWorkflowService::class);
        $service->addCopies($letter, [$department->id], $actor);
        $service->addCopies($letter, [$department->id], $actor);

        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'actor_id' => $actor->id,
            'action_type' => 'copy_added',
            'to_department_id' => $department->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'correspondence.action.copy_added',
            'auditable_type' => $letter->getMorphClass(),
            'auditable_id' => $letter->id,
        ]);
        $this->assertDatabaseCount('correspondence_actions', 1);
        $this->assertSame(1, DB::table('audit_logs')\n            ->where('event', 'correspondence.action.copy_added')\n            ->where('auditable_id', $letter->id)\n            ->count());
    }
}
