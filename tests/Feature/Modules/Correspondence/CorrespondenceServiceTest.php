<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Departments\Models\Department;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Settings\Services\SystemSettingsService;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CorrespondenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_draft_persists_incoming_correspondence_with_creator_and_no_registration_number(): void
    {
        $creator = $this->createSecretariatUser();

        $correspondence = app(CorrespondenceService::class)->createDraft([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => '  درخواست رسمی  ',
            'body' => 'متن نامه',
            'sender_name' => 'سازمان فرستنده',
        ], $creator);

        $this->assertDatabaseHas('correspondences', [
            'id' => $correspondence->id,
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'درخواست رسمی',
            'status' => Correspondence::STATUS_DRAFT,
            'registration_number' => null,
            'created_by' => $creator->id,
        ]);
        $this->assertSame((int) $creator->id, (int) $correspondence->creator->id);
        $this->assertNull($correspondence->registered_at);
    }

    public function test_create_draft_supports_outgoing_and_internal_correspondence_types(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $service = app(CorrespondenceService::class);

        foreach ([Correspondence::TYPE_OUTGOING, Correspondence::TYPE_INTERNAL] as $type) {
            $correspondence = $service->createDraft([
                'type' => $type,
                'subject' => 'مکاتبه آزمایشی '.$type,
            ], $creator);

            $this->assertSame($type, $correspondence->type);
            $this->assertSame(Correspondence::STATUS_DRAFT, $correspondence->status);
        }
    }

    public function test_internal_draft_keeps_creator_and_recipient_departments_separate(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $creatorDepartment = Department::query()->create([
            'name' => 'واحد ایجادکننده',
            'code' => 'SRC-'.strtoupper(bin2hex(random_bytes(3))),
            'is_active' => true,
        ]);
        $recipientDepartment = Department::query()->create([
            'name' => 'واحد گیرنده',
            'code' => 'DST-'.strtoupper(bin2hex(random_bytes(3))),
            'is_active' => true,
        ]);
        $creator->departments()->attach($creatorDepartment->id, [
            'is_primary' => true,
            'joined_at' => now(),
        ]);

        $correspondence = app(CorrespondenceService::class)->createDraft([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه بین دو واحد',
            'recipient_department_id' => $recipientDepartment->id,
        ], $creator);

        $this->assertDatabaseHas('correspondences', [
            'id' => $correspondence->id,
            'created_department_id' => $creatorDepartment->id,
            'recipient_department_id' => $recipientDepartment->id,
            'sender_name' => $creator->name,
        ]);
    }

    public function test_create_draft_rejects_unknown_type(): void
    {
        $creator = User::factory()->create(['status' => 'active']);

        $this->expectException(InvalidArgumentException::class);

        app(CorrespondenceService::class)->createDraft([
            'type' => 'unknown',
            'subject' => 'موضوع',
        ], $creator);
    }

    public function test_create_draft_rejects_empty_subject(): void
    {
        $creator = User::factory()->create(['status' => 'active']);

        $this->expectException(InvalidArgumentException::class);

        app(CorrespondenceService::class)->createDraft([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => '   ',
        ], $creator);
    }
    private function createSecretariatUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create(['name' => 'دبیرخانه تست', 'code' => 'SEC-'.strtoupper(bin2hex(random_bytes(3))), 'is_active' => true, 'manager_id' => $user->id]);
        app(SystemSettingsService::class)->set([OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id]);
        return $user;
    }
}
