<?php

declare(strict_types=1);

namespace App\Modules\Correspondence\Policies;

use App\Models\User;
use App\Modules\Correspondence\Models\Correspondence;
use App\Modules\Correspondence\Services\CorrespondenceAccessService;

class CorrespondencePolicy
{
    public function view(User $user, Correspondence $correspondence): bool
    {
        return app(CorrespondenceAccessService::class)->canView($correspondence, $user);
    }
}
