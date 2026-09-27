<?php

namespace Tests\Feature\Core\Permissions;

use App\Core\Permissions\Contracts\PermissionServiceInterface;
use App\Core\Permissions\Services\PermissionService;
use Tests\TestCase;

class PermissionServiceBindingTest extends TestCase
{
    public function test_permission_service_interface_is_bound_to_its_implementation(): void
    {
        $service = app(PermissionServiceInterface::class);

        $this->assertInstanceOf(
            PermissionService::class,
            $service
        );
    }
}