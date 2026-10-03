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

    public function createOrganization(array $data): ExternalOrganization { return ExternalOrganization::create($data); }
    public function updateOrganization(ExternalOrganization $organization, array $data): ExternalOrganization { $organization->update($data); return $organization->refresh(); }
    public function deleteOrganization(ExternalOrganization $organization): void { $organization->delete(); }
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
    public function deleteDepartment(ExternalDepartment $department): void { $department->delete(); }
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