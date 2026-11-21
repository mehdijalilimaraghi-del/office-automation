<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Models;

use Illuminate\Database\Eloquent\Model;

class Discovery extends Model
{
    protected $table = 'telephony_discoveries';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
