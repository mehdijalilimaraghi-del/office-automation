<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use App\Core\Audit\Contracts\AuditServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateUserAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function execute(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
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
