<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tasks;

use App\Core\Departments\Models\Department;
use App\Core\Permissions\Enums\PermissionScope;
use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectMember;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TaskHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_task_can_create_first_planned_activity(): void
    {
        $creator = $this->userWithPermission('tasks.create');

        $response = $this->actingAs($creator)->post(route('tasks.store'), [
            'title' => 'Task With Next Activity',
            'priority' => 'normal',
            'next_activity_title' => 'دریافت تأیید مالی',
            'next_activity_description' => 'تأیید بودجه دریافت شود.',
            'next_activity_assigned_to' => $creator->id,
        ]);

        $response->assertRedirect();

        $task = Task::query()->where('title', 'Task With Next Activity')->firstOrFail();

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'title' => 'دریافت تأیید مالی',
            'status' => 'pending',
            'activity_type' => 'planned',
            'sequence' => 1,
            'assigned_to' => $creator->id,
        ]);
    }

    public function test_planned_task_activity_can_be_completed(): void
    {
        $user = $this->userWithPermission('tasks.update');
        $task = $this->createTask($this->createProject($user), $user);
        $activity = $task->activities()->create([
            'title' => 'پیگیری با واحد مالی',
            'status' => 'pending',
            'activity_type' => 'planned',
            'sequence' => 1,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch(route('tasks.activities.complete', [$task, $activity]))
            ->assertRedirect();

        $this->assertDatabaseHas('task_activities', [
            'id' => $activity->id,
            'task_id' => $task->id,
            'title' => 'پیگیری با واحد مالی',
            'status' => 'completed',
            'activity_type' => 'logged',
            'completed_by' => $user->id,
        ]);

        $this->assertNotNull($activity->refresh()->completed_at);
    }

    public function test_manual_completed_activity_is_saved_in_unified_activity_table(): void
    {
        $user = $this->userWithPermission('tasks.log_work');
        $task = $this->createTask($this->createProject($user), $user);

        $this->actingAs($user)
            ->post(route('tasks.work-logs.store', $task), [
                'title' => 'تماس انجام‌شده با مشتری',
                'description' => 'نتیجه تماس ثبت شد.',
                'started_at' => '2026-10-10T09:00',
                'completed_at' => '2026-10-10T09:30',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_activities', [
            'task_id' => $task->id,
            'title' => 'تماس انجام‌شده با مشتری',
            'description' => 'نتیجه تماس ثبت شد.',
            'status' => 'completed',
            'activity_type' => 'logged',
            'created_by' => $user->id,
            'completed_by' => $user->id,
        ]);

        $activity = TaskActivity::query()
            ->where('task_id', $task->id)
            ->where('title', 'تماس انجام‌شده با مشتری')
            ->firstOrFail();

        $this->assertNotNull($activity->started_at);
        $this->assertNotNull($activity->completed_at);
    }

    public function test_work_log_can_be_edited_and_deleted(): void
    {
        $user = $this->userWithPermission('tasks.update');
        $task = $this->createTask($this->createProject($user), $user);
        $workLog = $task->activities()->create([
            'title' => 'گزارش اولیه',
            'description' => 'شرح اولیه',
            'status' => 'completed',
            'activity_type' => 'logged',
            'sequence' => 1,
            'created_by' => $user->id,
            'completed_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->put(route('tasks.activities.update', [$task, $workLog]), [
                'title' => 'گزارش اصلاح‌شده',
                'description' => 'شرح اصلاح‌شده',
                'started_at' => null,
                'completed_at' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_activities', [
            'id' => $workLog->id,
            'task_id' => $task->id,
            'title' => 'گزارش اصلاح‌شده',
            'description' => 'شرح اصلاح‌شده',
            'activity_type' => 'logged',
        ]);

        $this->actingAs($user)
            ->delete(route('tasks.activities.destroy', [$task, $workLog]))
            ->assertRedirect();

        $this->assertDatabaseMissing('task_activities', ['id' => $workLog->id]);
    }

    public function test_task_can_have_related_internal_users_without_changing_assignee(): void
    {
        $assignee = $this->userWithPermission('tasks.update');
        $related = User::factory()->create(['status' => 'active']);
        $task = $this->createTask($this->createProject($assignee), $assignee);

        $this->actingAs($assignee)
            ->put(route('tasks.participants.update', $task), [
                'user_ids' => [$related->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_participants', [
            'task_id' => $task->id,
            'user_id' => $related->id,
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $assignee->id,
        ]);
    }

    public function test_user_with_referral_permission_can_refer_task(): void
    {
        $assignee = $this->userWithPermission('tasks.refer');
        $project = $this->createProject($assignee);
        $newAssignee = User::factory()->create(['status' => 'active']);
        $this->addMember($project, $newAssignee);
        $task = $this->createTask($project, $assignee);

        $this->actingAs($assignee)
            ->patch(route('tasks.refer', $task), [
                'assignee_id' => $newAssignee->id,
                'note' => 'ارجاع برای ادامه پیگیری',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $newAssignee->id,
        ]);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'to_user_id' => $newAssignee->id,
            'type' => 'handoff',
        ]);
    }

    public function test_user_can_refer_default_department_task_to_any_department_member(): void
    {
        $assignee = $this->userWithPermission('tasks.refer');
        $newAssignee = User::factory()->create(['status' => 'active']);

        $department = Department::query()->create([
            'name' => 'واحد تست ارجاع',
            'is_active' => true,
            'manager_id' => $assignee->id,
        ]);

        $department->members()->attach($assignee->id);
        $department->members()->attach($newAssignee->id);

        $project = Project::query()->create([
            'department_id' => $department->id,
            'name' => 'پروژه پیش‌فرض تست',
            'code' => 'DEFAULT-TASK-'.Str::upper(Str::random(8)),
            'status' => 'active',
            'manager_id' => $assignee->id,
            'created_by' => $assignee->id,
            'is_active' => true,
            'is_system_default' => true,
        ]);

        $task = $this->createTask($project, $assignee);

        $this->actingAs($assignee)
            ->patch(route('tasks.refer', $task), [
                'assignee_id' => $newAssignee->id,
                'note' => 'ارجاع به عضو واحد',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $newAssignee->id,
        ]);
    }

    public function test_task_external_parties_can_save_multiple_contacts(): void
    {
        $user = $this->userWithPermission('tasks.update');
        $user->roles()->first()->permissions()->syncWithoutDetaching(
            Permission::query()->where('code', 'tasks.view')->get()
                ->mapWithKeys(fn ($permission) => [$permission->id => ['scope' => 'organization']])
                ->all()
        );
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user);

        $organization = \App\Core\ExternalParties\Models\ExternalOrganization::query()->create([
            'name' => 'سازمان تست',
            'is_active' => true,
        ]);
        $department = \App\Core\ExternalParties\Models\ExternalDepartment::query()->create([
            'external_organization_id' => $organization->id,
            'name' => 'واحد تست',
            'is_active' => true,
        ]);
        $firstContact = \App\Core\ExternalParties\Models\ExternalContact::query()->create([
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => 'شخص اول',
            'position' => 'کارشناس',
            'mobile' => '09120000001',
            'is_active' => true,
        ]);
        $secondContact = \App\Core\ExternalParties\Models\ExternalContact::query()->create([
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => 'شخص دوم',
            'position' => 'مدیر',
            'mobile' => '09120000002',
            'is_active' => true,
        ]);

        $secondOrganization = \App\Core\ExternalParties\Models\ExternalOrganization::query()->create([
            'name' => 'سازمان دوم تست',
            'is_active' => true,
        ]);
        $secondDepartment = \App\Core\ExternalParties\Models\ExternalDepartment::query()->create([
            'external_organization_id' => $secondOrganization->id,
            'name' => 'واحد دوم تست',
            'is_active' => true,
        ]);
        $secondOrganizationContact = \App\Core\ExternalParties\Models\ExternalContact::query()->create([
            'external_organization_id' => $secondOrganization->id,
            'external_department_id' => $secondDepartment->id,
            'name' => 'شخص سازمان دوم',
            'position' => 'مدیر',
            'mobile' => '09120000004',
            'is_active' => true,
        ]);
        $thirdContact = \App\Core\ExternalParties\Models\ExternalContact::query()->create([
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
            'name' => 'شخص سوم',
            'position' => 'رئیس',
            'mobile' => '09120000003',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('tasks.external-parties.update', $task), [
                'external_organization_id' => $organization->id,
                'external_department_id' => $department->id,
                'external_contact_ids' => [$firstContact->id],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->put(route('tasks.external-parties.update', $task), [
                'external_organization_id' => $organization->id,
                'external_department_id' => $department->id,
                'external_contact_ids' => [$secondContact->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_external_contacts', [
            'task_id' => $task->id,
            'external_contact_id' => $firstContact->id,
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
        ]);
        $this->assertDatabaseHas('task_external_contacts', [
            'task_id' => $task->id,
            'external_contact_id' => $secondContact->id,
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
        ]);
        $this->assertDatabaseHas('task_external_contacts', [
            'task_id' => $task->id,
            'external_contact_id' => $firstContact->id,
        ]);
        $this->assertDatabaseHas('task_external_contacts', [
            'task_id' => $task->id,
            'external_contact_id' => $secondContact->id,
        ]);

        $this->actingAs($user)
            ->put(route('tasks.external-parties.update', $task), [
                'external_organization_id' => $secondOrganization->id,
                'external_department_id' => $secondDepartment->id,
                'external_contact_ids' => [$secondOrganizationContact->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_external_contacts', [
            'task_id' => $task->id,
            'external_contact_id' => $secondOrganizationContact->id,
            'external_organization_id' => $secondOrganization->id,
            'external_department_id' => $secondDepartment->id,
        ]);
        $this->assertDatabaseHas('task_external_contacts', [
            'task_id' => $task->id,
            'external_contact_id' => $secondContact->id,
            'external_organization_id' => $organization->id,
            'external_department_id' => $department->id,
        ]);

        $this->actingAs($user)
            ->put(route('tasks.external-parties.update', $task), [
                'external_organization_id' => $organization->id,
                'external_department_id' => $department->id,
                'external_contact_ids' => [$thirdContact->id],
                'replace_external_contact_id' => $firstContact->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('task_external_contacts', [
            'task_id' => $task->id,
            'external_contact_id' => $firstContact->id,
        ]);
        $this->assertDatabaseHas('task_external_contacts', [
            'task_id' => $task->id,
            'external_contact_id' => $thirdContact->id,
        ]);

        $this->actingAs($user)
            ->put(route('tasks.external-parties.update', $task), [
                'external_organization_id' => $organization->id,
                'external_department_id' => $department->id,
                'external_contact_ids' => [],
                'replace_external_contact_id' => $thirdContact->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('task_external_contacts', [
            'task_id' => $task->id,
            'external_contact_id' => $thirdContact->id,
        ]);
        $this->assertDatabaseHas('task_external_contacts', [
            'task_id' => $task->id,
            'external_contact_id' => $secondContact->id,
        ]);

        $response = $this->actingAs($user)->get(route('tasks.show', $task));

        $response->assertInertia(
            fn ($page) => $page
                ->component('Tasks/Show')
                ->has('task.external_contacts', 2)
        );
    }

    public function test_task_comment_can_be_updated_and_deleted(): void
    {
        $user = $this->userWithPermission('tasks.update');
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user);
        $comment = $task->comments()->create([
            'user_id' => $user->id,
            'body' => 'نظر اولیه',
        ]);

        $this->actingAs($user)
            ->put(route('tasks.comments.update', [$task, $comment]), ['body' => 'نظر ویرایش‌شده'])
            ->assertRedirect();

        $this->assertDatabaseHas('task_comments', [
            'id' => $comment->id,
            'body' => 'نظر ویرایش‌شده',
        ]);

        $this->actingAs($user)
            ->delete(route('tasks.comments.destroy', [$task, $comment]))
            ->assertRedirect();

        $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
    }

    public function test_task_reminder_preserves_selected_time_and_can_be_updated_and_deleted(): void
    {
        $user = $this->userWithPermission('tasks.create_reminder');
        $user->roles()->first()->permissions()->syncWithoutDetaching(
            Permission::query()->where('code', 'tasks.update')->get()
                ->mapWithKeys(fn ($permission) => [$permission->id => ['scope' => 'project']])
                ->all()
        );
        $project = $this->createProject($user);
        $task = $this->createTask($project, $user);

        $this->actingAs($user)
            ->post(route('tasks.reminders.store', $task), [
                'user_id' => $user->id,
                'title' => 'یادآوری تست',
                'description' => 'شرح تست',
                'remind_at' => '2026-10-07T14:35:00',
            ])
            ->assertRedirect();

        $reminder = $task->reminders()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('task_reminders', [
            'id' => $reminder->id,
            'remind_at' => '2026-10-07 14:35:00',
        ]);

        $this->actingAs($user)
            ->put(route('tasks.reminders.update', [$task, $reminder]), [
                'title' => 'یادآوری ویرایش‌شده',
                'description' => 'شرح جدید',
                'remind_at' => '2026-10-08T16:20:00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_reminders', [
            'id' => $reminder->id,
            'title' => 'یادآوری ویرایش‌شده',
            'remind_at' => '2026-10-08 16:20:00',
        ]);

        $this->actingAs($user)
            ->delete(route('tasks.reminders.destroy', [$task, $reminder]))
            ->assertRedirect();

        $this->assertDatabaseMissing('task_reminders', ['id' => $reminder->id]);
    }

    public function test_project_scope_filters_task_index_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.view', 'project');
        $visibleProject = $this->createProject($user);
        $visibleTask = $this->createTask($visibleProject, $user);

        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $hiddenTask = $this->createTask($hiddenProject, $hiddenManager);

        $response = $this->actingAs($user)
            ->get(route('tasks.index'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Tasks/Index')
                ->has('tasks.data', 1)
                ->where('tasks.data.0.id', $visibleTask->id)
                ->where('tasks.data.0.title', $visibleTask->title)
        );

        $this->assertNotSame($visibleTask->id, $hiddenTask->id);
    }

    public function test_project_scope_denies_inaccessible_task_show_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.view', 'project');
        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $task = $this->createTask($hiddenProject, $hiddenManager);

        $this->actingAs($user)
            ->get(route('tasks.show', $task))
            ->assertForbidden();
    }

    public function test_project_scope_denies_inaccessible_task_update_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.update', 'project');
        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $task = $this->createTask($hiddenProject, $hiddenManager);

        $this->actingAs($user)
            ->put(route('tasks.update', $task), [
                'title' => 'Should Not Update',
                'priority' => 'urgent',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => $task->title,
        ]);
    }

    public function test_project_scope_denies_inaccessible_task_assignment_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.assign', 'project');
        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $newAssignee = User::factory()->create(['status' => 'active']);
        $this->addMember($hiddenProject, $newAssignee);
        $task = $this->createTask($hiddenProject, $hiddenManager);

        $this->actingAs($user)
            ->patch(route('tasks.assign', $task), [
                'assignee_id' => $newAssignee->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $hiddenManager->id,
        ]);
    }

    public function test_project_scope_denies_inaccessible_task_status_change_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.change_status', 'project');
        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $task = $this->createTask($hiddenProject, $hiddenManager);

        $this->actingAs($user)
            ->patch(route('tasks.change-status', $task), [
                'status' => 'completed',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'todo',
        ]);
    }

    public function test_project_scope_denies_inaccessible_task_delete_at_http_layer(): void
    {
        $user = $this->userWithPermissionScope('tasks.delete', 'project');
        $hiddenManager = User::factory()->create(['status' => 'active']);
        $hiddenProject = $this->createProject($hiddenManager);
        $task = $this->createTask($hiddenProject, $hiddenManager);

        $this->actingAs($user)
            ->delete(route('tasks.destroy', $task))
            ->assertForbidden();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_task_index_supports_server_side_filters_and_pagination(): void
    {
        $user = $this->userWithPermissionScope('tasks.view', 'project');
        $project = $this->createProject($user);

        foreach (range(1, 16) as $index) {
            $task = $this->createTask($project, $user);
            $task->update([
                'title' => $index === 16 ? 'Find This Task' : 'Other Task '.$index,
                'status' => $index === 16 ? 'completed' : 'todo',
                'priority' => $index === 16 ? 'urgent' : 'normal',
            ]);
        }

        $response = $this->actingAs($user)->get(route('tasks.index', [
            'search' => 'Find This',
            'status' => 'completed',
            'priority' => 'urgent',
            'project_id' => $project->id,
        ]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Tasks/Index')
                ->where('tasks.total', 1)
                ->where('tasks.data.0.title', 'Find This Task')
                ->where('filters.search', 'Find This')
                ->where('filters.status', 'completed')
                ->where('filters.priority', 'urgent')
        );

        $allResponse = $this->actingAs($user)->get(route('tasks.index'));
        $allResponse->assertInertia(
            fn ($page) => $page
                ->where('tasks.total', 16)
                ->where('tasks.per_page', 15)
                ->where('tasks.last_page', 2)
        );
    }

    public function test_project_member_can_create_task_through_http_layer(): void
    {
        $creator = $this->userWithPermission('tasks.create');
        $project = $this->createProject($creator);
        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $response = $this->actingAs($creator)->post(
            route('tasks.store'),
            [
                'project_id' => $project->id,
                'title' => 'HTTP Test Task',
                'description' => 'Created through HTTP layer.',
                'assignee_id' => $assignee->id,
                'priority' => 'high',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'HTTP Test Task',
            'assignee_id' => $assignee->id,
            'created_by' => $creator->id,
            'priority' => 'high',
        ]);
    }

    public function test_non_project_member_cannot_create_task(): void
    {
        $user = $this->userWithPermission('tasks.create');
        $manager = User::factory()->create();
        $project = $this->createProject($manager);
        $assignee = User::factory()->create();

        $this->addMember($project, $assignee);

        $this->actingAs($user)
            ->post(route('tasks.store'), [
                'project_id' => $project->id,
                'title' => 'Forbidden Task',
                'assignee_id' => $assignee->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', [
            'title' => 'Forbidden Task',
        ]);
    }

    public function test_creating_completed_task_sets_completed_at(): void
    {
        $creator = $this->userWithPermission('tasks.create');

        $this->actingAs($creator)->post(route('tasks.store'), [
            'title' => 'Completed Task',
            'status' => 'completed',
            'priority' => 'normal',
        ])->assertRedirect();

        $task = Task::query()->where('title', 'Completed Task')->firstOrFail();

        $this->assertSame('completed', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_task_creator_can_update_task(): void
    {
        $creator = $this->userWithPermission('tasks.update');
        $project = $this->createProject($creator);
        $task = $this->createTask($project, $creator);

        $response = $this->actingAs($creator)->put(
            route('tasks.update', $task),
            [
                'title' => 'Updated HTTP Task',
                'description' => 'Updated description.',
                'priority' => 'urgent',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated HTTP Task',
            'priority' => 'urgent',
        ]);
    }

    public function test_assignee_can_change_task_status(): void
    {
        $assignee = $this->userWithPermission('tasks.change_status');
        $project = $this->createProject($assignee);
        $task = $this->createTask($project, $assignee);

        $response = $this->actingAs($assignee)->patch(
            route('tasks.change-status', $task),
            [
                'status' => 'completed',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);

        $this->assertNotNull(
            $task->refresh()->completed_at
        );
    }

    public function test_project_manager_can_assign_task_through_http_layer(): void
    {
        $manager = $this->userWithPermission('tasks.assign');
        $project = $this->createProject($manager);
        $assignee = User::factory()->create([
            'status' => 'active',
        ]);

        $this->addMember($project, $assignee);

        $task = $this->createTask($project, $manager);

        $this->actingAs($manager)
            ->patch(route('tasks.assign', $task), [
                'assignee_id' => $assignee->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'assignee_id' => $assignee->id,
        ]);
    }

    public function test_project_manager_can_delete_task(): void
    {
        $manager = $this->userWithPermission('tasks.delete');
        $project = $this->createProject($manager);
        $task = $this->createTask($project, $manager);

        $this->actingAs($manager)
            ->delete(route('tasks.destroy', $task))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', [
            'id' => $task->id,
        ]);
    }

    private function userWithPermission(string $permissionCode): User
    {
        $permission = Permission::query()
            ->where('code', $permissionCode)
            ->firstOrFail();

        $role = Role::query()->create([
            'name' => 'HTTP Task Role '.Str::upper(Str::random(8)),
            'code' => 'http-task-'.Str::lower(Str::random(12)),
            'description' => 'HTTP task test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id, [
            'scope' => PermissionScope::defaultForPermission($permission->code)->value,
        ]);

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function userWithPermissionScope(string $permissionCode, string $scope): User
    {
        $permission = Permission::query()
            ->where('code', $permissionCode)
            ->firstOrFail();

        $role = Role::query()->create([
            'name' => 'HTTP Task Scope Role '.Str::upper(Str::random(8)),
            'code' => 'http-task-scope-'.Str::lower(Str::random(12)),
            'description' => 'HTTP task scope test role',
            'is_active' => true,
            'is_system' => false,
        ]);

        $role->permissions()->attach($permission->id, [
            'scope' => $scope,
        ]);

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function createProject(User $manager): Project
    {
        $project = Project::query()->create([
            'name' => 'HTTP Task Project',
            'code' => 'HTTP-TASK-'.Str::upper(Str::random(8)),
            'status' => 'active',
            'manager_id' => $manager->id,
            'created_by' => $manager->id,
            'is_active' => true,
        ]);

        $this->addMember($project, $manager);

        return $project;
    }

    private function addMember(Project $project, User $user): void
    {
        ProjectMember::query()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'project_role_id' => null,
        ]);
    }

    private function createTask(Project $project, User $creator): Task
    {
        return Task::query()->create([
            'project_id' => $project->id,
            'title' => 'HTTP Task',
            'description' => 'HTTP task test.',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_id' => $creator->id,
            'created_by' => $creator->id,
            'is_active' => true,
        ]);
    }
}
