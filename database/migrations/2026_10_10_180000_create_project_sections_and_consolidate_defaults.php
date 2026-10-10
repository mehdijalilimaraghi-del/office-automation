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
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('manager_can_create_tasks')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['project_id', 'sort_order']);
            $table->index('department_id');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('section_id')->nullable()->after('project_id')
                ->constrained('project_sections')->nullOnDelete();
            $table->index('section_id');
        });

        $defaults = DB::table('projects')
            ->where('is_system_default', true)
            ->orderBy('id')
            ->get();

        if ($defaults->isEmpty()) {
            DB::table('projects')->insert([
                'department_id' => null,
                'name' => 'پروژه عمومی سازمان',
                'code' => 'PRJ-GENERAL',
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

        DB::table('project_user')->where('project_id', $sharedId)->update(['project_role_id' => null]);
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['section_id']);
            $table->dropIndex(['section_id']);
            $table->dropColumn('section_id');
        });

        Schema::dropIfExists('project_sections');
        // Existing project/task/document data is intentionally not rewritten on rollback.
    }
};
