<?php

declare(strict_types=1);

namespace App\\Modules\\Documents\\Models;

use App\\Models\\User;
use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;
use Illuminate\\Database\\Eloquent\\Relations\\MorphMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'document_type',
        'description',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class);
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(DocumentAccess::class);
    }

    public function linkedTargets(): MorphMany
    {
        return $this->morphMany(DocumentLink::class, 'linkable');
    }

    public function latestVersion(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)
            ->orderByDesc('version_number');
    }
}