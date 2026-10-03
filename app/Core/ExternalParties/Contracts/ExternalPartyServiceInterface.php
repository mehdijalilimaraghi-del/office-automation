<?php

namespace App\Core\ExternalParties\Contracts;

use App\Core\ExternalParties\Models\ExternalContact;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\ExternalParties\Models\ExternalOrganization;
use Illuminate\Database\Eloquent\Collection;

interface ExternalPartyServiceInterface
{
    public function organizations(): Collection;
    public function departments(): Collection;
    public function contacts(): Collection;
    public function createOrganization(array $data): ExternalOrganization;
    public function updateOrganization(ExternalOrganization $organization, array $data): ExternalOrganization;
    public function deleteOrganization(ExternalOrganization $organization): void;
    public function createDepartment(array $data): ExternalDepartment;
    public function updateDepartment(ExternalDepartment $department, array $data): ExternalDepartment;
    public function deleteDepartment(ExternalDepartment $department): void;
    public function createContact(array $data): ExternalContact;
    public function updateContact(ExternalContact $contact, array $data): ExternalContact;
    public function deleteContact(ExternalContact $contact): void;
}