<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Correspondence;

use App\Core\Permissions\Models\Permission;
use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Roles\Models\Role;
use App\Core\Departments\Models\Department;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\Settings\Services\OrganizationDepartmentRoleService;
use App\Core\Settings\Services\SystemSettingsService;
use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Models\CorrespondenceAction;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;
use App\Modules\Correspondence\Services\CorrespondenceWorkflowService;
use App\Modules\Workflows\Models\WorkflowDefinition;
use App\Modules\Workflows\Models\WorkflowStep;
use App\Modules\Workflows\Services\WorkflowService;
use App\Modules\Workflows\Models\WorkflowInstanceStep;
use App\Modules\Workflows\Models\WorkflowInstance;
use App\Modules\Tasks\Models\Task;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class CorrespondenceHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_internal_correspondence_is_finalized_when_created(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $department = Department::query()->create([
            'name' => 'واحد ثبت مستقیم',
            'code' => 'DIR-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $creator->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);

        $this->actingAs($creator)->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INTERNAL,
            'created_department_id' => $department->id,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه داخلی با ثبت مستقیم',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
            'recipient_department_id' => $department->id,
        ])->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseHas('correspondences', [
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه داخلی با ثبت مستقیم',
            'status' => Correspondence::STATUS_REGISTERED,
            'registered_by' => $creator->id,
            'workflow_state' => 'in_progress',
        ]);
    }

    public function test_outgoing_draft_awaiting_manager_approval_appears_in_manager_needs_action_filter(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $manager = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد صادرکننده در انتظار تأیید',
            'code' => 'MGR-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $creator->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        app(SystemSettingsService::class)->set(['correspondence.approval.outgoing' => 'approval']);

        $letter = app(\App\Modules\Correspondence\Services\CorrespondenceService::class)->createDraft([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه صادره در انتظار تأیید مدیر',
            'created_department_id' => $department->id,
        ], $creator);

        $this->actingAs($manager)->get(route('correspondence.index', [
            'scope' => 'mine',
            'action_filter' => 'needs_action',
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('correspondences.total', 1)
            ->where('correspondences.data.0.id', $letter->id));
    }

    public function test_user_with_view_permission_can_open_correspondence_index(): void
    {
        $user = $this->userWithPermission('correspondence.view');
        Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه آزمایشی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('correspondence.index', ['action_filter' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Correspondence/Index')
                ->where('correspondences.total', 1)
                ->where('correspondences.data.0.subject', 'نامه آزمایشی'));
    }

    public function test_correspondence_index_supports_mine_department_and_all_scopes(): void
    {
        $user = $this->userWithPermission('correspondence.view');
        $otherUser = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد فیلتر مکاتبات '.Str::upper(Str::random(5)),
            'code' => 'FLT-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $user->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);

        Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'مکاتبه شخصی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);
        Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'مکاتبه واحد',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/009990',
            'created_by' => $otherUser->id,
            'created_department_id' => $department->id,
            'current_department_id' => $department->id,
        ]);
        Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'مکاتبه عمومی ثبت‌شده',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/009991',
            'created_by' => $otherUser->id,
            'created_department_id' => $department->id,
        ]);

        $this->actingAs($user)->get(route('correspondence.index', ['action_filter' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.scope', 'mine')
                ->where('correspondences.total', 1));

        $this->actingAs($user)->get(route('correspondence.index', ['scope' => 'department', 'action_filter' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.scope', 'department')
                ->where('correspondences.total', 2));

        $this->actingAs($user)->get(route('correspondence.index', ['scope' => 'all', 'action_filter' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.scope', 'all')
                ->where('correspondences.total', 3));
    }

    public function test_correspondence_action_filter_distinguishes_pending_and_previously_acted_referrals(): void
    {
        $user = $this->userWithPermission('correspondence.view');
        $referrer = $this->userWithPermission('correspondence.view');
        $otherUser = $this->userWithPermission('correspondence.view');
        $creator = $this->userWithPermission('correspondence.view');

        $pending = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'ارجاع مستقیم بدون اقدام',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'assigned_to_employee',
            'current_user_id' => $user->id,
            'created_by' => $creator->id,
        ]);
        CorrespondenceAction::query()->create([
            'correspondence_id' => $pending->id,
            'actor_id' => $referrer->id,
            'action_type' => CorrespondenceAction::MANAGER_REFER,
            'to_user_id' => $user->id,
        ]);

        $acted = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'ارجاع‌شده و اقدام‌شده توسط من',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_user_id' => $otherUser->id,
            'created_by' => $creator->id,
        ]);
        CorrespondenceAction::query()->create([
            'correspondence_id' => $acted->id,
            'actor_id' => $referrer->id,
            'action_type' => CorrespondenceAction::MANAGER_REFER,
            'to_user_id' => $user->id,
        ]);
        CorrespondenceAction::query()->create([
            'correspondence_id' => $acted->id,
            'actor_id' => $user->id,
            'action_type' => CorrespondenceAction::MANAGER_REFER,
            'to_user_id' => $otherUser->id,
        ]);

        $actedWhileStillAssigned = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'ارجاع‌شده و اقدام‌شده با وجود باقی‌ماندن مسئولیت',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_user_id' => $user->id,
            'created_by' => $creator->id,
        ]);
        CorrespondenceAction::query()->create([
            'correspondence_id' => $actedWhileStillAssigned->id,
            'actor_id' => $referrer->id,
            'action_type' => CorrespondenceAction::MANAGER_REFER,
            'to_user_id' => $user->id,
        ]);
        CorrespondenceAction::query()->create([
            'correspondence_id' => $actedWhileStillAssigned->id,
            'actor_id' => $user->id,
            'action_type' => CorrespondenceAction::REQUEST_TASK,
        ]);

        $referredByUser = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'ارجاع‌شده توسط من به شخص دیگر',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_user_id' => $otherUser->id,
            'created_by' => $creator->id,
        ]);
        CorrespondenceAction::query()->create([
            'correspondence_id' => $referredByUser->id,
            'actor_id' => $user->id,
            'action_type' => CorrespondenceAction::MANAGER_REFER,
            'to_user_id' => $otherUser->id,
        ]);

        $createdByMe = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'مکاتبه ایجادشده توسط من',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);
        $registeredByMe = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'مکاتبه ثبت نهایی‌شده توسط من',
            'status' => Correspondence::STATUS_REGISTERED,
            'registered_by' => $user->id,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($user)->get(route('correspondence.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.action_filter', 'needs_action')
                ->where('correspondences.total', 1)
                ->where('correspondences.data.0.id', $pending->id));

        $this->actingAs($user)->get(route('correspondence.index', ['action_filter' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.action_filter', 'all')
                ->where('correspondences.total', 5));

        $this->actingAs($user)->get(route('correspondence.index', ['action_filter' => 'needs_action']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.action_filter', 'needs_action')
                ->where('correspondences.total', 1)
                ->where('correspondences.data.0.id', $pending->id));

        $this->actingAs($user)->get(route('correspondence.index', ['action_filter' => 'acted_by_me']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.action_filter', 'acted_by_me')
                ->where('correspondences.total', 2));

        $this->actingAs($user)->get(route('correspondence.index', ['action_filter' => 'created_by_me']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.action_filter', 'created_by_me')
                ->where('correspondences.total', 2)
                ->where('correspondences.data.0.id', $registeredByMe->id));
    }

    public function test_needs_action_uses_current_department_manager_even_without_referral_history(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $creator = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد ارجاع مستقیم مدیر '.Str::upper(Str::random(5)),
            'code' => 'MGR-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);

        $awaitingManager = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'ارجاع به واحد مدیر بدون تاریخچه',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $department->id,
            'current_user_id' => null,
            'created_by' => $creator->id,
        ]);
        Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'ارجاع‌شده به کارمند و خارج از اقدام مدیر',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'assigned_to_employee',
            'current_department_id' => $department->id,
            'current_user_id' => $employee->id,
            'created_by' => $creator->id,
        ]);
        $resultPendingManager = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'نتیجه کارمند در انتظار مدیر',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'result_pending_manager',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($manager)->get(route('correspondence.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.action_filter', 'needs_action')
                ->where('correspondences.total', 2)
                ->where('correspondences.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === collect([$resultPendingManager->id, $awaitingManager->id])->sort()->values()->all()));
    }

    public function test_approved_project_request_is_actionable_for_current_department_manager(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $creator = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'مدیریت پروژه آزمون '.Str::upper(Str::random(5)),
            'code' => 'PRJ-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);

        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'درخواست پروژه تأییدشده و منتظر ایجاد پروژه',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'project_request_approved',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($manager)->get(route('correspondence.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.action_filter', 'needs_action')
                ->where('correspondences.total', 1)
                ->where('correspondences.data.0.id', $letter->id));
    }

    public function test_department_filter_uses_department_module_data_and_never_bypasses_visibility(): void
    {
        $user = $this->userWithPermission('correspondence.view');
        $creator = $this->userWithPermission('correspondence.view');
        $memberDepartment = Department::query()->create([
            'name' => 'واحد عضو فیلتر '.Str::upper(Str::random(5)),
            'code' => 'DM-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $otherDepartment = Department::query()->create([
            'name' => 'واحد دیگر فیلتر '.Str::upper(Str::random(5)),
            'code' => 'DO-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $user->departments()->attach($memberDepartment->id, ['is_primary' => true, 'joined_at' => now()]);

        $memberLetter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه واحد عضو',
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/DM-001',
            'created_by' => $creator->id,
            'current_department_id' => $memberDepartment->id,
        ]);
        $otherOrdinaryLetter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه عادی واحد دیگر',
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/DO-001',
            'created_by' => $creator->id,
            'current_department_id' => $otherDepartment->id,
        ]);
        Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه محرمانه واحد دیگر',
            'classification' => Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/DO-002',
            'created_by' => $creator->id,
            'current_department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($user)->get(route('correspondence.index', [
            'scope' => 'department',
            'department_filter' => 'mine',
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.department_filter', 'mine')
            ->where('departments.0.id', $memberDepartment->id)
            ->where('correspondences.total', 1)
            ->where('correspondences.data.0.id', $memberLetter->id));

        $this->actingAs($user)->get(route('correspondence.index', [
            'scope' => 'all',
            'department_filter' => (string) $otherDepartment->id,
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('filters.department_filter', (string) $otherDepartment->id)
            ->where('departments', fn ($departments) => collect($departments)->pluck('id')->contains($memberDepartment->id) && collect($departments)->pluck('id')->contains($otherDepartment->id))
            ->where('correspondences.total', 1)
            ->where('correspondences.data.0.id', $otherOrdinaryLetter->id));
    }

    public function test_needs_action_includes_department_routing_for_managers_of_multiple_departments(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $creator = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');

        $firstDepartment = Department::query()->create([
            'name' => 'واحد اول مدیر چندواحدی '.Str::upper(Str::random(5)),
            'code' => 'M1-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $secondDepartment = Department::query()->create([
            'name' => 'واحد دوم مدیر چندواحدی '.Str::upper(Str::random(5)),
            'code' => 'M2-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);

        $departmentReferral = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'ارجاع به واحد تحت مدیریت دوم',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $secondDepartment->id,
            'current_user_id' => null,
            'created_by' => $creator->id,
        ]);
        CorrespondenceAction::query()->create([
            'correspondence_id' => $departmentReferral->id,
            'actor_id' => $creator->id,
            'action_type' => CorrespondenceAction::MANAGER_REFER,
            'to_department_id' => $secondDepartment->id,
            'to_user_id' => null,
        ]);

        $assignedToEmployee = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'ارجاع‌شده به کارمند واحد دوم',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'assigned_to_employee',
            'current_department_id' => $secondDepartment->id,
            'current_user_id' => $employee->id,
            'created_by' => $creator->id,
        ]);
        CorrespondenceAction::query()->create([
            'correspondence_id' => $assignedToEmployee->id,
            'actor_id' => $manager->id,
            'action_type' => CorrespondenceAction::MANAGER_REFER,
            'to_department_id' => $secondDepartment->id,
            'to_user_id' => $employee->id,
        ]);

        $this->actingAs($manager)->get(route('correspondence.index', ['scope' => 'department', 'action_filter' => 'needs_action']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.scope', 'department')
                ->where('filters.action_filter', 'all')
                ->where('correspondences.total', 2));

        $this->actingAs($manager)->get(route('correspondence.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.action_filter', 'needs_action')
                ->where('correspondences.total', 1)
                ->where('correspondences.data.0.id', $departmentReferral->id));
    }

    public function test_user_with_view_permission_can_open_correspondence_details(): void
    {
        $user = $this->userWithPermission('correspondence.view');
        $correspondence = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'جزئیات نامه آزمایشی',
            'body' => '<p>متن نامه</p>',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('correspondence.show', $correspondence))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Correspondence/Show')
                ->where('correspondence.subject', 'جزئیات نامه آزمایشی')
                ->where('correspondence.body', '<p>متن نامه</p>')
                ->where('attachments', []));
    }

    public function test_registered_ordinary_letter_is_visible_to_any_viewer(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $viewer = $this->userWithPermission('correspondence.view');
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه عادی ثبت‌شده',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/000001',
            'created_by' => $creator->id,
        ]);

        $this->actingAs($viewer)->get(route('correspondence.index', ['scope' => 'all', 'action_filter' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('correspondences.total', 1));
        $this->actingAs($viewer)->get(route('correspondence.show', $letter))->assertOk();
    }

    public function test_drafts_are_visible_only_to_the_creator_and_secretariat(): void
    {
        $creator = $this->userWithPermission('correspondence.view');
        $otherUser = $this->userWithPermission('correspondence.view');
        $draft = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'پیش‌نویس خصوصی',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($otherUser)->get(route('correspondence.index', ['scope' => 'all', 'action_filter' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('correspondences.total', 0));
        $this->actingAs($otherUser)->get(route('correspondence.show', $draft))->assertForbidden();

        $this->actingAs($creator)->get(route('correspondence.index', ['scope' => 'all', 'action_filter' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('correspondences.total', 1));
        $this->actingAs($creator)->get(route('correspondence.show', $draft))->assertOk();
    }

    public function test_unrelated_viewer_cannot_open_confidential_letter(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $viewer = $this->userWithPermission('correspondence.view');
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'subject' => 'نامه محرمانه',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/000002',
            'created_by' => $creator->id,
        ]);

        $this->actingAs($viewer)->get(route('correspondence.index', ['scope' => 'all', 'action_filter' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('correspondences.total', 0));
        $this->actingAs($viewer)->get(route('correspondence.show', $letter))->assertForbidden();
    }

    public function test_related_viewer_can_open_confidential_letter(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $viewer = $this->userWithPermission('correspondence.view');
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'classification' => Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'subject' => 'نامه محرمانه مرتبط',
            'status' => Correspondence::STATUS_REGISTERED,
            'created_by' => $creator->id,
        ]);
        $letter->authorizedUsers()->attach($viewer->id, [
            'reason' => 'participant',
            'granted_by' => $creator->id,
        ]);

        $this->actingAs($viewer)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('correspondence.subject', 'نامه محرمانه مرتبط'));
    }

    public function test_current_department_manager_can_add_multiple_copy_recipients_during_workflow(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $currentDepartment = Department::query()->create([
            'name' => 'واحد مسئول رونوشت '.Str::upper(Str::random(5)),
            'code' => 'CUR-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $firstCopyDepartment = Department::query()->create([
            'name' => 'واحد رونوشت اول '.Str::upper(Str::random(5)),
            'code' => 'CP1-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $secondCopyDepartment = Department::query()->create([
            'name' => 'واحد رونوشت دوم '.Str::upper(Str::random(5)),
            'code' => 'CP2-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'افزودن رونوشت حین گردش',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/009992',
            'created_by' => $manager->id,
            'current_department_id' => $currentDepartment->id,
            'workflow_state' => 'in_progress',
        ]);

        $this->actingAs($manager)
            ->post(route('correspondence.copies.store', $letter), [
                'department_ids' => [$firstCopyDepartment->id, $secondCopyDepartment->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('correspondence_copies', [
            'correspondence_id' => $letter->id,
            'department_id' => $firstCopyDepartment->id,
            'created_by' => $manager->id,
        ]);
        $this->assertDatabaseHas('correspondence_copies', [
            'correspondence_id' => $letter->id,
            'department_id' => $secondCopyDepartment->id,
            'created_by' => $manager->id,
        ]);
        $this->assertDatabaseCount('correspondence_actions', 2);
    }

    public function test_user_who_is_not_current_department_manager_cannot_add_workflow_copies(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $otherUser = $this->userWithPermission('correspondence.view');
        $currentDepartment = Department::query()->create([
            'name' => 'واحد مدیر نامه '.Str::upper(Str::random(5)),
            'code' => 'OWN-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $copyDepartment = Department::query()->create([
            'name' => 'واحد مقصد رونوشت '.Str::upper(Str::random(5)),
            'code' => 'DST-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'رونوشت غیرمجاز',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/009993',
            'created_by' => $manager->id,
            'current_department_id' => $currentDepartment->id,
            'workflow_state' => 'in_progress',
        ]);

        $this->actingAs($otherUser)
            ->post(route('correspondence.copies.store', $letter), [
                'department_ids' => [$copyDepartment->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('correspondence_copies', [
            'correspondence_id' => $letter->id,
            'department_id' => $copyDepartment->id,
        ]);
    }

    public function test_incoming_and_outgoing_letters_require_their_respective_dates(): void
    {
        $user = $this->userWithPermission('correspondence.create');

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه صادره بدون تاریخ',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('sent_at');

        $secretariatUser = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'دبیرخانه تاریخ '.Str::upper(Str::random(5)),
            'code' => 'DAT-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $secretariatUser->id,
        ]);
        app(SystemSettingsService::class)->set([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id,
        ]);

        $this->actingAs($secretariatUser)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INCOMING,
            'recipient_department_id' => $this->recipientDepartmentId(),
            'subject' => 'نامه وارده بدون تاریخ',
            'original_letters' => [UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('received_at');

        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه صادره بدون تاریخ']);
        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه وارده بدون تاریخ']);

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه داخلی بدون واحد دریافت‌کننده',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('recipient_department_id');

        $this->actingAs($secretariatUser)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INCOMING,
            'received_at' => '2026-10-27',
            'subject' => 'نامه وارده بدون واحد دریافت‌کننده',
            'original_letters' => [UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('recipient_department_id');

        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه داخلی بدون واحد دریافت‌کننده']);
        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه وارده بدون واحد دریافت‌کننده']);
    }

    public function test_related_correspondence_search_returns_numbers_dates_and_party_names(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $department = Department::query()->create([
            'name' => 'واحد جستجوی نامه',
            'code' => 'SRCH-'.Str::upper(Str::random(5)),
            'is_active' => true,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'موضوع قابل جستجو',
            'status' => Correspondence::STATUS_REGISTERED,
            'letter_number' => '1405-000777',
            'registration_number' => 'REG-000777',
            'sent_at' => '2026-10-24',
            'registered_at' => now(),
            'created_by' => $user->id,
            'created_department_id' => $department->id,
        ]);

        $this->actingAs($user)->getJson(route('correspondence.related-search', ['q' => '000777']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $letter->id)
            ->assertJsonPath('data.0.letter_number', '1405-000777')
            ->assertJsonPath('data.0.registration_number', 'REG-000777')
            ->assertJsonPath('data.0.subject', 'موضوع قابل جستجو')
            ->assertJsonPath('data.0.created_department', 'واحد جستجوی نامه')
            ->assertJsonStructure(['data' => [['letter_date', 'external_registration_number']]]);
    }

    public function test_user_with_create_permission_can_create_editor_draft_and_unsafe_markup_is_removed(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $recipientDepartmentId = $this->recipientDepartmentId();

        $this->actingAs($user)->post(route('correspondence.store'), [
            'type' => 'internal',
            'recipient_department_id' => $recipientDepartmentId,
            'subject' => '  نامه داخلی  ',
            'composition_mode' => 'editor',
            'body' => '<p>متن <strong>مهم</strong><img src=x onerror=alert(1)></p>',
            'sender_name' => 'فرستنده',
        ])->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseHas('correspondences', [
            'type' => 'internal',
            'subject' => 'نامه داخلی',
            'body' => '<p>متن <strong>مهم</strong></p>',
            'status' => 'draft',
            'registration_number' => null,
            'created_by' => $user->id,
            'recipient_department_id' => $recipientDepartmentId,
            'sender_name' => $user->name,
        ]);
    }

    public function test_user_with_multiple_department_memberships_can_choose_the_creator_department(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $primaryDepartment = Department::query()->create([
            'name' => 'واحد اصلی '.Str::upper(Str::random(5)),
            'code' => 'PRI-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $secondDepartment = Department::query()->create([
            'name' => 'واحد دوم '.Str::upper(Str::random(5)),
            'code' => 'SEC-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $user->departments()->attach($primaryDepartment->id, ['is_primary' => true, 'joined_at' => now()]);
        $user->departments()->attach($secondDepartment->id, ['is_primary' => false, 'joined_at' => now()]);

        $this->actingAs($user)->get(route('correspondence.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('createdDepartments', fn ($departments) => collect($departments)->pluck('id')->contains($primaryDepartment->id)
                    && collect($departments)->pluck('id')->contains($secondDepartment->id)));

        $this->actingAs($user)->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INTERNAL,
            'created_department_id' => $secondDepartment->id,
            'recipient_department_id' => $primaryDepartment->id,
            'subject' => 'نامه از واحد دوم',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
        ])->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseHas('correspondences', [
            'subject' => 'نامه از واحد دوم',
            'created_by' => $user->id,
            'created_department_id' => $secondDepartment->id,
            'recipient_department_id' => $primaryDepartment->id,
        ]);
    }

    public function test_file_mode_requires_at_least_one_attachment(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $organization = ExternalOrganization::query()->create([
            'name' => 'سازمان مخاطب '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => 'outgoing',
            'sent_at' => '2026-10-24',
            'external_organization_id' => $organization->id,
            'subject' => 'نامه صادره',
            'composition_mode' => 'file',
            'body' => 'توضیحات فایل نامه آزمایشی',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('correspondences', 0);
    }

    public function test_scanner_upload_token_accepts_pdf_and_exposes_ready_status_only_to_its_owner(): void
    {
        Storage::fake('local');
        $owner = $this->userWithPermission('correspondence.create');
        $otherUser = $this->userWithPermission('correspondence.create');
        $token = Str::random(64);
        $cacheKey = 'correspondence_scanner_upload:'.hash('sha256', $token);

        Cache::put($cacheKey, [
            'user_id' => (int) $owner->id,
            'status' => 'pending',
        ], now()->addMinutes(10));

        $this->post(route('correspondence.scanner.upload', $token), [
            'file' => UploadedFile::fake()->create('scanned-letter.pdf', 10, 'application/pdf'),
        ])->assertOk()->assertJson(['status' => 'ready']);

        $this->actingAs($owner)->get(route('correspondence.scanner.status', $token))
            ->assertOk()
            ->assertJson(['status' => 'ready', 'file_name' => 'scanned-letter.pdf']);

        $this->actingAs($otherUser)->get(route('correspondence.scanner.status', $token))->assertNotFound();
    }

    public function test_incoming_correspondence_can_store_a_description_separately_from_original_letter(): void
    {
        Storage::fake('local');
        $secretariat = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'دبیرخانه توضیحات '.Str::upper(Str::random(5)),
            'code' => 'DESC-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $secretariat->id,
        ]);
        app(SystemSettingsService::class)->set([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id,
        ]);

        $organization = ExternalOrganization::query()->create(['name' => 'سازمان مبدأ توضیحات '.Str::upper(Str::random(5)), 'status' => 'active']);
        $description = 'خلاصه نامه درباره درخواست بررسی قرارداد';
        $this->actingAs($secretariat)->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INCOMING,
            'letter_number' => 'IN-DESC-001',
            'external_organization_id' => $organization->id,
            'received_at' => '2026-10-28',
            'subject' => 'نامه وارده با توضیح',
            'recipient_department_id' => $this->recipientDepartmentId(),
            'body' => $description,
            'original_letters' => [UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseHas('correspondences', [
            'subject' => 'نامه وارده با توضیح',
            'body' => $description,
            'letter_number' => 'IN-DESC-001',
        ]);
    }

    public function test_incoming_correspondence_requires_an_original_letter_file_even_if_body_is_provided(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'دبیرخانه تست',
            'code' => 'SEC-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $user->id,
        ]);
        app(SystemSettingsService::class)->set([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id,
        ]);

        $organization = ExternalOrganization::query()->create(['name' => 'سازمان مبدأ فایل '.Str::upper(Str::random(5)), 'status' => 'active']);
        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INCOMING,
            'letter_number' => 'IN-1405-TEST',
            'external_organization_id' => $organization->id,
            'received_at' => '2026-10-24',
            'subject' => 'نامه وارده بدون اصل نامه',
            'recipient_department_id' => $this->recipientDepartmentId(),
            'composition_mode' => 'editor',
            'body' => '<p>متن تایپی جایگزین اسکن نیست</p>',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('original_letters');

        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه وارده بدون اصل نامه']);
    }

    public function test_user_can_create_file_only_draft_with_attachment(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $recipientDepartmentId = $this->recipientDepartmentId();
        $documents = Mockery::mock(DocumentService::class);
        $documents->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data, UploadedFile $file, User $creator, Correspondence $linkable, string $purpose): bool {
                return $data['document_type'] === 'correspondence_attachment'
                    && $file->getClientOriginalName() === 'letter.pdf'
                    && $creator->is(auth()->user())
                    && $linkable->exists
                    && $purpose === 'correspondence_attachment';
            })
            ->andReturn(new Document());
        $this->instance(DocumentService::class, $documents);

        $this->actingAs($user)->post(route('correspondence.store'), [
            'type' => 'internal',
            'recipient_department_id' => $recipientDepartmentId,
            'subject' => 'نامه پیوست‌شده',
            'composition_mode' => 'file',
            'attachments' => [UploadedFile::fake()->create('letter.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseHas('correspondences', [
            'subject' => 'نامه پیوست‌شده',
            'body' => null,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
    }

    public function test_active_staff_user_has_baseline_correspondence_view_and_draft_access(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->get(route('correspondence.index'))->assertOk();
        $this->actingAs($user)->get(route('correspondence.create'))->assertOk();
    }

    public function test_non_secretariat_can_create_internal_and_directly_register_outgoing_but_not_incoming_letters(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        app(SystemSettingsService::class)->set(['correspondence.approval.outgoing' => 'direct']);
        $this->actingAs($user)->get(route('correspondence.create'))->assertOk()->assertInertia(fn ($page) => $page->where('types', [['value' => Correspondence::TYPE_OUTGOING, 'label' => 'صادره'], ['value' => Correspondence::TYPE_INTERNAL, 'label' => 'داخلی']]));
        $this->actingAs($user)->post(route('correspondence.store'), ['type' => Correspondence::TYPE_INCOMING, 'subject' => 'نامه وارده غیرمجاز', 'composition_mode' => 'editor', 'body' => '<p>متن</p>'])->assertForbidden();
        $this->assertDatabaseCount('correspondences', 0);
        $organization = ExternalOrganization::query()->create([
            'name' => 'سازمان مخاطب '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);
        $this->actingAs($user)->post(route('correspondence.store'), ['type' => Correspondence::TYPE_OUTGOING, 'sent_at' => '2026-10-24', 'external_organization_id' => $organization->id, 'subject' => 'پیش‌نویس صادره مجاز', 'composition_mode' => 'editor', 'body' => '<p>متن</p>'])->assertRedirect(route('correspondence.index'));
        $this->actingAs($user)->post(route('correspondence.store'), ['type' => Correspondence::TYPE_INTERNAL, 'recipient_department_id' => $this->recipientDepartmentId(), 'subject' => 'نامه داخلی مجاز', 'composition_mode' => 'editor', 'body' => '<p>متن</p>'])->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseCount('correspondences', 2);
    }

    public function test_only_secretariat_can_register_outgoing_draft(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $letter = Correspondence::query()->create(['type' => Correspondence::TYPE_OUTGOING, 'subject' => 'پیش‌نویس صادره', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => $creator->id]);
        $this->actingAs($creator)->post(route('correspondence.register', $letter))->assertForbidden();
        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'status' => Correspondence::STATUS_DRAFT, 'registration_number' => null]);
    }

    public function test_internal_letter_creator_can_register_their_own_internal_draft(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $letter = Correspondence::query()->create(['type' => Correspondence::TYPE_INTERNAL, 'subject' => 'نامه داخلی', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => $creator->id]);
        $this->actingAs($creator)->post(route('correspondence.register', $letter))->assertRedirect();
        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'status' => Correspondence::STATUS_REGISTERED, 'registered_by' => $creator->id]);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'actor_id' => $creator->id,
            'action_type' => 'registered',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'correspondence.action.registered',
            'auditable_type' => $letter->getMorphClass(),
            'auditable_id' => $letter->id,
        ]);
    }

    public function test_configured_secretariat_can_create_incoming_letter_and_register_outgoing_draft(): void
    {
        $secretariatUser = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create(['name' => 'دبیرخانه تست', 'code' => 'TEST-SEC', 'is_active' => true, 'manager_id' => $secretariatUser->id]);
        app(SystemSettingsService::class)->set([OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id]);
        $organization = ExternalOrganization::query()->create(['name' => 'سازمان مبدأ دبیرخانه '.Str::upper(Str::random(5)), 'status' => 'active']);
        $this->actingAs($secretariatUser)->get(route('correspondence.create'))->assertOk()->assertInertia(fn ($page) => $page->where('types.0.value', Correspondence::TYPE_INCOMING));
        $this->actingAs($secretariatUser)->post(route('correspondence.store'), ['type' => Correspondence::TYPE_INCOMING, 'received_at' => '2026-10-24', 'recipient_department_id' => $this->recipientDepartmentId(), 'external_organization_id' => $organization->id, 'letter_number' => 'IN-1405-001', 'subject' => 'نامه وارده دبیرخانه', 'original_letters' => [UploadedFile::fake()->create('original.pdf', 10, 'application/pdf')]])->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseHas('correspondences', [
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'نامه وارده دبیرخانه',
            'created_by' => $secretariatUser->id,
            'workflow_state' => 'awaiting_manager_approval',
            'current_user_id' => $secretariatUser->id,
        ]);
        $incomingLetter = Correspondence::query()->where('subject', 'نامه وارده دبیرخانه')->firstOrFail();
        $this->actingAs($secretariatUser)->post(route('correspondence.actions.store', $incomingLetter), [
            'action_type' => 'manager_approve',
            'note' => 'نامه وارده بررسی و تأیید شد.',
        ])->assertRedirect();
        $secretariatEmployee = $this->userWithPermission('correspondence.view');
        $secretariatEmployee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $this->actingAs($secretariatEmployee)->post(route('correspondence.register', $incomingLetter))->assertForbidden();
        $this->actingAs($secretariatEmployee)->get(route('correspondence.show', $incomingLetter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRegister', false));
        $this->actingAs($secretariatUser)->get(route('correspondence.show', $incomingLetter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canRegister', true)
                ->where('originalLetters.0.file_name', 'original.pdf')
                ->where('attachments', []));
        $this->actingAs($secretariatUser)->post(route('correspondence.register', $incomingLetter))->assertRedirect();
        $this->assertDatabaseHas('correspondences', [
            'id' => $incomingLetter->id,
            'status' => Correspondence::STATUS_REGISTERED,
            'registered_by' => $secretariatUser->id,
        ]);
        app(SystemSettingsService::class)->set(['correspondence.approval.incoming' => 'direct']);
        $this->actingAs($secretariatUser)->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_INCOMING,
            'received_at' => '2026-10-25',
            'recipient_department_id' => $this->recipientDepartmentId(),
            'external_organization_id' => $organization->id,
            'letter_number' => 'IN-1405-002',
            'subject' => 'نامه وارده ثبت مستقیم',
            'original_letters' => [UploadedFile::fake()->create('direct-original.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseHas('correspondences', [
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'نامه وارده ثبت مستقیم',
            'status' => Correspondence::STATUS_REGISTERED,
            'registered_by' => $secretariatUser->id,
            'workflow_state' => 'in_progress',
        ]);

        $outgoingCreator = User::factory()->create(['status' => 'active']);
        $letter = Correspondence::query()->create(['type' => Correspondence::TYPE_OUTGOING, 'subject' => 'پیش‌نویس صادره برای دبیرخانه', 'status' => Correspondence::STATUS_DRAFT, 'created_by' => $outgoingCreator->id]);
        $this->actingAs($secretariatUser)->post(route('correspondence.register', $letter))->assertRedirect();
        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'status' => Correspondence::STATUS_REGISTERED, 'registered_by' => $secretariatUser->id, 'workflow_state' => 'awaiting_dispatch']);

        $this->actingAs($secretariatUser)->put(route('correspondence.external-tracking.update', $letter), [
            'sent_at' => '2026-10-27 10:30:00',
            'dispatch_method' => 'courier',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'workflow_state' => 'dispatched', 'sent_at' => '2026-10-27 10:30:00']);
        $this->assertDatabaseHas('correspondence_actions', ['correspondence_id' => $letter->id, 'action_type' => 'dispatched', 'actor_id' => $secretariatUser->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'correspondence.action.dispatched', 'auditable_type' => $letter->getMorphClass(), 'auditable_id' => $letter->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $outgoingCreator->id, 'type' => 'App\\Modules\\Notifications\\Notifications\\CorrespondenceNotification']);
        $notification = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('notifiable_id', $outgoingCreator->id)
            ->where('type', 'App\\Modules\\Notifications\\Notifications\\CorrespondenceNotification')
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringNotContainsString('پیش‌نویس صادره برای دبیرخانه', (string) $notification->data);
    }
    public function test_outgoing_correspondence_creator_can_update_tracking_but_cannot_finalize_dispatch(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه صادره برای پیگیری',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)->put(route('correspondence.external-tracking.update', $letter), [
            'sent_at' => '2026-10-24',
        ])->assertForbidden();

        $this->actingAs($creator)->put(route('correspondence.external-tracking.update', $letter), [
            'task_id' => null,
            'external_registration_number' => 'IN-1405-184',
            'external_registered_at' => '2026-10-25',
            'dispatch_method' => 'official_portal',
            'external_tracking_notes' => 'نامه در دبیرخانه سازمان مقصد ثبت شد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'external_registration_number' => 'IN-1405-184',
            'external_registered_at' => '2026-10-25',
            'dispatch_method' => 'official_portal',
            'external_tracking_notes' => 'نامه در دبیرخانه سازمان مقصد ثبت شد.',
            'sent_at' => null,
        ]);
    }

    public function test_external_tracking_cannot_be_updated_for_an_incoming_letter(): void
    {
        $creator = User::factory()->create(['status' => 'active']);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'نامه وارده بدون پیگیری صادره',
            'status' => Correspondence::STATUS_DRAFT,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)->put(route('correspondence.external-tracking.update', $letter), [
            'external_registration_number' => 'IN-1405-185',
        ])->assertSessionHasErrors('external_tracking');

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'external_registration_number' => null,
        ]);
    }

    public function test_current_department_manager_can_approve_an_outgoing_draft_before_registration(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $secretariatManager = $this->userWithPermission('correspondence.view');
        $secretariat = Department::query()->create([
            'name' => 'دبیرخانه ثبت نهایی صادره '.Str::upper(Str::random(5)),
            'code' => 'SEC-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $secretariatManager->id,
        ]);
        app(SystemSettingsService::class)->set([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $secretariat->id,
        ]);
        $department = Department::query()->create([
            'name' => 'واحد تأییدکننده '.Str::upper(Str::random(5)),
            'code' => 'APR-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه منتظر تأیید مدیر',
            'status' => Correspondence::STATUS_DRAFT,
            'workflow_state' => 'awaiting_manager_approval',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_department_id' => $department->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_approve',
            'note' => 'محتوا بررسی شد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'approved_for_registration',
            'current_department_id' => $secretariat->id,
            'current_user_id' => $secretariatManager->id,
        ]);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => 'manager_approve',
            'actor_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'correspondence.action.manager_approve',
            'auditable_type' => $letter->getMorphClass(),
            'auditable_id' => $letter->id,
        ]);
    }

    public function test_manager_can_approve_incoming_draft_when_organization_requires_approval(): void
    {
        app(SystemSettingsService::class)->set(['correspondence.approval.incoming' => 'approval']);

        $manager = $this->userWithPermission('correspondence.view');
        $creator = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'دبیرخانه آزمون تأیید وارده '.Str::upper(Str::random(5)),
            'code' => 'INAPR-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'نامه وارده نیازمند تأیید مدیر',
            'letter_number' => 'IN-APP-001',
            'status' => Correspondence::STATUS_DRAFT,
            'workflow_state' => 'awaiting_manager_approval',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_department_id' => $department->id,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($manager)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManageWorkflow', true));

        $this->actingAs($creator)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_approve',
            'note' => 'تلاش کارمند برای تأیید',
        ])->assertForbidden();

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_approve',
            'note' => 'نامه وارده بررسی شد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'approved_for_registration',
            'current_user_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => 'manager_approve',
            'actor_id' => $manager->id,
        ]);
    }

    public function test_registered_confidential_letter_is_visible_to_copy_department_members(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $viewer = $this->userWithPermission('correspondence.view');
        $copyDepartment = Department::query()->create([
            'name' => 'واحد رونوشت '.Str::upper(Str::random(5)),
            'code' => 'CC-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $viewer->departments()->attach($copyDepartment->id, ['is_primary' => true, 'joined_at' => now()]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'classification' => Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'subject' => 'نامه محرمانه دارای رونوشت',
            'status' => Correspondence::STATUS_REGISTERED,
            'created_by' => $creator->id,
        ]);
        $letter->copies()->attach($copyDepartment->id, ['created_by' => $creator->id, 'note' => 'جهت اطلاع']);

        $this->actingAs($viewer)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('correspondence.subject', 'نامه محرمانه دارای رونوشت')
                ->where('correspondence.copies.0.name', $copyDepartment->name));

        $targetDepartment = Department::query()->create([
            'name' => 'واحد مقصد آزمون '.Str::upper(Str::random(5)),
            'code' => 'DST-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $this->actingAs($viewer)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_refer',
            'to_department_id' => $targetDepartment->id,
            'note' => 'نباید از طریق رونوشت قابل اقدام باشد.',
        ])->assertForbidden();
    }

    public function test_correspondence_cartable_only_shows_department_routing_to_manager_until_employee_assignment(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد کارتابل مکاتبات '.Str::upper(Str::random(5)),
            'code' => 'CCB-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $employee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه ارجاع‌شده به واحد',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $managerCartable = app(\App\Modules\Cartable\Services\CartableService::class)->for($manager);
        $employeeCartable = app(\App\Modules\Cartable\Services\CartableService::class)->for($employee);

        $this->assertContains($letter->id, $managerCartable['correspondenceItems']->pluck('id')->all());
        $this->assertNotContains($letter->id, $employeeCartable['correspondenceItems']->pluck('id')->all());

        $letter->forceFill([
            'workflow_state' => 'assigned_to_employee',
            'current_user_id' => $employee->id,
        ])->save();

        $employeeCartable = app(\App\Modules\Cartable\Services\CartableService::class)->for($employee);
        $this->assertContains($letter->id, $employeeCartable['correspondenceItems']->pluck('id')->all());
    }

    public function test_employee_must_submit_a_documented_result_and_manager_confirms_it_before_completion(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد نتیجه '.Str::upper(Str::random(5)),
            'code' => 'RES-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $employee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه نیازمند اعلام نتیجه',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canReturnToManager', false));

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'submit_result',
            'note' => 'کارمند هنوز از طرف مدیر مسئول بررسی نشده است.',
        ])->assertForbidden();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'in_progress',
            'current_user_id' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_refer',
            'target_type' => 'user',
            'to_user_id' => $employee->id,
            'note' => 'برای بررسی و اعلام نتیجه.',
        ])->assertRedirect();

        $this->actingAs($employee)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canReturnToManager', true));

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'submit_result',
            'note' => 'بررسی انجام شد؛ درخواست به علت نبود اعتبار قابل انجام نیست.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'result_pending_manager',
            'current_user_id' => $manager->id,
        ]);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => 'submit_result',
            'actor_id' => $employee->id,
            'note' => 'بررسی انجام شد؛ درخواست به علت نبود اعتبار قابل انجام نیست.',
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'confirm_result',
            'note' => '',
        ])->assertSessionHasErrors('workflow_action');

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'confirm_result',
            'note' => 'نتیجه و علت اعلام‌شده بررسی و تأیید شد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'completed_with_result',
            'current_user_id' => null,
        ]);
    }

    public function test_current_department_manager_can_refer_to_an_employee_in_the_same_department(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد ارجاع کارمند '.Str::upper(Str::random(5)),
            'code' => 'USR-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $employee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'نامه ارجاع به کارمند',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_refer',
            'target_type' => 'user',
            'to_user_id' => $employee->id,
            'note' => 'برای بررسی و اعلام نتیجه.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'current_department_id' => $department->id,
            'current_user_id' => $employee->id,
            'workflow_state' => 'assigned_to_employee',
        ]);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => 'manager_refer',
            'to_user_id' => $employee->id,
            'to_department_id' => $department->id,
        ]);

        $otherEmployee = $this->userWithPermission('correspondence.view');
        $otherEmployee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $this->actingAs($otherEmployee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'submit_result',
            'note' => 'نباید بتواند به نامه ارجاع‌شده به کاربر دیگر اقدام کند.',
        ])->assertForbidden();

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'submit_result',
            'note' => 'بررسی کارمند ارجاع‌شده انجام شد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'result_pending_manager',
            'current_user_id' => $manager->id,
        ]);
    }

    public function test_manager_can_refer_to_another_department_after_employee_submits_result(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $currentDepartment = Department::query()->create([
            'name' => 'واحد نتیجه و ارجاع مجدد '.Str::upper(Str::random(5)),
            'code' => 'RER-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $employee->departments()->attach($currentDepartment->id, ['is_primary' => true, 'joined_at' => now()]);
        $targetDepartment = Department::query()->create([
            'name' => 'واحد ارجاع پس از نتیجه '.Str::upper(Str::random(5)),
            'code' => 'NXT-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'نامه نیازمند ارجاع مجدد پس از نتیجه',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $currentDepartment->id,
            'current_user_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_refer',
            'target_type' => 'user',
            'to_user_id' => $employee->id,
            'note' => 'برای بررسی اولیه و اعلام نتیجه.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'assigned_to_employee',
            'current_user_id' => $employee->id,
        ]);

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'submit_result',
            'note' => 'بررسی اولیه انجام شد و نیاز به نظر واحد دیگری وجود دارد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'result_pending_manager',
            'current_user_id' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_refer',
            'target_type' => 'department',
            'to_department_id' => $targetDepartment->id,
            'note' => 'پس از بررسی کارمند، برای بررسی تخصصی ارجاع می‌شود.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'current_department_id' => $targetDepartment->id,
            'workflow_state' => 'in_progress',
        ]);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => 'manager_refer',
            'from_department_id' => $currentDepartment->id,
            'to_department_id' => $targetDepartment->id,
        ]);
    }

    public function test_secretariat_member_who_manages_current_department_can_still_act_as_its_manager(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $secretariatDepartment = Department::query()->create([
            'name' => 'دبیرخانه نقش ترکیبی '.Str::upper(Str::random(5)),
            'code' => 'MIX-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $currentDepartment = Department::query()->create([
            'name' => 'واحد تحت مدیریت چندگانه '.Str::upper(Str::random(5)),
            'code' => 'MGR-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $targetDepartment = Department::query()->create([
            'name' => 'واحد مقصد نقش ترکیبی '.Str::upper(Str::random(5)),
            'code' => 'DST-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        app(SystemSettingsService::class)->set([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $secretariatDepartment->id,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'نامه وارده با نقش ترکیبی',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $currentDepartment->id,
            'current_user_id' => $manager->id,
            'registered_by' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'manager_refer',
            'target_type' => 'department',
            'to_department_id' => $targetDepartment->id,
            'note' => 'ارجاع از سمت مدیر واحد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'current_department_id' => $targetDepartment->id,
            'workflow_state' => 'in_progress',
        ]);
    }

    public function test_secretariat_can_return_outgoing_letter_for_correction_and_creator_can_resubmit(): void
    {
        $secretariat = $this->userWithPermission('correspondence.view');
        $creator = $this->userWithPermission('correspondence.create');
        $viewRoleUser = $this->userWithPermission('correspondence.view');
        $creator->roles()->attach($viewRoleUser->roles()->firstOrFail()->id);
        $creator = $creator->fresh();
        $secretariatDepartment = Department::query()->create([
            'name' => 'دبیرخانه برگشت '.Str::upper(Str::random(5)),
            'code' => 'RET-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $secretariat->id,
        ]);
        $originDepartment = Department::query()->create([
            'name' => 'واحد ایجادکننده '.Str::upper(Str::random(5)),
            'code' => 'ORG-'.Str::upper(Str::random(6)),
            'is_active' => true,
        ]);
        $creator->departments()->attach($originDepartment->id, ['is_primary' => true, 'joined_at' => now()]);
        app(SystemSettingsService::class)->set([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $secretariatDepartment->id,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه صادره دارای ایراد',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'awaiting_dispatch',
            'current_department_id' => $secretariatDepartment->id,
            'created_department_id' => $originDepartment->id,
            'created_by' => $creator->id,
            'registered_by' => $secretariat->id,
        ]);

        $this->actingAs($secretariat)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'secretariat_return',
            'note' => 'نام سازمان مخاطب با متن نامه مطابقت ندارد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'returned_for_correction',
            'current_department_id' => $originDepartment->id,
            'current_user_id' => $creator->id,
        ]);
        $this->actingAs($creator)->get(route('correspondence.edit', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Correspondence/Create'));

        $this->actingAs($creator)->post(route('correspondence.actions.store', $letter), [
            'action_type' => 'resubmit_to_secretariat',
            'note' => 'اصلاحات انجام شد.',
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'awaiting_dispatch',
            'current_department_id' => $secretariatDepartment->id,
        ]);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => 'secretariat_return',
            'note' => 'نام سازمان مخاطب با متن نامه مطابقت ندارد.',
        ]);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => 'resubmit_to_secretariat',
        ]);
    }

    public function test_legacy_reject_and_close_without_result_actions_are_not_accepted(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد آزمون گردش '.Str::upper(Str::random(5)),
            'code' => 'WF-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $manager->id,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه بدون اقدام منسوخ',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        foreach (['reject_request', 'close_without_result'] as $legacyAction) {
            $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
                'action_type' => $legacyAction,
                'note' => 'این اقدام دیگر نباید پذیرفته شود.',
            ])->assertSessionHasErrors('action_type');
        }

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'workflow_state' => 'in_progress',
        ]);
    }

    private function recipientDepartmentId(): int
    {
        return Department::query()->create([
            'name' => 'واحد گیرنده '.Str::upper(Str::random(6)),
            'code' => 'REC-'.Str::upper(Str::random(8)),
            'is_active' => true,
        ])->id;
    }

    public function test_incoming_letter_requires_and_preserves_original_letter_number(): void
    {
        Storage::fake('local');
        $secretariat=User::factory()->create(['status'=>'active']);
        $department=Department::query()->create(['name'=>'دبیرخانه شماره نامه '.Str::upper(Str::random(5)),'code'=>'IN-'.Str::upper(Str::random(5)),'is_active'=>true,'manager_id'=>$secretariat->id]);
        app(SystemSettingsService::class)->set([OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT)=>(string)$department->id]);
        $organization=ExternalOrganization::query()->create(['name'=>'سازمان مبدأ شماره نامه '.Str::upper(Str::random(5)),'status'=>'active']);
        $payload=['type'=>Correspondence::TYPE_INCOMING,'recipient_department_id'=>$this->recipientDepartmentId(),'received_at'=>'2026-10-27','subject'=>'وارده با شماره اصلی','letter_number'=>'OUTSIDE-452/الف','external_organization_id'=>$organization->id,'original_letters'=>[UploadedFile::fake()->create('original.pdf',10,'application/pdf')]];
        $this->actingAs($secretariat)->post(route('correspondence.store'),$payload)->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseHas('correspondences',['subject'=>'وارده با شماره اصلی','letter_number'=>'OUTSIDE-452/الف','registration_number'=>null]);
        unset($payload['letter_number']); $payload['subject']='وارده بدون شماره اصلی';
        $this->actingAs($secretariat)->from(route('correspondence.create'))->post(route('correspondence.store'),$payload)->assertRedirect(route('correspondence.create'))->assertSessionHasErrors('letter_number');
        $this->assertDatabaseMissing('correspondences',['subject'=>'وارده بدون شماره اصلی']);
    }

    public function test_incoming_letter_number_must_be_unique_per_source_organization(): void
    {
        Storage::fake('local');
        $secretariat = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'دبیرخانه یکتایی شماره '.Str::upper(Str::random(5)),
            'code' => 'UNQ-'.Str::upper(Str::random(6)),
            'is_active' => true,
            'manager_id' => $secretariat->id,
        ]);
        app(SystemSettingsService::class)->set([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id,
        ]);
        $organizationA = ExternalOrganization::query()->create(['name' => 'سازمان الف '.Str::upper(Str::random(5)), 'status' => 'active']);
        $organizationB = ExternalOrganization::query()->create(['name' => 'سازمان ب '.Str::upper(Str::random(5)), 'status' => 'active']);
        $missingOrganizationPayload = [
            'type' => Correspondence::TYPE_INCOMING,
            'letter_number' => 'NO-ORG-1405',
            'received_at' => '2026-10-27',
            'recipient_department_id' => $this->recipientDepartmentId(),
            'subject' => 'نامه بدون سازمان مبدأ',
            'original_letters' => [UploadedFile::fake()->create('no-org.pdf', 10, 'application/pdf')],
        ];
        $this->actingAs($secretariat)->from(route('correspondence.create'))->post(route('correspondence.store'), $missingOrganizationPayload)
            ->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('external_organization_id');

        $payload = [
            'type' => Correspondence::TYPE_INCOMING,
            'letter_number' => 'DUP-1405-01',
            'received_at' => '2026-10-27',
            'recipient_department_id' => $this->recipientDepartmentId(),
            'subject' => 'نامه اول',
            'external_organization_id' => $organizationA->id,
            'original_letters' => [UploadedFile::fake()->create('first.pdf', 10, 'application/pdf')],
        ];

        $this->actingAs($secretariat)->post(route('correspondence.store'), $payload)
            ->assertRedirect(route('correspondence.index'));
        $payload['subject'] = 'نامه تکراری همان سازمان';
        $payload['original_letters'] = [UploadedFile::fake()->create('duplicate.pdf', 10, 'application/pdf')];
        $this->from(route('correspondence.create'))->post(route('correspondence.store'), $payload)
            ->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('letter_number');
        $payload['subject'] = 'همان شماره در سازمان دیگر';
        $payload['external_organization_id'] = $organizationB->id;
        $payload['original_letters'] = [UploadedFile::fake()->create('other-org.pdf', 10, 'application/pdf')];
        $this->post(route('correspondence.store'), $payload)
            ->assertRedirect(route('correspondence.index'));
    }

    public function test_incoming_correspondence_can_be_managed_by_secretariat_after_registration_until_first_action(): void
    {
        $secretariat = User::factory()->create(['status' => 'active']);
        $department = Department::query()->create([
            'name' => 'دبیرخانه اصلاح مکاتبه '.Str::upper(Str::random(5)),
            'code' => 'ED-'.Str::upper(Str::random(5)),
            'is_active' => true,
            'manager_id' => $secretariat->id,
        ]);
        app(SystemSettingsService::class)->set([
            OrganizationDepartmentRoleService::settingKey(OrganizationDepartmentRoleService::SECRETARIAT) => (string) $department->id,
        ]);
        $recipient = $this->recipientDepartmentId();
        $organization = ExternalOrganization::query()->create(['name' => 'سازمان مبدأ ویرایش '.Str::upper(Str::random(5)), 'status' => 'active']);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'موضوع اولیه',
            'letter_number' => 'IN-123',
            'registration_number' => 'REG-123',
            'status' => Correspondence::STATUS_REGISTERED,
            'registered_by' => $secretariat->id,
            'created_by' => $secretariat->id,
            'recipient_department_id' => $recipient,
            'external_organization_id' => $organization->id,
            'received_at' => '2026-10-27',
        ]);

        // Incoming correspondence must retain its original scanned letter even
        // when editing other metadata; give this fixture a valid original file.
        Storage::fake('local');
        app(DocumentService::class)->create(
            data: ['title' => 'اصل نامه آزمایشی', 'document_type' => 'correspondence_original'],
            file: UploadedFile::fake()->create('original.pdf', 10, 'application/pdf'),
            creator: $secretariat,
            linkable: $letter,
            purpose: 'correspondence_original',
        );

        // These records are created as part of registration/copy bookkeeping and
        // must not count as the first user workflow action.
        foreach (['registered', 'secretariat_initial_referral', 'copy_added'] as $actionType) {
            \App\Modules\Correspondence\Models\CorrespondenceAction::query()->create([
                'correspondence_id' => $letter->id,
                'actor_id' => $secretariat->id,
                'action_type' => $actionType,
                'note' => 'ثبت سیستمی',
            ]);
        }

        $this->actingAs($secretariat)->put(route('correspondence.update', $letter), [
            'subject' => 'موضوع اصلاح‌شده',
            'classification' => 'ordinary',
            'letter_number' => 'IN-124',
            'received_at' => '2026-10-27',
            'recipient_department_id' => $recipient,
            'external_organization_id' => $organization->id,
        ])->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'subject' => 'موضوع اصلاح‌شده', 'letter_number' => 'IN-124']);

        $this->actingAs($secretariat)->delete(route('correspondence.destroy', $letter))->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseMissing('correspondences', ['id' => $letter->id]);

        $blocked = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'subject' => 'نامه دارای ارجاع',
            'letter_number' => 'IN-125',
            'status' => Correspondence::STATUS_REGISTERED,
            'created_by' => $secretariat->id,
        ]);
        \App\Modules\Correspondence\Models\CorrespondenceAction::query()->create([
            'correspondence_id' => $blocked->id,
            'actor_id' => $secretariat->id,
            'action_type' => 'manager_approve',
            'note' => 'اقدام آزمایشی',
        ]);
        $this->actingAs($secretariat)->delete(route('correspondence.destroy', $blocked))->assertForbidden();
        $this->assertDatabaseHas('correspondences', ['id' => $blocked->id]);
    }

    public function test_system_admin_can_delete_correspondence_after_workflow_actions(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $adminRole = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN'],
            [
                'name' => 'مدیر سیستم',
                'description' => 'System administrator role for tests',
                'is_active' => true,
                'is_system' => true,
            ],
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        $creator = $this->userWithPermission('correspondence.create');
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INTERNAL,
            'subject' => 'نامه آزمایشی دارای گردش',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => 'REG-ADMIN-DELETE-001',
            'created_by' => $creator->id,
        ]);
        \App\Modules\Correspondence\Models\CorrespondenceAction::query()->create([
            'correspondence_id' => $letter->id,
            'actor_id' => $admin->id,
            'action_type' => 'manager_approve',
            'note' => 'اقدام گردش آزمایشی',
        ]);

        $this->actingAs($admin)->delete(route('correspondence.destroy', $letter))
            ->assertRedirect(route('correspondence.index'));

        $this->assertDatabaseMissing('correspondences', ['id' => $letter->id]);
        $this->assertDatabaseMissing('correspondence_actions', ['correspondence_id' => $letter->id]);
    }

    public function test_creator_can_manage_registered_outgoing_correspondence_until_first_action(): void
    {
        $creator = $this->userWithPermission('correspondence.create');
        $otherUser = $this->userWithPermission('correspondence.create');
        $organization = ExternalOrganization::query()->create([
            'name' => 'سازمان مخاطب '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'subject' => 'نامه صادره اولیه',
            'body' => '<p>متن اولیه نامه</p>',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => 'REG-OUT-123',
            'external_organization_id' => $organization->id,
            'created_by' => $creator->id,
        ]);

        $this->actingAs($creator)->put(route('correspondence.update', $letter), [
            'subject' => 'نامه صادره اصلاح‌شده',
            'classification' => 'ordinary',
            'sent_at' => '2026-10-27',
            'external_organization_id' => $organization->id,
            'composition_mode' => 'editor',
            'body' => '<p>متن اصلاح‌شده نامه</p>',
        ])->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseHas('correspondences', ['id' => $letter->id, 'subject' => 'نامه صادره اصلاح‌شده']);

        $this->actingAs($otherUser)->delete(route('correspondence.destroy', $letter))->assertForbidden();
        $this->actingAs($creator)->delete(route('correspondence.destroy', $letter))->assertRedirect(route('correspondence.index'));
        $this->assertDatabaseMissing('correspondences', ['id' => $letter->id]);
    }

    public function test_project_management_employee_request_requires_manager_decision_and_can_be_returned_for_revision(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $projectDepartment = Department::query()->create([
            'name' => 'واحد پروژه‌های آزمون '.Str::upper(Str::random(5)),
            'code' => 'PM-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $employee->departments()->attach($projectDepartment->id, ['is_primary' => true, 'joined_at' => now()]);
        app(SystemSettingsService::class)->set([
            'organization.department_roles.project_management' => (string) $projectDepartment->id,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'درخواست پروژه برای بررسی مرحله‌ای',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'assigned_to_employee',
            'recipient_department_id' => $projectDepartment->id,
            'current_department_id' => $projectDepartment->id,
            'current_user_id' => $employee->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::PM_REQUEST_PROJECT,
            'note' => '',
        ])->assertSessionHasErrors('workflow_action');

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::PM_REQUEST_PROJECT,
            'note' => 'با توجه به حجم کار، ایجاد پروژه مستقل پیشنهاد می‌شود.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame('project_review_pending_manager', $letter->workflow_state);
        $this->assertSame((int) $manager->id, (int) $letter->current_user_id);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => CorrespondenceAction::PM_REQUEST_PROJECT,
            'actor_id' => $employee->id,
            'to_user_id' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::PM_MANAGER_REJECT_REQUEST,
            'note' => 'اطلاعات تکمیلی برای تصمیم لازم است.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame('assigned_to_employee', $letter->workflow_state);
        $this->assertSame((int) $employee->id, (int) $letter->current_user_id);

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::PM_REQUEST_PROJECT,
            'note' => 'نسخه تکمیلی درخواست پروژه.',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::PM_MANAGER_APPROVE_REQUEST,
            'note' => 'درخواست پروژه تأیید می‌شود.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame('project_request_approved', $letter->workflow_state);
        $this->assertSame((int) $manager->id, (int) $letter->current_user_id);
    }

    public function test_project_management_referral_request_does_not_route_until_manager_approves_it(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $targetManager = $this->userWithPermission('correspondence.view');
        $projectDepartment = Department::query()->create([
            'name' => 'واحد پروژه‌های ارجاع آزمون '.Str::upper(Str::random(5)),
            'code' => 'PMR-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $targetDepartment = Department::query()->create([
            'name' => 'واحد مقصد ارجاع آزمون '.Str::upper(Str::random(5)),
            'code' => 'TGT-'.Str::upper(Str::random(6)),
            'manager_id' => $targetManager->id,
            'is_active' => true,
        ]);
        $employee->departments()->attach($projectDepartment->id, ['is_primary' => true, 'joined_at' => now()]);
        app(SystemSettingsService::class)->set([
            'organization.department_roles.project_management' => (string) $projectDepartment->id,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'درخواست ارجاع واحد پروژه‌ها',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'assigned_to_employee',
            'recipient_department_id' => $projectDepartment->id,
            'current_department_id' => $projectDepartment->id,
            'current_user_id' => $employee->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::PM_REQUEST_REFER,
            'to_department_id' => $targetDepartment->id,
            'note' => 'این موضوع در حوزه تخصصی واحد مقصد است.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame((int) $projectDepartment->id, (int) $letter->current_department_id);
        $this->assertSame('project_review_pending_manager', $letter->workflow_state);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::PM_MANAGER_APPROVE_REQUEST,
            'note' => 'ارجاع به واحد تخصصی تأیید شد.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame((int) $targetDepartment->id, (int) $letter->current_department_id);
        $this->assertSame((int) $targetManager->id, (int) $letter->current_user_id);
        $this->assertSame('in_progress', $letter->workflow_state);
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => $permissionCode],
            [
                'name' => 'Correspondence test permission '.$permissionCode,
                'is_active' => true,
                'is_system' => true,
            ],
        );

        $role = Role::query()->create([
            'name' => 'Correspondence Test '.Str::upper(Str::random(8)),
            'code' => 'correspondence-test-'.Str::lower(Str::random(12)),
            'description' => 'Correspondence HTTP test role',
            'is_active' => true,
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id, [
            'scope' => PermissionScope::defaultForPermission($permission->code)->value,
        ]);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }
    public function test_outgoing_tracking_updates_are_audited_without_storing_tracking_note_values(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_OUTGOING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه صادره برای پیگیری',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/000321',
            'created_by' => $user->id,
        ]);
        $privateNote = 'جزئیات داخلی پیگیری که نباید در Audit ذخیره شود';

        $this->actingAs($user)->put(route('correspondence.external-tracking.update', $letter), [
            'sent_at' => '2026-10-26',
        ])->assertForbidden();

        $this->actingAs($user)->put(route('correspondence.external-tracking.update', $letter), [
            'external_registration_number' => 'EXT-991',
            'external_registered_at' => '2026-10-25',
            'dispatch_method' => 'courier',
            'external_tracking_notes' => $privateNote,
        ])->assertRedirect();

        $this->assertDatabaseHas('correspondences', [
            'id' => $letter->id,
            'external_registration_number' => 'EXT-991',
            'dispatch_method' => 'courier',
            'external_tracking_notes' => $privateNote,
        ]);

        $audit = \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('event', 'correspondence.external_tracking.updated')
            ->where('auditable_type', $letter->getMorphClass())
            ->where('auditable_id', $letter->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('external_tracking_notes', (string) $audit->metadata);
        $this->assertStringNotContainsString($privateNote, json_encode($audit, JSON_UNESCAPED_UNICODE));
        $this->assertNull($audit->old_values);
        $this->assertNull($audit->new_values);
    }

    public function test_cartable_includes_actionable_correspondence_but_not_copy_only_access(): void
    {
        $actor = $this->userWithPermission('correspondence.view');
        $copyViewer = $this->userWithPermission('correspondence.view');
        $currentDepartment = Department::query()->create([
            'name' => 'واحد جاری '.Str::upper(Str::random(5)),
            'code' => 'CUR-'.Str::upper(Str::random(6)),
            'manager_id' => $actor->id,
            'is_active' => true,
        ]);
        $copyDepartment = Department::query()->create([
            'name' => 'واحد رونوشت '.Str::upper(Str::random(5)),
            'code' => 'CPY-'.Str::upper(Str::random(6)),
            'manager_id' => $copyViewer->id,
            'is_active' => true,
        ]);

        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'subject' => 'نامه نیازمند اقدام',
            'status' => Correspondence::STATUS_REGISTERED,
            'registration_number' => '1405/008888',
            'created_by' => $actor->id,
            'current_department_id' => $currentDepartment->id,
            'workflow_state' => 'in_progress',
        ]);
        $letter->copies()->attach($copyDepartment->id, [
            'created_by' => $actor->id,
            'note' => 'جهت اطلاع',
        ]);

        $actorCartable = app(\App\Modules\Cartable\Services\CartableService::class)->for($actor);
        $copyCartable = app(\App\Modules\Cartable\Services\CartableService::class)->for($copyViewer);

        $this->assertSame(1, $actorCartable['summary']['correspondence_referrals']);
        $this->assertSame($letter->id, $actorCartable['correspondenceItems']->first()->id);
        $this->assertSame(0, $copyCartable['summary']['correspondence_referrals']);
        $this->assertCount(0, $copyCartable['correspondenceItems']);
    }

    public function test_outgoing_correspondence_can_select_an_existing_external_organization_and_department(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        app(SystemSettingsService::class)->set(['correspondence.approval.outgoing' => 'direct']);
        $organization = ExternalOrganization::query()->create([
            'name' => 'سازمان بیرونی '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);
        $department = ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'واحد بیرونی '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('correspondence.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('externalOrganizations.0.id', $organization->id)
                ->where('externalDepartments.0.id', $department->id));

        $this->actingAs($user)->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_OUTGOING,
            'sent_at' => '2026-10-24',
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'recipient_name' => 'مدیر واحد',
            'subject' => 'نامه به سازمان بیرونی',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
        ])->assertRedirect(route('correspondence.index'));

        $letter = Correspondence::query()->where('subject', 'نامه به سازمان بیرونی')->firstOrFail();
        $this->assertSame($organization->id, (int) $letter->external_organization_id);
        $this->assertSame($department->id, (int) $letter->external_department_id);

        $this->actingAs($user)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('correspondence.external_organization.name', $organization->name)
                ->where('correspondence.external_department.name', $department->name));
    }

    public function test_correspondence_rejects_external_department_from_another_organization(): void
    {
        $user = $this->userWithPermission('correspondence.create');
        $organization = ExternalOrganization::query()->create([
            'name' => 'سازمان الف '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);
        $otherOrganization = ExternalOrganization::query()->create([
            'name' => 'سازمان ب '.Str::upper(Str::random(5)),
            'status' => 'active',
        ]);
        $department = ExternalDepartment::query()->create([
            'external_organization_id' => $otherOrganization->id,
            'name' => 'واحد متعلق به سازمان ب',
            'status' => 'active',
        ]);

        $this->actingAs($user)->from(route('correspondence.create'))->post(route('correspondence.store'), [
            'type' => Correspondence::TYPE_OUTGOING,
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'subject' => 'نامه با واحد نامعتبر',
            'composition_mode' => 'editor',
            'body' => '<p>متن نامه</p>',
        ])->assertRedirect(route('correspondence.create'))
            ->assertSessionHasErrors('external_department_id');

        $this->assertDatabaseMissing('correspondences', ['subject' => 'نامه با واحد نامعتبر']);
    }



    public function test_general_department_employee_can_submit_request_and_manager_can_return_it_for_revision(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد گردش عمومی '.Str::upper(Str::random(5)),
            'code' => 'GW-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $employee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه برای درخواست کارمند',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'assigned_to_employee',
            'recipient_department_id' => $department->id,
            'current_department_id' => $department->id,
            'current_user_id' => $employee->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::EMPLOYEE_REQUEST_LETTER,
            'note' => '',
        ])->assertSessionHasErrors('workflow_action');

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::EMPLOYEE_REQUEST_LETTER,
            'note' => 'برای پاسخ‌گویی به نامه، تهیه نامه رسمی لازم است.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame('general_review_pending_manager', $letter->workflow_state);
        $this->assertSame((int) $manager->id, (int) $letter->current_user_id);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => CorrespondenceAction::EMPLOYEE_REQUEST_LETTER,
            'actor_id' => $employee->id,
            'to_user_id' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::MANAGER_REJECT_EMPLOYEE_REQUEST,
            'note' => 'ابتدا پیش‌نویس پاسخ را با اطلاعات تکمیلی اصلاح کنید.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame('assigned_to_employee', $letter->workflow_state);
        $this->assertSame((int) $employee->id, (int) $letter->current_user_id);
    }



    public function test_manager_can_delegate_letter_draft_to_assigned_employee_and_receive_the_created_draft(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $letterCreator = $this->userWithPermission('correspondence.create');
        $employee->roles()->attach($letterCreator->roles()->first()->id);

        $department = Department::query()->create([
            'name' => 'واحد تهیه پیش‌نویس '.Str::upper(Str::random(5)),
            'code' => 'LD-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $employee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'درخواست تهیه نامه پاسخ',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'assigned_to_employee',
            'recipient_department_id' => $department->id,
            'current_department_id' => $department->id,
            'current_user_id' => $employee->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::EMPLOYEE_REQUEST_LETTER,
            'note' => 'برای پاسخ رسمی به این مکاتبه نیاز به تهیه نامه داریم.',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::MANAGER_ASSIGN_LETTER_DRAFT,
            'note' => 'پیش‌نویس پاسخ را تهیه و برای بررسی ارسال کنید.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame('letter_draft_pending_employee', $letter->workflow_state);
        $this->assertSame((int) $employee->id, (int) $letter->current_user_id);

        $this->actingAs($employee)->get(route('correspondence.create', ['source_correspondence_id' => $letter->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Correspondence/Create')
                ->where('sourceCorrespondenceId', $letter->id)
                ->where('sourceCorrespondence.id', $letter->id));

        $this->actingAs($employee)->post(route('correspondence.store'), [
            'source_correspondence_id' => $letter->id,
            'type' => Correspondence::TYPE_INTERNAL,
            'created_department_id' => $department->id,
            'recipient_department_id' => $department->id,
            'subject' => 'پیش‌نویس پاسخ به نامه',
            'composition_mode' => 'editor',
            'body' => '<p>متن پیش‌نویس پاسخ</p>',
        ])->assertRedirect(route('correspondence.show', $letter));

        $letter->refresh();
        $draft = Correspondence::query()->where('reply_to_id', $letter->id)->firstOrFail();
        $this->assertSame('letter_draft_pending_manager', $letter->workflow_state);
        $this->assertSame((int) $manager->id, (int) $letter->current_user_id);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => CorrespondenceAction::REQUESTED_LETTER_DRAFT_CREATED,
            'actor_id' => $employee->id,
            'to_user_id' => $manager->id,
        ]);
        $this->assertSame((int) $letter->id, (int) $draft->reply_to_id);
        $this->actingAs($manager)->get(route('correspondence.show', $draft))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Correspondence/Show'));
        $draft->forceFill(['status' => Correspondence::STATUS_REGISTERED, 'registration_number' => 'TEST-LETTER-'.Str::upper(Str::random(5)), 'registered_by' => $manager->id])->save();
        app(CorrespondenceWorkflowService::class)->onRegistered($draft, $manager);
        $letter->refresh();
        $this->assertSame('letter_registered', $letter->workflow_state);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => CorrespondenceAction::REQUESTED_LETTER_REGISTERED,
            'actor_id' => $manager->id,
        ]);
    }







    public function test_confidential_correspondence_is_visible_to_assignees_of_linked_workflow_tasks(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $assignee = $this->userWithPermission('correspondence.view');
        $unrelated = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد محرمانگی گردش '.Str::upper(Str::random(5)),
            'code' => 'SC-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $definition = WorkflowDefinition::query()->create([
            'name' => 'فرآیند محرمانگی',
            'code' => 'SC-'.Str::upper(Str::random(8)),
            'created_by' => $manager->id,
            'is_active' => true,
        ]);
        $step = WorkflowStep::query()->create([
            'workflow_definition_id' => $definition->id,
            'step_order' => 1,
            'name' => 'اقدام محرمانه',
            'task_title' => 'اقدام روی نامه محرمانه',
            'actor_type' => 'specific_user',
            'actor_id' => $assignee->id,
            'department_id' => $department->id,
            'review_required' => true,
            'priority' => 'normal',
            'is_active' => true,
        ]);
        $instance = WorkflowInstance::query()->create([
            'workflow_definition_id' => $definition->id,
            'initiated_by' => $manager->id,
            'department_id' => $department->id,
            'status' => 'running',
            'started_at' => now(),
        ]);
        $task = Task::query()->create([
            'department_id' => $department->id,
            'title' => 'اقدام روی نامه محرمانه',
            'status' => 'todo',
            'priority' => 'normal',
            'review_required' => true,
            'review_status' => 'not_started',
            'assignee_id' => $assignee->id,
            'created_by' => $manager->id,
            'is_active' => true,
        ]);
        WorkflowInstanceStep::query()->create([
            'workflow_instance_id' => $instance->id,
            'workflow_step_id' => $step->id,
            'task_id' => $task->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_CONFIDENTIAL,
            'subject' => 'نامه محرمانه مرتبط با گردش کار',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'workflow_started',
            'workflow_instance_id' => $instance->id,
            'created_by' => $manager->id,
            'registered_by' => $manager->id,
        ]);

        $access = app(CorrespondenceAccessService::class);
        $this->assertTrue($access->canView($letter, $assignee));
        $this->assertFalse($access->canView($letter, $unrelated));
        $this->assertSame(1, $access->visibleToQuery(Correspondence::query(), $assignee)
            ->whereKey($letter->id)->count());
        $this->assertSame(0, $access->visibleToQuery(Correspondence::query(), $unrelated)
            ->whereKey($letter->id)->count());
    }



    public function test_manager_creates_general_task_from_correspondence_and_links_it(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $taskCreator = $this->userWithPermission('tasks.create');
        $taskViewer = $this->userWithPermission('tasks.view');
        $manager->roles()->attach([
            $taskCreator->roles()->first()->id,
            $taskViewer->roles()->first()->id,
        ]);
        $department = Department::query()->create([
            'name' => 'واحد دستورکار عمومی '.Str::upper(Str::random(5)),
            'code' => 'GT-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $employee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $project = Project::query()->where('is_system_default', true)->where('is_active', true)->orderBy('id')->first();
        if ($project === null) {
            $project = Project::query()->create([
                'name' => 'پروژه عمومی سازمان',
                'code' => 'GEN-'.Str::upper(Str::random(6)),
                'status' => 'active',
                'manager_id' => null,
                'created_by' => null,
                'is_active' => true,
                'is_system_default' => true,
            ]);
        }
        $section = ProjectSection::query()->firstOrCreate([
            'project_id' => $project->id,
            'department_id' => $department->id,
        ], [
            'name' => $department->name,
            'manager_can_create_tasks' => true,
            'sort_order' => 1,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه برای ایجاد دستورکار عمومی',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'assigned_to_employee',
            'current_department_id' => $department->id,
            'current_user_id' => $employee->id,
            'recipient_department_id' => $department->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::EMPLOYEE_REQUEST_TASK,
            'note' => 'درخواست ایجاد دستورکار برای پیگیری نامه.',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::MANAGER_CREATE_TASK,
            'note' => 'با درخواست ایجاد دستورکار موافقت شد.',
        ])->assertRedirect();

        $this->actingAs($manager)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Correspondence/Show')
                ->where('canManageWorkflow', false)
                ->where('taskInstructionUrl', fn ($value) => is_string($value) && str_contains($value, 'create_for_correspondence='.$letter->id)));

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::MANAGER_REFER,
            'target_type' => 'user',
            'to_user_id' => $employee->id,
            'note' => 'ارجاع پیش از ایجاد دستورکار نباید مجاز باشد.',
        ])->assertSessionHasErrors('workflow_action');

        $this->actingAs($manager)->get(route('tasks.index', [
            'create_for_correspondence' => $letter->id,
            'project_id' => $project->id,
            'section_id' => $section->id,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('createForCorrespondence.correspondence_id', (int) $letter->id)
                ->where('createForCorrespondence.project_id', (int) $project->id)
                ->where('createForCorrespondence.section_id', (int) $section->id)
                ->where('formOptions.projects', fn ($projects) => collect($projects)->contains(
                    fn ($item) => (int) $item['id'] === (int) $project->id
                        && collect($item['sections'])->contains(fn ($itemSection) => (int) $itemSection['id'] === (int) $section->id)
                )));

        $this->actingAs($manager)->post(route('tasks.store'), [
            'correspondence_id' => $letter->id,
            'project_id' => $project->id,
            'section_id' => $section->id,
            'department_id' => $department->id,
            'title' => 'پیگیری نامه و تهیه پاسخ',
            'priority' => 'normal',
            'delegate_to_employee' => false,
        ])->assertRedirect(route('correspondence.show', $letter));

        $letter->refresh();
        $task = Task::query()->findOrFail($letter->task_id);
        $this->assertSame((int) $project->id, (int) $task->project_id);
        $this->assertSame((int) $section->id, (int) $task->section_id);
        $this->assertSame((int) $department->id, (int) $task->department_id);
        $this->assertSame((int) $manager->id, (int) $task->assignee_id);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => 'manager_create_task',
            'actor_id' => $manager->id,
        ]);

        $this->actingAs($manager)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManageWorkflow', true)
                ->where('canCompleteTaskInstructionWorkflow', true));

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::MANAGER_COMPLETE_WORKFLOW,
            'note' => 'دستورکار ایجاد شد و اقدام دیگری برای این نامه باقی نمانده است.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame('workflow_completed', $letter->workflow_state);
        $this->assertNull($letter->current_user_id);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => CorrespondenceAction::MANAGER_COMPLETE_WORKFLOW,
            'actor_id' => $manager->id,
        ]);
    }




    public function test_manager_can_recall_an_unacted_employee_referral(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد پس گرفتن ارجاع '.Str::upper(Str::random(5)),
            'code' => 'RC-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $employee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه با ارجاع در انتظار اقدام',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::MANAGER_REFER,
            'target_type' => 'user',
            'to_user_id' => $employee->id,
            'note' => 'برای بررسی ارجاع شد.',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::MANAGER_REFER,
            'target_type' => 'user',
            'to_user_id' => $employee->id,
            'note' => 'ارجاع مجدد نباید مجاز باشد.',
        ])->assertSessionHasErrors('workflow_action');

        $this->actingAs($manager)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRecallReferral', true));

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::MANAGER_RECALL_REFERRAL,
            'note' => 'برای اصلاح نحوه ارجاع، نامه به مدیر بازگردانده می‌شود.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame('in_progress', $letter->workflow_state);
        $this->assertSame((int) $manager->id, (int) $letter->current_user_id);
        $this->assertDatabaseHas('correspondence_actions', [
            'correspondence_id' => $letter->id,
            'action_type' => CorrespondenceAction::MANAGER_RECALL_REFERRAL,
            'actor_id' => $manager->id,
            'to_user_id' => $manager->id,
        ]);
    }

    public function test_employee_can_request_a_shared_project_task_and_manager_can_open_existing_task_form(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $taskCreator = $this->userWithPermission('tasks.create');
        $taskViewer = $this->userWithPermission('tasks.view');
        $manager->roles()->attach([
            $taskCreator->roles()->first()->id,
            $taskViewer->roles()->first()->id,
        ]);
        $department = Department::query()->create([
            'name' => 'واحد درخواست دستورکار '.Str::upper(Str::random(5)),
            'code' => 'TQ-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $employee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $project = Project::query()->where('is_system_default', true)->where('is_active', true)->orderBy('id')->first();
        if ($project === null) {
            $project = Project::query()->create([
                'name' => 'پروژه عمومی سازمان',
                'code' => 'GEN-'.Str::upper(Str::random(6)),
                'status' => 'active',
                'manager_id' => null,
                'created_by' => null,
                'is_active' => true,
                'is_system_default' => true,
            ]);
        }
        $section = ProjectSection::query()->firstOrCreate([
            'project_id' => $project->id,
            'department_id' => $department->id,
        ], [
            'name' => $department->name,
            'manager_can_create_tasks' => true,
            'sort_order' => 1,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'درخواست دستورکار در پروژه عمومی',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'assigned_to_employee',
            'recipient_department_id' => $department->id,
            'current_department_id' => $department->id,
            'current_user_id' => $employee->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($employee)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::EMPLOYEE_REQUEST_TASK,
            'note' => 'برای پیگیری نامه به یک دستورکار در بخش واحد نیاز است.',
        ])->assertRedirect();

        $this->actingAs($manager)->post(route('correspondence.actions.store', $letter), [
            'action_type' => CorrespondenceAction::MANAGER_CREATE_TASK,
            'note' => 'درخواست دستورکار تأیید شد؛ از فرم موجود وظایف استفاده می‌شود.',
        ])->assertRedirect();

        $letter->refresh();
        $this->assertSame('in_progress', $letter->workflow_state);
        $this->assertSame((int) $manager->id, (int) $letter->current_user_id);
        $this->actingAs($manager)->get(route('correspondence.show', $letter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canCreateTaskInstruction', true)
                ->where('taskInstructionUrl', fn ($url) => is_string($url)
                    && str_contains($url, 'create_for_correspondence='.$letter->id)
                    && str_contains($url, 'project_id='.$project->id)
                    && str_contains($url, 'section_id='.$section->id)));
    }

    public function test_manager_sees_employee_request_in_needs_action_filter(): void
    {
        $manager = $this->userWithPermission('correspondence.view');
        $employee = $this->userWithPermission('correspondence.view');
        $department = Department::query()->create([
            'name' => 'واحد فیلتر درخواست مدیر '.Str::upper(Str::random(5)),
            'code' => 'MF-'.Str::upper(Str::random(6)),
            'manager_id' => $manager->id,
            'is_active' => true,
        ]);
        $employee->departments()->attach($department->id, ['is_primary' => true, 'joined_at' => now()]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'درخواست در انتظار اقدام مدیر',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'general_review_pending_manager',
            'recipient_department_id' => $department->id,
            'current_department_id' => $department->id,
            'current_user_id' => $manager->id,
            'created_by' => $employee->id,
        ]);
        CorrespondenceAction::query()->create([
            'correspondence_id' => $letter->id,
            'actor_id' => $employee->id,
            'action_type' => CorrespondenceAction::EMPLOYEE_REQUEST_TASK,
            'from_department_id' => $department->id,
            'to_department_id' => $department->id,
            'from_user_id' => $employee->id,
            'to_user_id' => $manager->id,
        ]);

        $this->actingAs($manager)->get(route('correspondence.index', ['scope' => 'mine', 'action_filter' => 'needs_action']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.action_filter', 'needs_action')
                ->where('correspondences.total', 1)
                ->where('correspondences.data.0.id', $letter->id));
    }



    public function test_department_manager_sees_letters_registered_by_team_without_owning_the_next_action(): void
    {
        $secretariatManager = $this->userWithPermission('correspondence.view');
        $registrar = $this->userWithPermission('correspondence.view');
        $recipientManager = $this->userWithPermission('correspondence.view');
        $secretariat = Department::query()->create([
            'name' => 'واحد دبیرخانه کارتابل '.Str::upper(Str::random(5)),
            'code' => 'SM-'.Str::upper(Str::random(6)),
            'manager_id' => $secretariatManager->id,
            'is_active' => true,
        ]);
        $registrar->departments()->attach($secretariat->id, ['is_primary' => true, 'joined_at' => now()]);
        $recipient = Department::query()->create([
            'name' => 'واحد گیرنده کارتابل '.Str::upper(Str::random(5)),
            'code' => 'RM-'.Str::upper(Str::random(6)),
            'manager_id' => $recipientManager->id,
            'is_active' => true,
        ]);
        $letter = Correspondence::query()->create([
            'type' => Correspondence::TYPE_INCOMING,
            'classification' => Correspondence::CLASSIFICATION_ORDINARY,
            'subject' => 'نامه ثبت‌شده توسط کارمند دبیرخانه',
            'status' => Correspondence::STATUS_REGISTERED,
            'workflow_state' => 'in_progress',
            'registered_by' => $registrar->id,
            'current_department_id' => $recipient->id,
            'current_user_id' => $recipientManager->id,
            'recipient_department_id' => $recipient->id,
            'created_by' => $registrar->id,
        ]);

        $this->actingAs($secretariatManager)->get(route('correspondence.index', [
            'scope' => 'mine',
            'action_filter' => 'all',
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('correspondences.total', 1)
            ->where('correspondences.data.0.id', $letter->id));

        $this->actingAs($secretariatManager)->get(route('correspondence.index', [
            'scope' => 'mine',
            'action_filter' => 'needs_action',
        ]))->assertOk()->assertInertia(fn ($page) => $page
            ->where('correspondences.total', 0));
    }

}
