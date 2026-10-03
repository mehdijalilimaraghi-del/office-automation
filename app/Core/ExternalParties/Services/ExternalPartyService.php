<?php

namespace App\Core\ExternalParties\Services;

use App\Core\ExternalParties\Contracts\ExternalPartyServiceInterface;
use App\Core\ExternalParties\Models\{ExternalContact, ExternalDepartment, ExternalOrganization};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ExternalPartyService implements ExternalPartyServiceInterface
{
    public function organizations(): Collection { return ExternalOrganization::query()->withCount(['departments','contacts'])->orderBy('name')->get(); }
    public function departments(): Collection { return ExternalDepartment::query()->with('organization:id,name')->orderBy('name')->get(); }
    public function contacts(): Collection { return ExternalContact::query()->with(['organization:id,name','department:id,name'])->orderBy('name')->get(); }

    public function validateTaskContext(array $data): void
    {
        $organizationId = ! empty($data['external_organization_id']) ? (int) $data['external_organization_id'] : null;
        $departmentId = ! empty($data['external_department_id']) ? (int) $data['external_department_id'] : null;
        $contactId = ! empty($data['external_contact_id']) ? (int) $data['external_contact_id'] : null;

        if ($departmentId !== null) {
            if ($organizationId === null) {
                throw ValidationException::withMessages(['external_department_id' => 'برای انتخاب واحد خارجی، ابتدا سازمان خارجی را انتخاب کنید.']);
            }
            if (! ExternalDepartment::query()->whereKey($departmentId)->where('external_organization_id', $organizationId)->exists()) {
                throw ValidationException::withMessages(['external_department_id' => 'واحد خارجی انتخاب‌شده متعلق به سازمان انتخاب‌شده نیست.']);
            }
        }

        if ($contactId !== null) {
            if ($organizationId === null) {
                throw ValidationException::withMessages(['external_contact_id' => 'برای انتخاب شخص خارجی، ابتدا سازمان خارجی را انتخاب کنید.']);
            }
            $contact = ExternalContact::query()->find($contactId);
            if ($contact === null) {
                throw ValidationException::withMessages(['external_contact_id' => 'شخص خارجی انتخاب‌شده یافت نشد.']);
            }
            if ($organizationId !== null && (int) $contact->external_organization_id !== $organizationId) {
                throw ValidationException::withMessages(['external_contact_id' => 'شخص خارجی انتخاب‌شده متعلق به سازمان انتخاب‌شده نیست.']);
            }
            if ($departmentId !== null && (int) $contact->external_department_id !== $departmentId) {
                throw ValidationException::withMessages(['external_contact_id' => 'شخص خارجی انتخاب‌شده متعلق به واحد انتخاب‌شده نیست.']);
            }
        }
    }

    public function createOrganization(array $data): ExternalOrganization { return ExternalOrganization::create($data); }
    public function updateOrganization(ExternalOrganization $organization, array $data): ExternalOrganization { $organization->update($data); return $organization->refresh(); }
    public function deleteOrganization(ExternalOrganization $organization): void
    {
        if ($organization->departments()->exists() || $organization->contacts()->exists()) {
            throw ValidationException::withMessages([
                'organization' => 'سازمان خارجی دارای واحد یا شخص است و قابل حذف نیست.',
            ]);
        }

        $organization->delete();
    }
    public function createDepartment(array $data): ExternalDepartment
    {
        $this->validateDepartmentParent($data);
        return ExternalDepartment::create($data);
    }
    public function updateDepartment(ExternalDepartment $department, array $data): ExternalDepartment
    {
        $this->validateDepartmentParent($data, $department->id);
        $department->update($data);
        return $department->refresh();
    }
    public function deleteDepartment(ExternalDepartment $department): void
    {
        if ($department->children()->exists() || $department->contacts()->exists()) {
            throw ValidationException::withMessages([
                'department' => 'واحد خارجی دارای زیرواحد یا شخص است و قابل حذف نیست.',
            ]);
        }

        $department->delete();
    }
    public function createContact(array $data): ExternalContact
    {
        $this->validateContactDepartment($data);
        return ExternalContact::create($data);
    }
    public function updateContact(ExternalContact $contact, array $data): ExternalContact
    {
        $this->validateContactDepartment($data);
        $contact->update($data);
        return $contact->refresh();
    }
    public function deleteContact(ExternalContact $contact): void { $contact->delete(); }

    private function validateDepartmentParent(array $data, ?int $currentId = null): void
    {
        if (empty($data['parent_id'])) {
            return;
        }

        $query = ExternalDepartment::query()
            ->whereKey((int) $data['parent_id'])
            ->where('external_organization_id', (int) $data['external_organization_id']);

        if ($currentId !== null) {
            $query->where('id', '<>', $currentId);
        }

        if (! $query->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => 'واحد بالادست باید متعلق به همان سازمان خارجی باشد.',
            ]);
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