<?php

declare(strict_types=1);

namespace App\\Modules\\Documents\\Models;

use App\\Models\\User;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class DocumentAccess extends Model
{
    protected $fillable = [
        'document_id',
        'user_id',
        'access_level',
        'granted_by',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}