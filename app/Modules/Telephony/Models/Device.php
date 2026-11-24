<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'telephony_devices';

    protected $guarded = [];

    protected $hidden = ['token_hash', 'pairing_hash'];

    protected function casts(): array
    {
        return ['pairing_expires_at' => 'datetime', 'paired_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
