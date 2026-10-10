<?php
declare(strict_types=1);
namespace App\Modules\Projects\Models;
use App\Core\Departments\Models\Department;
use App\Modules\Documents\Models\DocumentLink;
use App\Modules\Tasks\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
class ProjectSection extends Model
{
    protected $fillable = ['project_id', 'department_id', 'name', 'description', 'manager_can_create_tasks', 'sort_order'];
    protected function casts(): array { return ['manager_can_create_tasks' => 'boolean', 'sort_order' => 'integer']; }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function tasks(): HasMany { return $this->hasMany(Task::class, 'section_id'); }
    public function documents(): MorphMany { return $this->morphMany(DocumentLink::class, 'linkable'); }
}
