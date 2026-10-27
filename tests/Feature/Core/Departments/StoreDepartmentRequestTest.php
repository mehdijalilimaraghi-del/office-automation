<?php

namespace Tests\Feature\Core\Departments;

use App\Http\Requests\Core\Departments\StoreDepartmentRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreDepartmentRequestTest extends TestCase
{
    public function test_name_is_required(): void
    {
        $validator = Validator::make(
            [],
            (new StoreDepartmentRequest())->rules()
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'name',
            $validator->errors()->toArray()
        );
    }

    public function test_department_code_is_required(): void
    {
        $validator = Validator::make(
            ['name' => 'واحد فنی'],
            (new StoreDepartmentRequest())->rules()
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('code', $validator->errors()->toArray());
    }

    public function test_name_cannot_exceed_150_characters(): void
    {
        $validator = Validator::make(
            ['name' => str_repeat('a', 151)],
            (new StoreDepartmentRequest())->rules()
        );

        $this->assertTrue($validator->fails());

        $this->assertArrayHasKey(
            'name',
            $validator->errors()->toArray()
        );
    }

    public function test_valid_department_data_passes_validation(): void
    {
        $validator = Validator::make(
            [
                'name' => 'واحد فنی',
                'code' => 'TECH',
                'description' => 'واحد فنی شرکت',
                'is_active' => true,
            ],
            (new StoreDepartmentRequest())->rules()
        );

        $this->assertFalse($validator->fails());
    }
}