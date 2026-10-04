<?php

namespace App\Core\CustomFields\Http\Controllers;

use App\Core\CustomFields\Actions\CreateCustomFieldAction;
use App\Core\CustomFields\Actions\DeleteCustomFieldAction;
use App\Core\CustomFields\Actions\UpdateCustomFieldAction;
use App\Core\CustomFields\Models\CustomField;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\CustomFields\StoreCustomFieldRequest;
use App\Http\Requests\Core\CustomFields\UpdateCustomFieldRequest;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class CustomFieldController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Core/CustomFields/Index', [
            'fields' => CustomField::query()->orderBy('entity_type')->orderBy('sort_order')->orderBy('label')->get(),
            'entityTypes' => [
                ['value' => \App\Models\User::class, 'label' => 'کاربر'],
            ],
            'types' => \App\Core\CustomFields\Services\CustomFieldService::supportedTypes(),
        ]);
    }

    public function store(StoreCustomFieldRequest $request, CreateCustomFieldAction $action): RedirectResponse
    {
        $action->execute($request->validated());
        return back()->with('success', 'فیلد سفارشی ایجاد شد.');
    }

    public function update(UpdateCustomFieldRequest $request, CustomField $customField, UpdateCustomFieldAction $action): RedirectResponse
    {
        $action->execute($customField, $request->validated());
        return back()->with('success', 'فیلد سفارشی به‌روزرسانی شد.');
    }

    public function destroy(CustomField $customField, DeleteCustomFieldAction $action): RedirectResponse
    {
        $action->execute($customField);
        return back()->with('success', 'فیلد سفارشی حذف شد.');
    }
}
