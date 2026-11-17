<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveCustodyEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'action', 'location', 'recipient', 'due_on', 'note'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
