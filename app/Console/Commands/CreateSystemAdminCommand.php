<?php

namespace App\Console\Commands;

use App\Core\Roles\Models\Role;
use App\Models\User;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\CoreRoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateSystemAdminCommand extends Command
{
    protected $signature = 'office:create-system-admin
        {username=systemadmin : Username of the system bootstrap administrator}
        {--email= : Optional technical email address}
        {--password= : Password; when omitted, it is requested securely}';

    protected $description = 'Create or reset the standalone system bootstrap administrator';

    public function handle(): int
    {
        $username = strtolower(trim((string) $this->argument('username')));

        if (! preg_match('/^[a-z][a-z0-9_.-]{2,49}$/', $username)) {
            $this->error('نام کاربری باید با حرف انگلیسی شروع شود و فقط شامل حروف، اعداد، نقطه، خط تیره یا زیرخط باشد.');

            return self::FAILURE;
        }

        $password = (string) $this->option('password');

        if ($password === '') {
            $password = (string) $this->secret('رمز عبور مدیر سیستم را وارد کنید');

            if ($password === '') {
                $this->error('رمز عبور نمی‌تواند خالی باشد.');

                return self::FAILURE;
            }
        }

        $email = strtolower(trim((string) $this->option('email')));

        if ($email === '') {
            $email = $username.'@system.local';
        }

        $this->callSilent('db:seed', [
            '--class' => CorePermissionSeeder::class,
            '--force' => true,
        ]);

        $this->callSilent('db:seed', [
            '--class' => CoreRoleSeeder::class,
            '--force' => true,
        ]);

        $role = Role::query()
            ->where('code', 'SYSTEM_ADMIN')
            ->where('is_active', true)
            ->firstOrFail();

        $user = User::query()
            ->where('username', $username)
            ->first();

        if ($user === null) {
            if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                $email = $username.'+'.Str::lower(Str::random(8)).'@system.local';
            }

            $user = new User();
        }

        $user->fill([
            'name' => 'مدیر اصلی سیستم',
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
        ]);
        $user->save();

        $user->roles()->sync([$role->id]);

        // The system bootstrap account is deliberately outside the organization.
        $user->departmentMemberships()->delete();
        $user->positionAssignments()->delete();
        $user->projects()->detach();

        $this->info("مدیر اصلی سیستم با نام کاربری «{$username}» ایجاد/به‌روزرسانی شد.");
        $this->line('این حساب عضو هیچ واحد سازمانی یا پروژه‌ای نیست و نقش SYSTEM_ADMIN دارد.');

        return self::SUCCESS;
    }
}
