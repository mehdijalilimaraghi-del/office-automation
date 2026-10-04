<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_details_and_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'name' => 'نام قدیمی',
            'email' => 'old@example.com',
        ]);

        $response = $this->actingAs($user)->post(route('profile.update'), [
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'employee_code' => 'EMP-100',
            'national_id' => '0012345678',
            'birth_date' => '1990-01-01',
            'email' => 'ali@example.com',
            'avatar' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $response->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('علی رضایی', $user->name);
        $this->assertSame('ali@example.com', $user->email);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'employee_code' => 'EMP-100',
            'national_id' => '0012345678',
            'birth_date' => '1990-01-01',
        ]);

        $this->assertNotNull($user->profile->avatar_path);
        Storage::disk('public')->assertExists($user->profile->avatar_path);
    }
}
