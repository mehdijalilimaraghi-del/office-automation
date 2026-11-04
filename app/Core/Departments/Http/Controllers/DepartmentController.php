<?php

namespace App\Core\Departments\Http\Controllers;

use App\Core\Departments\Contracts\DepartmentServiceInterface;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Core\Departments\Models\Department;
use App\Core\Users\Contracts\UserServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Departments\StoreDepartmentRequest;
use App\Http\Requests\Core\Departments\UpdateDepartmentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentServiceInterface $departmentService,
        private readonly UserServiceInterface $userService,
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function index(): Response
    {
        $departments = $this->departmentService->list()->map(function (Department $department): Department {
            $department->setAttribute('manager_signature_url', $department->manager_signature_path && $department->manager_signature_active
                ? route('departments.signature', $department)
                : null);
            return $department;
        });

        return Inertia::render('Core/Departments/Index', [
            'departments' => $departments,
            'users' => $this->userService->activeList(),
            'customFields' => [
                'department' => $this->customFieldService->definitions(Department::class),
                'position' => $this->customFieldService->definitions(\App\Core\Departments\Models\Position::class),
            ],
        ]);
    }

    public function store(
        StoreDepartmentRequest $request
    ): RedirectResponse {
        $data = $request->validated();
        unset($data['manager_signature'], $data['remove_manager_signature']);
        if ($request->hasFile('manager_signature')) {
            $data['manager_signature_path'] = $request->file('manager_signature')->store('organization/department-signatures', 'local');
        }
        $data['manager_signature_active'] = (bool) ($data['manager_signature_active'] ?? false)
            && ! empty($data['manager_signature_path']);
        $this->departmentService->create($data);

        return back()->with(
            'success',
            'دپارتمان با موفقیت ایجاد شد.'
        );
    }

    public function update(
        UpdateDepartmentRequest $request,
        Department $department
    ): RedirectResponse {
        $data = $request->validated();
        unset($data['manager_signature'], $data['remove_manager_signature']);
        $oldSignature = $department->manager_signature_path;
        if ($request->boolean('remove_manager_signature')) {
            $data['manager_signature_path'] = null;
            $data['manager_signature_active'] = false;
        } elseif ($request->hasFile('manager_signature')) {
            $data['manager_signature_path'] = $request->file('manager_signature')->store('organization/department-signatures', 'local');
            $data['manager_signature_active'] = (bool) ($data['manager_signature_active'] ?? true);
        }
        if (empty($data['manager_signature_path']) && ! $request->hasFile('manager_signature')) {
            $data['manager_signature_active'] = false;
        }
        $this->departmentService->update($department, $data);
        if ($oldSignature && ($request->boolean('remove_manager_signature') || ($request->hasFile('manager_signature') && $oldSignature !== $data['manager_signature_path']))) {
            Storage::disk('local')->delete($oldSignature);
        }

        return back()->with(
            'success',
            'دپارتمان با موفقیت ویرایش شد.'
        );
    }

    public function destroy(
        Department $department
    ): RedirectResponse {
        $this->departmentService->delete($department);

        return back()->with(
            'success',
            'دپارتمان با موفقیت حذف شد.'
        );
    }

    public function signature(Request $request, Department $department): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('departments.view'), 403);
        abort_unless($department->manager_signature_path && $department->manager_signature_active, 404);
        abort_unless(Storage::disk('local')->exists($department->manager_signature_path), 404);

        return Storage::disk('local')->response($department->manager_signature_path, null, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function toggleStatus(
        Department $department
    ): RedirectResponse {
        $this->departmentService->toggleStatus($department);

        return back()->with(
            'success',
            'وضعیت دپارتمان با موفقیت تغییر کرد.'
        );
    }
}
