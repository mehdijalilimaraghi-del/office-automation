<?php

declare(strict_types=1);

use App\Modules\Projects\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacyProjects = DB::table('projects')
                ->where('is_system_default', true)
                ->whereNotNull('department_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'name']);

            foreach ($legacyProjects as $project) {
                $projectId = (int) $project->id;
                $references = [];

                $checks = [
                    'tasks' => ['table' => 'tasks', 'column' => 'project_id'],
                    'project_sections' => ['table' => 'project_sections', 'column' => 'project_id'],
                    'workflow_instances' => ['table' => 'workflow_instances', 'column' => 'project_id'],
                    'correspondences' => ['table' => 'correspondences', 'column' => 'project_id'],
                ];

                foreach ($checks as $label => $check) {
                    if (Schema::hasTable($check['table'])
                        && Schema::hasColumn($check['table'], $check['column'])
                        && DB::table($check['table'])->where($check['column'], $projectId)->exists()) {
                        $references[] = $label;
                    }
                }

                if (Schema::hasTable('document_links')
                    && DB::table('document_links')
                        ->where('linkable_type', Project::class)
                        ->where('linkable_id', $projectId)
                        ->exists()) {
                    $references[] = 'document_links';
                }

                if (Schema::hasTable('custom_field_values')
                    && DB::table('custom_field_values')
                        ->where('entity_type', Project::class)
                        ->where('entity_id', $projectId)
                        ->exists()) {
                    $references[] = 'custom_field_values';
                }

                if ($references !== []) {
                    throw new RuntimeException(
                        "Cannot remove legacy default project {$projectId} ({$project->name}); references exist in: "
                        .implode(', ', $references)
                    );
                }

                // The legacy projects have no tasks/sections or business records.
                // Remove their membership and role rows explicitly to avoid SQL Server
                // multiple-cascade-path restrictions.
                if (Schema::hasTable('project_user')) {
                    DB::table('project_user')->where('project_id', $projectId)->delete();
                }

                if (Schema::hasTable('project_roles')) {
                    DB::table('project_roles')->where('project_id', $projectId)->delete();
                }

                DB::table('projects')->where('id', $projectId)->delete();
            }
        });
    }

    public function down(): void
    {
        // Deleted legacy project rows cannot be reconstructed without their original metadata.
    }
};
