<?php

namespace App\Core\ExternalParties\Http\Controllers;

use App\Core\CustomFields\Services\CustomFieldService;
use App\Core\ExternalParties\Contracts\ExternalPartyServiceInterface;
use App\Core\ExternalParties\Models\{ExternalContact, ExternalDepartment, ExternalOrganization};
use App\Http\Controllers\Controller;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExternalPartyController extends Controller
{
    public function __construct(
        private readonly ExternalPartyServiceInterface $service,
        private readonly CustomFieldService $customFieldService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Core/ExternalParties/Index', [
            'organizations' => $this->service->organizations(),
            'departments' => $this->service->departments(),
            'contacts' => $this->service->contacts(),
            'canCreate' => Gate::allows('external_parties.create'),
            'customFields' => [
                'organization' => $this->customFieldService->definitions(ExternalOrganization::class),
                'department' => $this->customFieldService->definitions(ExternalDepartment::class),
                'contact' => $this->customFieldService->definitions(ExternalContact::class),
            ],
        ]);
    }

    public function storeOrganization(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $this->service->createOrganization($data);

        return back()->with('success', 'سازمان خارجی ایجاد شد.');
    }

    public function updateOrganization(Request $request, ExternalOrganization $organization): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $this->service->updateOrganization($organization, $data);

        return back()->with('success', 'سازمان خارجی ویرایش شد.');
    }

    public function destroyOrganization(ExternalOrganization $organization): RedirectResponse
    {
        $this->service->deleteOrganization($organization);

        return back()->with('success', 'سازمان خارجی حذف شد.');
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'external_organization_id' => ['required', 'exists:external_organizations,id'],
            'parent_id' => ['nullable', 'exists:external_departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'organizational_title_prefix' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'personal_salutation' => ['nullable', 'string', 'max:100'],
            'professional_title' => ['nullable', 'string', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
            'phone' => ['nullable', 'string', 'max:50'],
            'extension' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $this->service->createDepartment($data);

        return back()->with('success', 'واحد خارجی ایجاد شد.');
    }

    public function updateDepartment(Request $request, ExternalDepartment $department): RedirectResponse
    {
        $data = $request->validate([
            'external_organization_id' => ['required', 'exists:external_organizations,id'],
            'parent_id' => ['nullable', 'exists:external_departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'manager_contact_id' => ['nullable', 'integer', Rule::exists('external_contacts', 'id')->where(fn ($query) => $query->where('external_organization_id', $request->input('external_organization_id'))->where('external_department_id', $department->id)->where('status', 'active'))],
            'organizational_title_prefix' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'personal_salutation' => ['nullable', 'string', 'max:100'],
            'professional_title' => ['nullable', 'string', 'max:100'],
            'custom_fields' => ['nullable', 'array'],
            'phone' => ['nullable', 'string', 'max:50'],
            'extension' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $this->service->updateDepartment($department, $data);

        return back()->with('success', 'واحد خارجی ویرایش شد.');
    }

    public function destroyDepartment(ExternalDepartment $department): RedirectResponse
    {
        $this->service->deleteDepartment($department);

        return back()->with('success', 'واحد خارجی حذف شد.');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'external_organization_id' => ['required', 'exists:external_organizations,id'],
            'external_department_id' => ['nullable', 'exists:external_departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
            'position' => ['nullable', 'string', 'max:255'],
            'organizational_title_prefix' => ['nullable', 'string', 'max:100'],
            'personal_salutation' => ['nullable', 'string', 'max:100'],
            'professional_title' => ['nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'extension' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $this->service->createContact($data);

        return back()->with('success', 'مخاطب خارجی ایجاد شد.');
    }

    public function updateContact(Request $request, ExternalContact $contact): RedirectResponse
    {
        $data = $request->validate([
            'external_organization_id' => ['required', 'exists:external_organizations,id'],
            'external_department_id' => ['nullable', 'exists:external_departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'custom_fields' => ['nullable', 'array'],
            'position' => ['nullable', 'string', 'max:255'],
            'organizational_title_prefix' => ['nullable', 'string', 'max:100'],
            'personal_salutation' => ['nullable', 'string', 'max:100'],
            'professional_title' => ['nullable', 'string', 'max:100'],
            'is_default' => ['sometimes', 'boolean'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'extension' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $this->service->updateContact($contact, $data);

        return back()->with('success', 'مخاطب خارجی ویرایش شد.');
    }

    public function destroyContact(ExternalContact $contact): RedirectResponse
    {
        $this->service->deleteContact($contact);

        return back()->with('success', 'مخاطب خارجی حذف شد.');
    }
}
