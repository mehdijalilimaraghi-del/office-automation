<?php

namespace App\Core\Departments\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionAssignment extends Model
{
    protected $fillable = ['position_id','user_id','is_primary','is_active','started_at','ended_at'];
    protected function casts(): array
    {
        return ['is_primary'=>'boolean','is_active'=>'boolean','started_at'=>'datetime','ended_at'=>'datetime'];
    }
    public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}