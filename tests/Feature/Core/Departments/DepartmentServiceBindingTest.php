<?php

namespace Tests\Feature\Core\Departments;

use App\Core\Departments\Contracts\DepartmentServiceInterface;
use App\Core\Departments\Services\DepartmentService;
use Tests\TestCase;

class DepartmentServiceBindingTest extends TestCase
{
    public function test_department_service_interface_is_bound_to_its_implementation(): void
    {
        $service = $this->app->make(
            DepartmentServiceInterface::class
        );

        $this->assertInstanceOf(
            DepartmentService::class,
            $service
        );
    }
}