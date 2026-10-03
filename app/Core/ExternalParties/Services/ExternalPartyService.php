<?php

namespace App\\Core\\ExternalParties\\Services;

use App\\Core\\ExternalParties\\Contracts\\ExternalPartyServiceInterface;
use App\\Core\\ExternalParties\\Models\\{ExternalContact, ExternalDepartment, ExternalOrganization};
use Illuminate\\Database\\Eloquent\\Collection;

class ExternalPartyService implements ExternalPartyServiceInterface
{
    public function organizations(): Collection { return ExternalOrganization::query()->withCount(['departments','contacts'])->orderBy('name')->get(); }
    public function departments(): Collection { return ExternalDepartment::query()->with('organization:id,name')->orderBy('name')->get(); }
    public function contacts(): Collection { return ExternalContact::query()->with(['organization:id,name','department:id,name'])->orderBy('name')->get(); }

    public function createOrganization(array $data): ExternalOrganization { return ExternalOrganization::create($data); }
    public function updateOrganization(ExternalOrganization $organization, array $data): ExternalOrganization { $organization->update($data); return $organization->refresh(); }
    public function deleteOrganization(ExternalOrganization $organization): void { $organization->delete(); }
    public function createDepartment(array $data): ExternalDepartment { return ExternalDepartment::create($data); }
    public function updateDepartment(ExternalDepartment $department, array $data): ExternalDepartment { $department->update($data); return $department->refresh(); }
    public function deleteDepartment(ExternalDepartment $department): void { $department->delete(); }
    public function createContact(array $data): ExternalContact { return ExternalContact::create($data); }
    public function updateContact(ExternalContact $contact, array $data): ExternalContact { $contact->update($data); return $contact->refresh(); }
    public function deleteContact(ExternalContact $contact): void { $contact->delete(); }
}