<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Settings\Services\SystemSettingsService;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Departments\Models\Department;
use App\Core\ExternalParties\Models\ExternalContact;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceNumberingService;
use InvalidArgumentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CorrespondenceNumberingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbering_supports_the_three_presets_and_increments_a_shared_sequence(): void
    {
        $correspondence = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه آزمایشی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => User::factory()->create(['status' => 'active'])->id,
        ]);
        $settings = app(SystemSettingsService::class);
        $service = app(CorrespondenceNumberingService::class);

        $settings->set(['correspondence.numbering.format' => 'simple']);
        $this->assertSame('000001', $service->nextNumber($correspondence));

        $settings->set([
            'correspondence.numbering.format' => 'custom',
            'correspondence.numbering.custom_order' => 'year,sequence',
            'correspondence.numbering.separator' => '-',
        ]);
        $this->assertMatchesRegularExpression('/^\\d{4}-000002$/', $service->nextNumber($correspondence));

        $settings->set(['correspondence.numbering.format' => 'year']);
        $this->assertStringEndsWith('-000003', $service->nextNumber($correspondence));

        $settings->set(['correspondence.numbering.format' => 'year_department']);
        $this->assertStringContainsString('-ORG-000004', $service->nextNumber($correspondence));
    }

    public function test_department_numbering_uses_the_creator_department_not_the_recipient_department(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $createdDepartment = Department::query()->create([
            'name' => 'واحد ایجادکننده',
            'code' => 'CRT'.strtoupper(bin2hex(random_bytes(2))),
            'is_active' => true,
        ]);
        $recipientDepartment = Department::query()->create([
            'name' => 'واحد گیرنده',
            'code' => 'RCV'.strtoupper(bin2hex(random_bytes(2))),
            'is_active' => true,
        ]);
        $correspondence = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'شماره‌گذاری با واحد ایجادکننده',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $creator->id,
            'created_department_id' => $createdDepartment->id,
            'recipient_department_id' => $recipientDepartment->id,
        ]);
        app(SystemSettingsService::class)->set(['correspondence.numbering.format' => 'year_department']);

        $number = app(CorrespondenceNumberingService::class)->nextNumber($correspondence);

        $this->assertStringContainsString('/'.$createdDepartment->code.'/000001', $number);
        $this->assertStringNotContainsString($recipientDepartment->code, $number);
    }

    public function test_custom_numbering_order_is_applied(): void
    {
        $correspondence = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه داخلی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => User::factory()->create(['status' => 'active'])->id,
        ]);
        app(SystemSettingsService::class)->set([
            'correspondence.numbering.format' => 'custom',
            'correspondence.numbering.custom_order' => 'sequence,year,department',
            'correspondence.numbering.separator' => '-',
        ]);

        $number = app(CorrespondenceNumberingService::class)->nextNumber($correspondence);

        $this->assertMatchesRegularExpression('/^000001-\d{4}-ORG$/', $number);
    }
    public function test_register_assigns_a_unique_number_and_freezes_the_correspondence(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $registrar = $creator;
        app(SystemSettingsService::class)->set([
            'correspondence.numbering.format' => 'simple',
            'correspondence.registration_numbering.format' => 'simple',
        ]);
        $correspondence = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'ثبت نامه داخلی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $creator->id,
        ]);

        $registered = app(CorrespondenceNumberingService::class)->register($correspondence, $registrar);

        $this->assertSame(Correspondence::STATUS_REGISTERED, $registered->status);
        $this->assertNotEmpty($registered->letter_number);
        $this->assertNotEmpty($registered->registration_number);
        $this->assertNotSame($registered->letter_number, $registered->registration_number);
        $this->assertMatchesRegularExpression('/^000001$/', $registered->registration_number);
        $this->assertSame((int) $registrar->id, (int) $registered->registered_by);
        $this->assertNotNull($registered->registered_at);
        $this->assertDatabaseHas('correspondences', [
            'id' => $correspondence->id,
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => $registered->registration_number,
        ]);

        try {
            app(CorrespondenceNumberingService::class)->register($registered, $registrar);
            $this->fail('A registered correspondence must not be registered again.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('فقط پیش‌نویس را می‌توان ثبت نهایی کرد.', $exception->getMessage());
        }
    }

    public function test_secretariat_manager_can_register_draft_without_intermediate_approval(): void
    {
        $secretariatManager = $this->createSecretariatUser();
        $correspondence = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه صادره آماده ثبت نهایی',
            'status' => Correspondence::STATUS_DRAFT,
            'workflow_state' => 'awaiting_registration',
            'created_by' => User::factory()->create(['status' => 'active'])->id,
        ]);

        $registered = app(CorrespondenceNumberingService::class)->register($correspondence, $secretariatManager);

        $this->assertSame(Correspondence::STATUS_REGISTERED, $registered->status);
        $this->assertSame((int) $secretariatManager->id, (int) $registered->registered_by);
        $this->assertNotEmpty($registered->registration_number);
        $this->assertDatabaseHas('correspondence_numbering_scoped_counters', ['counter_type' => 'registration', 'last_number' => 1]);
        $this->assertDatabaseHas('correspondences', [
            'id' => $correspondence->id,
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'awaiting_registration',
            'registration_number' => $registered->registration_number,
        ]);
    }

    public function test_registration_number_uses_its_own_configurable_template(): void
    {
        $creator=User::factory()->create(['status'=>'active']); $registrar=$creator;
        $letter=Correspondence::query()->create(['type'=>Correspondence::TYPE_INTERNAL,'subject'=>'قالب مستقل','status'=>Correspondence::STATUS_DRAFT,'letter_number'=>'INT-77','created_by'=>$creator->id]);
        app(SystemSettingsService::class)->set(['correspondence.registration_numbering.format'=>'custom','correspondence.registration_numbering.custom_order'=>'sequence,year','correspondence.registration_numbering.separator'=>'-','correspondence.registration_numbering.padding'=>'4']);
        $registered=app(CorrespondenceNumberingService::class)->register($letter,$registrar);
        $this->assertMatchesRegularExpression('/^0001-\\d{4}$/',$registered->registration_number);
        $this->assertSame('INT-77',$registered->letter_number);
    }

    public function test_incoming_registration_does_not_generate_missing_original_letter_number(): void
    {
        $registrar=$this->createSecretariatUser();
        $letter=Correspondence::query()->create(['type'=>Correspondence::TYPE_INCOMING,'subject'=>'وارده بدون شماره اصلی','status'=>Correspondence::STATUS_DRAFT,'created_by'=>$registrar->id]);
        try { app(CorrespondenceNumberingService::class)->register($letter,$registrar); $this->fail('Incoming letters must require original number.'); }
        catch (InvalidArgumentException $exception) { $this->assertSame('شماره نامه وارده باید عیناً از روی نامه اصلی وارد شود.',$exception->getMessage()); }
        $this->assertDatabaseHas('correspondences',['id'=>$letter->id,'letter_number'=>null,'registration_number'=>null]);
    }

    public function test_finalizing_outgoing_letter_snapshots_creator_unit_stamp_and_manager_signature(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('organization/stamp/stamp.png', 'stamp-image');
        Storage::disk('local')->put('organization/letterhead/header.png', 'letterhead-image');
        Storage::disk('local')->put('organization/signatures/finance.png', 'signature-image');

        $creator = User::factory()->create(['status' => 'active', 'name' => 'مهدی جلیلی']);
        $department = Department::query()->create([
            'name' => 'واحد مالی',
            'code' => 'FIN-'.strtoupper(bin2hex(random_bytes(2))),
            'manager_id' => User::factory()->create(['status' => 'active'])->id,
            'manager_signature_path' => 'organization/signatures/finance.png',
            'manager_signature_active' => true,
            'is_active' => true,
        ]);
        $registrar = $this->createSecretariatUser();
        app(SystemSettingsService::class)->set([
            'organization.name' => 'سازمان نمونه',
            'organization.address' => 'تهران',
            'organization.stamp.path' => 'organization/stamp/stamp.png',
            'organization.stamp.active' => '1',
            'organization.letterhead.path' => 'organization/letterhead/header.png',
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه دارای مشخصات صادرکننده',
            'status' => Correspondence::STATUS_DRAFT,
            'workflow_state' => 'approved_for_registration',
            'created_by' => $creator->id,
            'created_department_id' => $department->id,
        ]);

        $registered = app(CorrespondenceNumberingService::class)->register($letter, $registrar);
        $snapshot = $registered->document_identity_snapshot;

        $this->assertSame('مهدی جلیلی', $snapshot['creator_name']);
        $this->assertSame('مدیریت واحد مالی', $snapshot['creator_unit_title']);
        $this->assertSame('سازمان نمونه', $snapshot['organization']['name']);
        $this->assertNotEmpty($snapshot['stamp_path']);
        $this->assertNotEmpty($snapshot['letterhead_path']);
        $this->assertNotEmpty($snapshot['signature_path']);
        Storage::disk('local')->assertExists($snapshot['stamp_path']);
        Storage::disk('local')->assertExists($snapshot['letterhead_path']);
        Storage::disk('local')->assertExists($snapshot['signature_path']);
    }

    public function test_outgoing_recipient_snapshot_uses_external_unit_addressing_and_manager(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $organization = ExternalOrganization::query()->create([
            'name' => 'سازمان مقصد',
            'status' => 'active',
        ]);
        $unit = ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'مدیریت مالی',
            'organizational_title_prefix' => 'مدیریت',
            'position' => 'مدیر واحد مالی',
            'personal_salutation' => 'سرکار خانم',
            'professional_title' => 'دکتر',
            'status' => 'active',
        ]);
        $manager = ExternalContact::query()->create([
            'external_organization_id' => $organization->id,
            'external_department_id' => $unit->id,
            'name' => 'زهرا احمدی',
            'status' => 'active',
        ]);
        $unit->update(['manager_contact_id' => $manager->id]);
        $registrar = $this->createSecretariatUser();

        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه به واحد بیرونی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $creator->id,
            'external_organization_id' => $organization->id,
            'external_department_id' => $unit->id,
            'external_contact_id' => null,
        ]);

        $registered = app(CorrespondenceNumberingService::class)->register($letter, $registrar);
        $snapshot = $registered->recipient_addressing_snapshot;

        $this->assertSame('مدیریت', $snapshot['organizational_title_prefix']);
        $this->assertSame('مدیر واحد مالی', $snapshot['position']);
        $this->assertSame('سرکار خانم', $snapshot['personal_salutation']);
        $this->assertSame('دکتر', $snapshot['professional_title']);
        $this->assertSame('زهرا احمدی', $snapshot['name']);
        $this->assertSame($manager->id, $snapshot['manager_contact_id']);
        $this->assertSame('سازمان مقصد', $snapshot['organization_name']);
        $this->assertSame('مدیریت مالی', $snapshot['department_name']);
    }

    private function createSecretariatUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create(['name' => 'دبیرخانه تست', 'code' => 'SEC-'.strtoupper(bin2hex(random_bytes(3))), 'is_active' => true, 'manager_id' => $user->id]);
        app(SystemSettingsService::class)->set([OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id]);
        return $user;
    }

    public function test_template_counter_resets_only_for_values_present_in_template(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $firstDepartment = Department::query()->create(['name' => 'واحد الف', 'code' => 'UNIT-A', 'is_active' => true]);
        $secondDepartment = Department::query()->create(['name' => 'واحد ب', 'code' => 'UNIT-B', 'is_active' => true]);
        $first = Correspondence::query()->create(['type' => Correspondence::TYPE_OUTGOING, 'subject' => 'الف', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => $creator->id, 'created_department_id' => $firstDepartment->id]);
        $second = Correspondence::query()->create(['type' => Correspondence::TYPE_OUTGOING, 'subject' => 'ب', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => $creator->id, 'created_department_id' => $secondDepartment->id]);
        app(SystemSettingsService::class)->set([
            'correspondence.numbering.format' => 'template',
            'correspondence.numbering.template' => '{{Dept}}/{{Num-3-r}}',
        ]);
        $service = app(CorrespondenceNumberingService::class);

        $this->assertSame('UNIT-A/001', $service->nextNumber($first));
        $this->assertSame('UNIT-A/002', $service->nextNumber($first));
        $this->assertSame('UNIT-B/001', $service->nextNumber($second));
    }

    public function test_continuous_template_counter_does_not_reset_when_other_template_values_change(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $firstDepartment = Department::query()->create(['name' => 'واحد الف', 'code' => 'UNIT-C', 'is_active' => true]);
        $secondDepartment = Department::query()->create(['name' => 'واحد ب', 'code' => 'UNIT-D', 'is_active' => true]);
        $first = Correspondence::query()->create(['type' => Correspondence::TYPE_OUTGOING, 'subject' => 'الف', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => $creator->id, 'created_department_id' => $firstDepartment->id]);
        $second = Correspondence::query()->create(['type' => Correspondence::TYPE_OUTGOING, 'subject' => 'ب', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => $creator->id, 'created_department_id' => $secondDepartment->id]);
        app(SystemSettingsService::class)->set([
            'correspondence.numbering.format' => 'template',
            'correspondence.numbering.template' => '{{Dept}}/{{Num-3-c}}',
        ]);
        $service = app(CorrespondenceNumberingService::class);

        $this->assertSame('UNIT-C/001', $service->nextNumber($first));
        $this->assertSame('UNIT-D/002', $service->nextNumber($second));
    }


}
