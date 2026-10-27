<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Settings\Services\SystemSettingsService;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Departments\Models\Department;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceNumberingService;
use InvalidArgumentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $registrar = $this->createSecretariatUser();
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
        $this->assertMatchesRegularExpression('/^\\d{4}\\/000001$/', $registered->registration_number);
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

    public function test_registration_requiring_approval_does_not_consume_a_number_without_completed_workflow(): void
    {
        $creator = $this->createSecretariatUser();
        $correspondence = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه صادره نیازمند تأیید',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $creator->id,
        ]);

        try {
            app(CorrespondenceNumberingService::class)->register($correspondence, $creator);
            $this->fail('Registration should require a completed approval workflow.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('این نامه باید پیش از ثبت نهایی توسط مدیر واحد تأیید شود.', $exception->getMessage());
        }

        $this->assertDatabaseHas('correspondence_number_counters', ['id' => 1, 'last_number' => 0]);
        $this->assertDatabaseHas('correspondences', [
            'id' => $correspondence->id,
            'status' => Correspondence::STATUS_DRAFT,
            'registration_number' => null,
        ]);
    }

    public function test_registration_number_uses_its_own_configurable_template(): void
    {
        $creator=User::factory()->create(['status'=>'active']); $registrar=$this->createSecretariatUser();
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

    private function createSecretariatUser(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create(['name' => 'دبیرخانه تست', 'code' => 'SEC-'.strtoupper(bin2hex(random_bytes(3))), 'is_active' => true, 'manager_id' => $user->id]);
        app(SystemSettingsService::class)->set([OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id]);
        return $user;
    }
}
