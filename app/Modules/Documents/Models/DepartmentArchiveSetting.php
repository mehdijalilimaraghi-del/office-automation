<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use Illuminate\Database\Eloquent\Model;

class DepartmentArchiveSetting extends Model
{
    protected $fillable = ['department_id', 'archivist_id', 'categories', 'locations'];

    protected function casts(): array
    {
        return ['categories' => 'array', 'locations' => 'array'];
    }
}
