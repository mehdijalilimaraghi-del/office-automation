<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveReference extends Model
{
    protected $fillable = ['target_type', 'target_id', 'created_by'];
}
