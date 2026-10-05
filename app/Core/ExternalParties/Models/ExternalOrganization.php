<?php

namespace App\Core\ExternalParties\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasSystemCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Core\CustomFields\Models\CustomFieldValue;

class ExternalOrganization extends Model
{
    use HasFactory;
    use HasSystemCode;

    protected static function systemCodePrefix(): string
    {
        return 'EXTORG';
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'entity_id')
            ->where('entity_type', self::class);
    }

    protected $fillable = ['name', 'code', 'phone', 'email', 'address', 'status'];

    public function departments(): HasMany
    {
        return $this->hasMany(ExternalDepartment::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ExternalContact::class);
    }
}