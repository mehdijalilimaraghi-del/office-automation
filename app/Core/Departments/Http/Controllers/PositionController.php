<?php

namespace App\Core\Departments\Http\Controllers;

use App\Core\Departments\Actions\AssignPositionAction;
use App\Core\Departments\Actions\CreatePositionAction;
use App\Core\Departments\Actions\DeletePositionAction;
use App\Core\Departments\Actions\UnassignPositionAction;
use App\Core\Departments\Actions\UpdatePositionAction;
use App\Core\Departments\Models\Department;
use App\Core\Departments\Models\Position;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Departments\AssignPositionRequest;
use App\Http\Requests\Core\Departments\StorePositionRequest;
use App\Http\Requests\Core\Departments\UpdatePositionRequest;
use Illuminate\Http\RedirectResponse;

class PositionController extends Controller
{
    public function __construct(
        private readonly CreatePositionAction $create,
        private readonly UpdatePositionAction $update,
        private readonly DeletePositionAction $delete,
        private readonly AssignPositionAction $assign,
        private readonly UnassignPositionAction $unassign,
    ) {}

    public function store(StorePositionRequest $request, Department $department): RedirectResponse
    {
        $this->create->execute($department, $request->validated());
        return back()->with('success', 'سمت با موفقیت ایجاد شد.');
    }

    public function update(UpdatePositionRequest $request, Department $department, Position $position): RedirectResponse
    {
        abort_unless($position->department_id === $department->id, 404);
        $this->update->execute($position, $request->validated());
        return back()->with('success', 'سمت با موفقیت ویرایش شد.');
    }

    public function destroy(Department $department, Position $position): RedirectResponse
    {
        abort_unless($position->department_id === $department->id, 404);
        $this->delete->execute($position);
        return back()->with('success', 'سمت با موفقیت حذف شد.');
    }

    public function assign(AssignPositionRequest $request, Department $department, Position $position): RedirectResponse
    {
        abort_unless($position->department_id === $department->id, 404);
        $user = User::query()->findOrFail((int) $request->validated('user_id'));
        $this->assign->execute($position, $user, (bool) ($request->validated('is_primary') ?? false));
        return back()->with('success', 'کاربر با موفقیت به سمت تخصیص یافت.');
    }

    public function unassign(Department $department, Position $position, int $user): RedirectResponse
    {
        abort_unless($position->department_id === $department->id, 404);
        $model = User::query()->findOrFail($user);
        $this->unassign->execute($position, $model);
        return back()->with('success', 'تخصیص سمت حذف شد.');
    }
}