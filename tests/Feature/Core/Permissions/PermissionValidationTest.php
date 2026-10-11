<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Permissions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_creation_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('permissions.store'));
    }

    public function test_permission_validation_is_not_exposed_through_mutation_routes(): void
    {
        $this->assertFalse(Route::has('permissions.store'));
        $this->assertFalse(Route::has('permissions.update'));
    }
}
