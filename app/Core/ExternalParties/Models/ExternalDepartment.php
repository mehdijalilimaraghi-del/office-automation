<?php

namespace App\Core\ExternalParties\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSystemCode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalDepartment extends Model
{
    use HasSystemCode;

    protected static function systemCodePrefix(): string
    {
        return 'EXTDPT';
    }

    protected $fillable = ['external_organization_id', 'parent_id', 'name', 'code', 'phone', 'extension', 'email', 'status'];

    public function organization(): BelongsTo { return $this->belongsTo(ExternalOrganization::class, 'external_organization_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function contacts(): HasMany { return $this->hasMany(ExternalContact::class); }
}