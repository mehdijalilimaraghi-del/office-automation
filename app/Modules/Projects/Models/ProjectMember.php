<?php

namespace App\Modules\Projects\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMember extends Model
{
    use HasFactory;

    protected $table = 'project_user';

    protected $fillable = [
        'project_id',
        'user_id',
        'project_role_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(
            Project::class,
            'project_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function projectRole(): BelongsTo
    {
        return $this->belongsTo(
            ProjectRole::class,
            'project_role_id'
        );
    }
}