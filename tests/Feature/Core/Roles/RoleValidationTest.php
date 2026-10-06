<?php

namespace Tests\Feature\Core\Roles;

use App\Http\Requests\Core\Roles\StoreRoleRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RoleValidationTest extends TestCase
{
    public function test_name_is_required(): void
    {
        $validator = Validator::make(
            [],
            (new StoreRoleRequest())->rules()
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'name',
            $validator->errors()->toArray()
        );
    }

    public function test_name_cannot_exceed_100_characters(): void
    {
        $validator = Validator::make(
            [
                'name' => str_repeat('a', 101),
            ],
            (new StoreRoleRequest())->rules()
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'name',
            $validator->errors()->toArray()
        );
    }

    public function test_code_is_not_required_for_role_creation(): void
    {
        $validator = Validator::make(
            [
                'name' => 'مدیر پروژه',
            ],
            (new StoreRoleRequest())->rules()
        );

        $this->assertFalse($validator->fails());
    }

    public function test_valid_role_data_passes_validation(): void
    {
        $validator = Validator::make(
            [
                'name' => 'مدیر پروژه',
                'description' => 'مدیریت پروژه‌ها',
                'is_active' => true,
            ],
            (new StoreRoleRequest())->rules()
        );

        $this->assertFalse($validator->fails());
    }
}