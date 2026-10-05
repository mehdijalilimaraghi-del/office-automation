<?php

namespace App\Core\Users\Actions;

use App\Models\User;
use App\Core\Audit\Contracts\AuditServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UpdateUserAction
{
    public function __construct(private readonly AuditServiceInterface $audit)
    {
    }

    public function execute(User $user, array $data): User
    {
        if ($user->isInactive() && ($data['status'] ?? $user->status) === 'active') {
            throw ValidationException::withMessages([
                'status' => 'فعال‌سازی کاربر باید از طریق دعوت‌نامه انجام شود.',
            ]);
        }

        if (
            $user->id === auth()->id() &&
            array_key_exists('status', $data) &&
            $data['status'] !== $user->status
        ) {
            throw ValidationException::withMessages([
                'status' => 'وضعیت کاربر جاری قابل تغییر نیست.',
            ]);
        }

        $oldValues = $user->only(['name', 'username', 'email', 'mobile', 'phone', 'extension', 'status']);

        $attributes = [
            'name' => $data['name'],
            'username' => $data['username'],
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

        $profile = $user->profile()->updateOrCreate([], [
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'employee_code' => $data['employee_code'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
        ]);

        if (($data['avatar'] ?? null) instanceof UploadedFile) {
            if ($profile->avatar_path) {
                Storage::disk('public')->delete($profile->avatar_path);
            }

            $profile->avatar_path = $data['avatar']->store('avatars', 'public');
            $profile->save();
        }

        $user->refresh();

        $this->audit->record('user.updated', $user, $oldValues, $user->only(['name', 'username', 'email', 'mobile', 'phone', 'extension', 'status']));

        return $user;
    }
}
