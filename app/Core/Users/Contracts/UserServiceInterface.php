<?php

namespace App\Core\Users\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserServiceInterface
{
    public function create(array $data): User;

    public function update(User $user, array $data): User;

    public function delete(User $user): void;

    public function toggleStatus(User $user): User;

    public function syncRoles(User $user, array $roleIds): User;

    public function list(): Collection;

    public function activeList(): Collection;
}