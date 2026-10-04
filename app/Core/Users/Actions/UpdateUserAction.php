<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateUserAction
{
    public function execute(User $user, array $data): User
    {
        if (
            $user->id === auth()->id() &&
            array_key_exists('status', $data) &&
            $data['status'] !== $user->status
        ) {
            throw ValidationException::withMessages([
                'status' => 'وضعیت کاربر جاری قابل تغییر نیست.',
            ]);
        }

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'phone' => $data['phone'] ?? null,
            'extension' => $data['extension'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];

        if (
            isset($data['password']) &&
            trim($data['password']) !== ''
        ) {
            $attributes['password'] = Hash::make(
                $data['password']
            );
        }

        $user->update($attributes);

        $user->profile()->updateOrCreate([], [
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'employee_code' => $data['employee_code'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
        ]);

        return $user->refresh();
    }
}
