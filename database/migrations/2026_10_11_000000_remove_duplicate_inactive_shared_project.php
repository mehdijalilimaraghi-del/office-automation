<?php

declare(strict_types=1);

use App\\Modules\\Projects\\Models\\Project;
use Illuminate\\Database\\Migrations\\Migration;

return new class extends Migration
{
    /**
     * Remove the known inactive duplicate of the shared organization project.
     */
    public function up(): void
    {
        Project::query()
            ->where('code', 'PRJ-000006')
            ->where('name', 'پروژه عمومی سازمان')
            ->where('is_active', false)
            ->first()
            ?->delete();
    }

    public function down(): void
    {
        // The removed record is application data and cannot be reconstructed safely.
    }
};
