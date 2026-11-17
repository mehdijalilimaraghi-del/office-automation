<?php

declare(strict_types=1);

namespace App\Modules\Projects\Http\Controllers;

use App\Core\Departments\Models\Department;
use App\Http\Controllers\Controller;
use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProjectSectionController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('update', $project);

        if ($project->is_system_default) {
            throw ValidationException::withMessages([
                'section' => 'بخش‌های پروژه عمومی از واحدهای سازمانی ایجاد می‌شوند و امکان ایجاد دستی آن‌ها وجود ندارد.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:10000'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'manager_can_create_tasks' => ['sometimes', 'boolean'],
        ]);

        $department = $this->eligibleDepartment((int) $data['department_id']);
        $project->sections()->create([
            'department_id' => $department->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'manager_can_create_tasks' => (bool) ($data['manager_can_create_tasks'] ?? true),
            'sort_order' => ((int) $project->sections()->max('sort_order')) + 1,
        ]);

        return back()->with('success', 'بخش پروژه ایجاد شد.');
    }

    public function update(Request $request, Project $project, ProjectSection $section): RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_unless((int) $section->project_id === (int) $project->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:10000'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'manager_can_create_tasks' => ['sometimes', 'boolean'],
        ]);

        if ($project->is_system_default) {
            if ((int) $data['department_id'] !== (int) $section->department_id) {
                throw ValidationException::withMessages([
                    'department_id' => 'واحد مسئول بخش پروژه عمومی از ساختار واحدهای سازمانی تعیین می‌شود و قابل تغییر دستی نیست.',
                ]);
            }

            $department = Department::query()->with('manager')->findOrFail($section->department_id);
            $section->update([
                'name' => $department->name,
                'description' => $data['description'] ?? null,
                'manager_can_create_tasks' => $department->is_active
                    && $department->manager !== null
                    && $department->manager->isActive()
                    && (bool) ($data['manager_can_create_tasks'] ?? false),
            ]);

            return back()->with('success', 'تنظیمات بخش پروژه عمومی ویرایش شد.');
        }

        if ((int) $data['department_id'] !== (int) $section->department_id && $section->tasks()->exists()) {
            throw ValidationException::withMessages([
                'department_id' => 'واحد مسئول بخشی که دستورکار دارد قابل تغییر نیست تا سوابق ارجاع حفظ شود.',
            ]);
        }

        $department = $this->eligibleDepartment((int) $data['department_id']);
        $section->update([
            'department_id' => $department->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'manager_can_create_tasks' => (bool) ($data['manager_can_create_tasks'] ?? true),
        ]);

        return back()->with('success', 'بخش پروژه ویرایش شد.');
    }

    public function destroy(Project $project, ProjectSection $section): RedirectResponse
    {
        Gate::authorize('update', $project);
        abort_unless((int) $section->project_id === (int) $project->id, 404);

        if ($project->is_system_default) {
            throw ValidationException::withMessages([
                'section' => 'بخش‌های پروژه عمومی همراه با چرخه عمر واحد سازمانی مدیریت می‌شوند و حذف دستی آن‌ها مجاز نیست.',
            ]);
        }

        if ($section->tasks()->exists() || $section->documents()->exists()) {
            throw ValidationException::withMessages([
                'section' => 'این بخش دارای دستورکار یا سند وابسته است؛ برای حفظ سوابق امکان حذف آن وجود ندارد.',
            ]);
        }

        $section->delete();

        return back()->with('success', 'بخش پروژه حذف شد.');
    }

    private function eligibleDepartment(int $departmentId): Department
    {
        $department = Department::query()
            ->with('manager')
            ->whereKey($departmentId)
            ->where('is_active', true)
            ->first();

        if ($department === null || $department->manager === null || ! $department->manager->isActive()) {
            throw ValidationException::withMessages([
                'department_id' => 'واحد مسئول باید فعال و دارای مدیر فعال باشد.',
            ]);
        }

        return $department;
    }
}
