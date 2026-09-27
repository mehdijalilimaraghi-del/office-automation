<?php

namespace Tests\Feature\Core\Users;

use App\Core\Users\Contracts\UserServiceInterface;
use App\Core\Users\Services\UserService;
use Tests\TestCase;

class UserServiceBindingTest extends TestCase
{
    public function test_user_service_interface_is_bound(): void
    {
        $service = app(
            UserServiceInterface::class
        );

        $this->assertInstanceOf(
            UserService::class,
            $service
        );
    }
}