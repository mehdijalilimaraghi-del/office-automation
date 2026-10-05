<?php

namespace App\Core\ExternalParties\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasSystemCode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Core\CustomFields\Models\CustomFieldValue;

class ExternalContact extends Model
{
    use HasSystemCode;

    protected static function systemCodePrefix(): string
    {
        return 'EXTCNT';
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', self::class);
    }

    protected $fillable = ['code', 'external_organization_id', 'external_department_id', 'name', 'position', 'mobile', 'phone', 'extension', 'email', 'notes', 'status'];

    public function organization(): BelongsTo { return $this->belongsTo(ExternalOrganization::class, 'external_organization_id'); }
    public function department(): BelongsTo { return $this->belongsTo(ExternalDepartment::class, 'external_department_id'); }
}