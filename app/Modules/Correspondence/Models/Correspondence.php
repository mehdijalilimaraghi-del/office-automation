<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Models;

use App\Core\ExternalParties\Models\ExternalContact;
use App\Core\ExternalParties\Models\ExternalDepartment;
use App\Core\ExternalParties\Models\ExternalOrganization;
use App\Models\User;
use App\Modules\Documents\Models\DocumentLink;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Correspondence extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_number',
        'type',
        'status',
        'registered_at',
        'subject',
        'body',
        'sender_organization_id',
        'sender_department_id',
        'sender_contact_id',
        'recipient_organization_id',
        'recipient_department_id',
        'recipient_contact_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['registered_at' => 'date'];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function senderOrganization(): BelongsTo { return $this->belongsTo(ExternalOrganization::class, 'sender_organization_id'); }
    public function senderDepartment(): BelongsTo { return $this->belongsTo(ExternalDepartment::class, 'sender_department_id'); }
    public function senderContact(): BelongsTo { return $this->belongsTo(ExternalContact::class, 'sender_contact_id'); }
    public function recipientOrganization(): BelongsTo { return $this->belongsTo(ExternalOrganization::class, 'recipient_organization_id'); }
    public function recipientDepartment(): BelongsTo { return $this->belongsTo(ExternalDepartment::class, 'recipient_department_id'); }
    public function recipientContact(): BelongsTo { return $this->belongsTo(ExternalContact::class, 'recipient_contact_id'); }
    public function referrals(): HasMany { return $this->hasMany(CorrespondenceReferral::class); }
    public function documents(): MorphMany { return $this->morphMany(DocumentLink::class, 'linkable'); }
}