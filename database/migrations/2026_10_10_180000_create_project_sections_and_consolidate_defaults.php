<?php

use App\Modules\Projects\Models\Project;
use App\Modules\Projects\Models\ProjectSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            // Historical mapping only: intentionally no FK to projects because SQL Server rejects
            // the additional cascade path alongside project_id's cascading foreign key.
            $table->unsignedBigInteger('legacy_project_id')->nullable()->index();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('manager_can_create_tasks')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'sort_order']);
            $table->index('department_id');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            // Keep section_id indexed but without a database FK: SQL Server rejects
            // the extra SET NULL cascade path through tasks.project_id -> projects.
            $table->unsignedBigInteger('section_id')->nullable()->after('project_id');
            $table->index('section_id');
        });

        $defaults = DB::table('projects')
            ->where('is_system_default', true)
            ->orderBy('id')
            ->get();

        if ($defaults->isEmpty()) {
            $departments = DB::table('departments')->orderBy('id')->get();
            if ($departments->isEmpty()) {
                return;
            }

            $code = 'PRJ-GENERAL';
            while (DB::table('projects')->where('code', $code)->exists()) {
                $code .= '-SHARED';
            }

            $sharedId = (int) DB::table('projects')->insertGetId([
                'department_id' => null,
                'name' => 'پروژه عمومی سازمان',
                'code' => $code,
                'description' => 'پروژه عمومی مشترک سازمان برای کارهای بین‌واحدی',
                'status' => 'active',
                'start_date' => null,
                'end_date' => null,
                'manager_id' => null,
                'created_by' => null,
                'is_active' => true,
                'is_system_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($departments as $index => $department) {
                DB::table('project_sections')->insert([
                    'project_id' => $sharedId,
                    'department_id' => $department->id,
                    'legacy_project_id' => null,
                    'name' => $department->name,
                    'description' => 'بخش عمومی فعالیت‌های واحد '.$department->name,
                    'manager_can_create_tasks' => true,
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        $sharedId = (int) $defaults->first()->id;
        DB::table('projects')->where('id', $sharedId)->update([
            'department_id' => null,
            'name' => 'پروژه عمومی سازمان',
            'description' => 'پروژه عمومی مشترک سازمان برای کارهای بین‌واحدی',
            'manager_id' => null,
            'created_by' => null,
            'is_active' => true,
            'is_system_default' => true,
            'updated_at' => now(),
        ]);

        foreach ($defaults as $index => $legacyProject) {
            $department = $legacyProject->department_id
                ? DB::table('departments')->where('id', $legacyProject->department_id)->first()
                : null;
            $sectionName = $department?->name
                ?: trim(str_replace('پروژه عمومی', '', (string) $legacyProject->name))
                ?: 'بخش '.($index + 1);

            $sectionId = (int) DB::table('project_sections')->insertGetId([
                'project_id' => $sharedId,
                'department_id' => $legacyProject->department_id,
                'legacy_project_id' => $legacyProject->id,
                'name' => $sectionName,
                'description' => $legacyProject->description,
                'manager_can_create_tasks' => true,
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tasks')->where('project_id', $legacyProject->id)->update([
                'project_id' => $sharedId,
                'section_id' => $sectionId,
            ]);

            if ($legacyProject->department_id !== null) {
                DB::table('tasks')
                    ->where('project_id', $sharedId)
                    ->where('section_id', $sectionId)
                    ->whereNull('department_id')
                    ->update(['department_id' => $legacyProject->department_id]);
            }

            DB::table('workflow_instances')
                ->where('project_id', $legacyProject->id)
                ->update(['project_id' => $sharedId]);

            DB::table('document_links')
                ->where('linkable_type', Project::class)
                ->where('linkable_id', $legacyProject->id)
                ->update([
                    'linkable_type' => ProjectSection::class,
                    'linkable_id' => $sectionId,
                ]);

            if ((int) $legacyProject->id !== $sharedId) {
                $members = DB::table('project_user')
                    ->where('project_id', $legacyProject->id)
                    ->get(['user_id', 'created_at', 'updated_at']);

                foreach ($members as $member) {
                    DB::table('project_user')->insertOrIgnore([
                        'project_id' => $sharedId,
                        'user_id' => $member->user_id,
                        'project_role_id' => null,
                        'created_at' => $member->created_at,
                        'updated_at' => $member->updated_at,
                    ]);
                }

                // Preserve the old default project as inactive for historical references,
                // but remove it from ordinary project selection.
                DB::table('projects')->where('id', $legacyProject->id)->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
            }
        }

        $sortOrder = (int) DB::table('project_sections')->where('project_id', $sharedId)->max('sort_order');
        foreach (DB::table('departments')->orderBy('id')->get() as $department) {
            $exists = DB::table('project_sections')
                ->where('project_id', $sharedId)
                ->where('department_id', $department->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $sortOrder++;
            DB::table('project_sections')->insert([
                'project_id' => $sharedId,
                'department_id' => $department->id,
                'legacy_project_id' => null,
                'name' => $department->name,
                'description' => 'بخش عمومی فعالیت‌های واحد '.$department->name,
                'manager_can_create_tasks' => true,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('project_user')->where('project_id', $sharedId)->update(['project_role_id' => null]);
    }

    public function down(): void
    {
        if (Schema::hasTable('project_sections')) {
            foreach (DB::table('project_sections')->orderBy('id')->get() as $section) {
                $restoreProjectId = $section->legacy_project_id ?: $section->project_id;

                DB::table('tasks')->where('section_id', $section->id)->update([
                    'project_id' => $restoreProjectId,
                    'section_id' => null,
                ]);

                DB::table('document_links')
                    ->where('linkable_type', ProjectSection::class)
                    ->where('linkable_id', $section->id)
                    ->update([
                        'linkable_type' => Project::class,
                        'linkable_id' => $restoreProjectId,
                    ]);
            }
        }

        Schema::table('tasks', function (Blueprint $table): void {
            // section_id intentionally has no foreign key on SQL Server.
            $table->dropIndex(['section_id']);
            $table->dropColumn('section_id');
        });

        Schema::dropIfExists('project_sections');
        // Legacy project rows are retained; workflow instances remain associated with the shared project.
    }
};
