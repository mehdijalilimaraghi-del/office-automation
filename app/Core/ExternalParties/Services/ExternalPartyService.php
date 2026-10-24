<?php

namespace App\Core\ExternalParties\Services;

use App\Core\ExternalParties\Contracts\ExternalPartyServiceInterface;
use App\Core\CustomFields\Services\CustomFieldService;
use App\Core\ExternalParties\Models\{ExternalContact, ExternalDepartment, ExternalOrganization};
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalPartyService implements ExternalPartyServiceInterface
{
    public function __construct(
        private readonly CustomFieldService $customFieldService,
    ) {}

    public function organizations(): Collection
    {
        return ExternalOrganization::query()
            ->with(['customFieldValues.field'])
            ->withCount(['departments', 'contacts'])
            ->orderBy('name')
            ->get();
    }

    public function departments(): Collection
    {
        return ExternalDepartment::query()
            ->with(['organization:id,name', 'customFieldValues.field'])
            ->orderBy('name')
            ->get();
    }

    public function contacts(): Collection
    {
        return ExternalContact::query()
            ->with(['organization:id,name', 'department:id,name', 'customFieldValues.field'])
            ->orderBy('name')
            ->get();
    }

    public function validateTaskContext(array $data): void
    {
        $organizationId = ! empty($data['external_organization_id']) ? (int) $data['external_organization_id'] : null;
        $departmentId = ! empty($data['external_department_id']) ? (int) $data['external_department_id'] : null;
        $contactId = ! empty($data['external_contact_id']) ? (int) $data['external_contact_id'] : null;

        if ($organizationId !== null && ! ExternalOrganization::query()
            ->whereKey($organizationId)
            ->where('status', 'active')
            ->exists()) {
            throw ValidationException::withMessages(['external_organization_id' => 'سازمان خارجی انتخاب‌شده فعال نیست یا وجود ندارد.']);
        }

        if ($departmentId !== null) {
            if ($organizationId === null) {
                throw ValidationException::withMessages(['external_department_id' => 'برای انتخاب واحد خارجی، ابتدا سازمان خارجی را انتخاب کنید.']);
            }
            if (! ExternalDepartment::query()
                ->whereKey($departmentId)
                ->where('external_organization_id', $organizationId)
                ->where('status', 'active')
                ->exists()) {
                throw ValidationException::withMessages(['external_department_id' => 'واحد خارجی باید فعال و متعلق به سازمان انتخاب‌شده باشد.']);
            }
        }

        if ($contactId !== null) {
            if ($organizationId === null) {
                throw ValidationException::withMessages(['external_contact_id' => 'برای انتخاب شخص خارجی، ابتدا سازمان خارجی را انتخاب کنید.']);
            }
            $contact = ExternalContact::query()
                ->whereKey($contactId)
                ->where('status', 'active')
                ->first();
            if ($contact === null) {
                throw ValidationException::withMessages(['external_contact_id' => 'شخص خارجی انتخاب‌شده فعال نیست یا وجود ندارد.']);
            }
            if ((int) $contact->external_organization_id !== $organizationId) {
                throw ValidationException::withMessages(['external_contact_id' => 'شخص خارجی انتخاب‌شده متعلق به سازمان انتخاب‌شده نیست.']);
            }
            if ($departmentId !== null && (int) $contact->external_department_id !== $departmentId) {
                throw ValidationException::withMessages(['external_contact_id' => 'شخص خارجی انتخاب‌شده متعلق به واحد انتخاب‌شده نیست.']);
            }
        }
    }

    public function createOrganization(array $data): ExternalOrganization
    {
        return DB::transaction(function () use ($data): ExternalOrganization {
            
                    $customFields = $data['custom_fields'] ?? [];
                    unset($data['code'], $data['custom_fields']);
                    $organization = ExternalOrganization::create($data);
                    $this->customFieldService->sync($organization, $customFields);
                    return $organization->refresh();
        });

    }
    public function updateOrganization(ExternalOrganization $organization, array $data): ExternalOrganization
    {
        return DB::transaction(function () use ($organization, $data): ExternalOrganization {
            
                    $customFields = $data['custom_fields'] ?? [];
                    unset($data['code'], $data['custom_fields']);
                    $organization->update($data);
                    $this->customFieldService->sync($organization, $customFields);
                    return $organization->refresh();
        });

    }
    public function deleteOrganization(ExternalOrganization $organization): void
    {
        if ($organization->departments()->exists()
            || $organization->contacts()->exists()
            || Task::query()->where('external_organization_id', $organization->id)->exists()
            || Correspondence::query()->where('external_organization_id', $organization->id)->exists()) {
            throw ValidationException::withMessages([
                'organization' => 'سازمان خارجی دارای واحد یا شخص است و قابل حذف نیست.',
            ]);
        }

        $organization->delete();
    }
    public function createDepartment(array $data): ExternalDepartment
    {
        return DB::transaction(function () use ($data): ExternalDepartment {
            
                    $this->validateDepartmentParent($data);
                    $customFields = $data['custom_fields'] ?? [];
                    unset($data['code'], $data['custom_fields']);
                    $department = ExternalDepartment::create($data);
                    $this->customFieldService->sync($department, $customFields);
                    return $department->refresh();
        });

    }
    public function updateDepartment(ExternalDepartment $department, array $data): ExternalDepartment
    {
        return DB::transaction(function () use ($department, $data): ExternalDepartment {
            
                    $this->validateDepartmentParent($data, $department->id);
                    if ((int) $department->external_organization_id !== (int) $data['external_organization_id']
                        && ($department->children()->exists() || $department->contacts()->exists())) {
                        throw ValidationException::withMessages([
                            'external_organization_id' => 'برای تغییر سازمان یک واحد، ابتدا زیرواحدها و اشخاص وابسته را منتقل کنید.',
                        ]);
                    }
                    $customFields = $data['custom_fields'] ?? [];
                    unset($data['code'], $data['custom_fields']);
                    $department->update($data);
                    $this->customFieldService->sync($department, $customFields);
                    return $department->refresh();
        });

    }
    public function deleteDepartment(ExternalDepartment $department): void
    {
        if ($department->children()->exists()
            || $department->contacts()->exists()
            || Task::query()->where('external_department_id', $department->id)->exists()
            || Correspondence::query()->where('external_department_id', $department->id)->exists()) {
            throw ValidationException::withMessages([
                'department' => 'واحد خارجی دارای زیرواحد یا شخص است و قابل حذف نیست.',
            ]);
        }

        $department->delete();
    }
    public function createContact(array $data): ExternalContact
    {
        return DB::transaction(function () use ($data): ExternalContact {
            
                    $this->validateContactDepartment($data);
                    $customFields = $data['custom_fields'] ?? [];
                    unset($data['code'], $data['custom_fields']);
                    $contact = ExternalContact::create($data);
                    $this->customFieldService->sync($contact, $customFields);
                    return $contact->refresh();
        });

    }
    public function updateContact(ExternalContact $contact, array $data): ExternalContact
    {
        return DB::transaction(function () use ($contact, $data): ExternalContact {
            
                    $this->validateContactDepartment($data);
                    $customFields = $data['custom_fields'] ?? [];
                    unset($data['code'], $data['custom_fields']);
                    $contact->update($data);
                    $this->customFieldService->sync($contact, $customFields);
                    return $contact->refresh();
        });

    }
    public function deleteContact(ExternalContact $contact): void
    {
        if (DB::table('task_external_contacts')->where('external_contact_id', $contact->id)->exists()) {
            throw ValidationException::withMessages([
                'contact' => 'این شخص خارجی به فعالیت ثبت‌شده متصل است و قابل حذف نیست.',
            ]);
        }

        $contact->delete();
    }

    private function validateDepartmentParent(array $data, ?int $currentId = null): void
    {
        if (empty($data['parent_id'])) {
            return;
        }

        $parentId = (int) $data['parent_id'];
        $organizationId = (int) $data['external_organization_id'];
        $parent = ExternalDepartment::query()
            ->whereKey($parentId)
            ->where('external_organization_id', $organizationId)
            ->first();

        if ($parent === null || ($currentId !== null && $parent->id === $currentId)) {
            throw ValidationException::withMessages([
                'parent_id' => 'واحد بالادست باید متعلق به همان سازمان خارجی باشد و نمی‌تواند خود واحد باشد.',
            ]);
        }

        if ($currentId !== null) {
            $visited = [];
            $cursor = $parent;

            while ($cursor !== null) {
                if ($cursor->id === $currentId || isset($visited[$cursor->id])) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'ساختار واحدهای بیرونی نمی‌تواند چرخه ایجاد کند.',
                    ]);
                }

                $visited[$cursor->id] = true;
                $cursor = $cursor->parent;
            }
        }
    }

    private function validateContactDepartment(array $data): void
    {
        if (empty($data['external_department_id'])) {
            return;
        }

        $valid = ExternalDepartment::query()
            ->whereKey((int) $data['external_department_id'])
            ->where('external_organization_id', (int) $data['external_organization_id'])
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'external_department_id' => 'واحد انتخاب‌شده متعلق به سازمان خارجی دیگری است.',
            ]);
        }
    }
}