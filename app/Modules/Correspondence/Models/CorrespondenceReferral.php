<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceReferral extends Model
{
    protected $fillable = [
        'correspondence_id',
        'from_user_id',
        'to_user_id',
        'referred_by',
        'instruction',
        'referred_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'referred_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function correspondence(): BelongsTo { return $this->belongsTo(Correspondence::class); }
    public function fromUser(): BelongsTo { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser(): BelongsTo { return $this->belongsTo(User::class, 'to_user_id'); }
    public function referredBy(): BelongsTo { return $this->belongsTo(User::class, 'referred_by'); }
}