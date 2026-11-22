<?php

declare(strict_types=1);

namespace App\Modules\Telephony\Models;

use Illuminate\Database\Eloquent\Model;

class Extension extends Model
{
    protected $table = 'telephony_extensions';

    protected $fillable = ['number', 'label', 'owner_type', 'user_id', 'department_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'user_id' => 'integer', 'department_id' => 'integer'];
    }
}
