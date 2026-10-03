<?php

namespace App\Console\Commands;

use App\Core\Roles\Models\Role;
use App\Models\User;
use Database\Seeders\CorePermissionSeeder;
use Database\Seeders\CoreRoleSeeder;
use Illuminate\Console\Command;

class BootstrapAdminCommand extends Command
{
    protected $signature = 'office:bootstrap-admin {email : Email address of an existing active user}';

    protected $description = 'Grant the system administrator role to an existing active user';

    public function handle(): int
    {
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $this->argument('email')))])
            ->first();

        if ($user === null) {
            $this->error('کاربر موردنظر پیدا نشد.');

            return self::FAILURE;
        }

        if (! $user->isActive()) {
            $this->error('کاربر موردنظر غیرفعال است.');

            return self::FAILURE;
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

        $user->roles()->syncWithoutDetaching([$role->id]);

        $this->info("نقش مدیر سیستم به «{$user->name}» اختصاص داده شد.");

        return self::SUCCESS;
    }
}
