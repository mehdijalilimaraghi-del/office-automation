<?php

namespace App\\Core\\ExternalParties\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class ExternalContact extends Model
{
    protected $fillable = ['external_organization_id', 'external_department_id', 'name', 'position', 'mobile', 'phone', 'extension', 'email', 'notes', 'status'];

    public function organization(): BelongsTo { return $this->belongsTo(ExternalOrganization::class, 'external_organization_id'); }
    public function department(): BelongsTo { return $this->belongsTo(ExternalDepartment::class, 'external_department_id'); }
}