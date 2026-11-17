<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Models;

use Illuminate\Database\Eloquent\Model;

class CorrespondenceBodyTemplate extends Model
{
    protected $fillable = ['user_id', 'title', 'body'];
}
