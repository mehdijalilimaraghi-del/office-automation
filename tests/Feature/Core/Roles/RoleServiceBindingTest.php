<?php

namespace Tests\Feature\Core\Roles;

use App\Core\Roles\Contracts\RoleServiceInterface;
use App\Core\Roles\Services\RoleService;
use Tests\TestCase;

class RoleServiceBindingTest extends TestCase
{
    public function test_role_service_interface_is_bound_to_its_implementation(): void
    {
        $service = $this->app->make(
            RoleServiceInterface::class
        );

        $this->assertInstanceOf(
            RoleService::class,
            $service
        );
    }
}