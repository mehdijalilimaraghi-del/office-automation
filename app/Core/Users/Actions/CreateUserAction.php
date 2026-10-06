<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use App\Core\Audit\Contracts\AuditServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateUserAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function execute(array $data): User
    {
        $name = trim(implode(' ', array_filter([
            trim((string) ($data['first_name'] ?? '')),
            trim((string) ($data['last_name'] ?? '')),
        ])));

        if ($name === '') {
            $name = trim((string) ($data['name'] ?? ''));
        }

        if ($name === '') {
            throw ValidationException::withMessages([
                'first_name' => 'نام و نام خانوادگی کاربر الزامی است.',
            ]);
        }

        $user = User::create([
            'name' => $name,
            'username' => $data['username'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'phone' => $data['phone'] ?? null,
            'extension' => $data['extension'] ?? null,
            'password' => Hash::make($data['password'] ?? Str::random(40)),
            'status' => $data['status'] ?? 'inactive',
        ]);

        $profile = $user->profile()->create([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'employee_code' => $data['employee_code'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
        ]);

        if (($data['avatar'] ?? null) instanceof UploadedFile) {
            $profile->avatar_path = $data['avatar']->store('avatars', 'public');
            $profile->save();
        }

        $this->audit->record('user.created', $user, null, [
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
        ]);

        return $user;
    }
}
