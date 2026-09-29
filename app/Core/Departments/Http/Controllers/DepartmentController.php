<?php

namespace App\Core\Departments\Http\Controllers;

use App\Core\Departments\Contracts\DepartmentServiceInterface;
use App\Core\Departments\Models\Department;
use App\Core\Users\Contracts\UserServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Departments\StoreDepartmentRequest;
use App\Http\Requests\Core\Departments\UpdateDepartmentRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentServiceInterface $departmentService,
        private readonly UserServiceInterface $userService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Core/Departments/Index', [
            'departments' => $this->departmentService->list(),
            'users' => $this->userService->activeList(),
        ]);
    }

    public function store(
        StoreDepartmentRequest $request
    ): RedirectResponse {
        $this->departmentService->create(
            $request->validated()
        );

        return back()->with(
            'success',
            'دپارتمان با موفقیت ایجاد شد.'
        );
    }

    public function update(
        UpdateDepartmentRequest $request,
        Department $department
    ): RedirectResponse {
        $this->departmentService->update(
            $department,
            $request->validated()
        );

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
