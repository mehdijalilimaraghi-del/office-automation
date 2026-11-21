<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Models;

use Illuminate\Database\Eloquent\Model;

class Modem extends Model
{
    protected $table = 'telephony_modems';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['evidence' => 'array', 'voice_advertised' => 'boolean', 'last_seen_at' => 'datetime'];
    }
}
