<?php

namespace Tests\Feature\Modules\Telephony;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Telephony\Models\Extension;
use App\Modules\Telephony\Providers\TelephonyServiceProvider;
use App\Modules\Telephony\Services\PbxConfigurationService;
use Database\Seeders\CorePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PbxConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CorePermissionSeeder::class);
        config(['telephony.enabled' => true]);
        $this->app->register(TelephonyServiceProvider::class);
        $this->app['router']->getRoutes()->refreshNameLookups();
        Storage::fake('local');
    }

    private function manager(): User
    {
        $role = Role::query()->create(['name' => 'PBX '.Str::random(8), 'code' => 'pbx-'.Str::random(10), 'is_active' => true, 'is_system' => false]);
        $role->permissions()->attach(Permission::query()->where('code', 'settings.manage')->firstOrFail()->id, ['scope' => 'organization']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function extension(User $owner, string $number = '1001'): array
    {
        return ['number' => $number, 'label' => 'دبیرخانه', 'owner_type' => 'user', 'user_id' => $owner->id, 'department_id' => null, 'is_active' => true];
    }

    private function menu(array $changes = []): array
    {
        return array_replace(['enabled' => true, 'timeout_seconds' => 5, 'retries' => 2, 'operator_number' => '1001', 'choices' => [['digit' => '1', 'number' => '1001']]], $changes);
    }

    private function wave(): UploadedFile
    {
        $wav = 'RIFF'.pack('V', 3236).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16).'data'.pack('V', 3200).str_repeat("\0", 3200);

        return UploadedFile::fake()->createWithContent('greeting.wav', $wav);
    }

    public function test_only_authorized_active_managers_can_manage_and_download(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        foreach (['telephony.extensions.index', 'telephony.ivr', 'telephony.configuration.download'] as $route) {
            $this->actingAs($user)->get(route($route))->assertForbidden();
        }
        $this->post(route('telephony.extensions.store'), $this->extension($user))->assertForbidden();
        $manager = $this->manager();
        $manager->update(['status' => 'inactive']);
        $this->actingAs($manager)->get(route('telephony.extensions.index'))->assertForbidden();
    }

    public function test_extensions_validate_ownership_duplicates_and_reserved_numbers(): void
    {
        $owner = $this->manager();
        $this->actingAs($owner)->post(route('telephony.extensions.store'), $this->extension($owner))->assertSessionHas('success');
        $this->assertDatabaseHas('telephony_extensions', ['number' => '1001', 'user_id' => $owner->id]);
        $this->post(route('telephony.extensions.store'), $this->extension($owner))->assertSessionHasErrors('number');
        foreach (['9196', '9197', '12', "10\n02", '1002;system'] as $number) {
            $this->post(route('telephony.extensions.store'), $this->extension($owner, $number))->assertSessionHasErrors('number');
        }
        $this->post(route('telephony.extensions.store'), array_replace($this->extension($owner, '1002'), ['owner_type' => 'department']))->assertSessionHasErrors(['department_id', 'user_id']);
        $this->get(route('telephony.extensions.index'))->assertOk();
    }

    public function test_ivr_requires_audio_and_active_unique_destinations(): void
    {
        $owner = $this->manager();
        Extension::query()->create($this->extension($owner));
        $this->actingAs($owner)->post(route('telephony.ivr.save'), $this->menu())->assertSessionHasErrors('audio');
        $this->post(route('telephony.ivr.save'), $this->menu(['audio' => UploadedFile::fake()->createWithContent('bad.wav', 'not a wave')]))->assertSessionHasErrors('audio');
        $this->post(route('telephony.ivr.save'), $this->menu(['audio' => $this->wave()]))->assertSessionHas('success');
        $this->post(route('telephony.ivr.save'), $this->menu(['choices' => [['digit' => '1', 'number' => '1001'], ['digit' => '1', 'number' => '1001']]]))->assertSessionHasErrors('choices.0.digit');
        $this->post(route('telephony.ivr.save'), $this->menu(['operator_number' => '9999']))->assertSessionHasErrors('operator_number');
        $this->get(route('telephony.ivr'))->assertOk();
    }

    public function test_routed_extension_cannot_be_renamed_or_disabled(): void
    {
        $owner = $this->manager();
        $extension = Extension::query()->create($this->extension($owner));
        $this->actingAs($owner)->post(route('telephony.ivr.save'), $this->menu(['audio' => $this->wave()]))->assertSessionHas('success');
        $this->put(route('telephony.extensions.update', $extension), array_replace($this->extension($owner), ['is_active' => false]))->assertSessionHasErrors('is_active');
        $this->put(route('telephony.extensions.update', $extension), $this->extension($owner, '1002'))->assertSessionHasErrors('is_active');
        $this->assertDatabaseHas('telephony_extensions', ['number' => '1001', 'is_active' => true]);
    }

    public function test_download_is_credential_free_and_contains_validated_audio_and_helpers(): void
    {
        $owner = $this->manager();
        Extension::query()->create($this->extension($owner));
        $this->actingAs($owner)->post(route('telephony.ivr.save'), $this->menu(['audio' => $this->wave()]))->assertSessionHas('success');
        $response = $this->get(route('telephony.configuration.download'))->assertOk()->assertDownload('OfficeAutomation-PhoneSettings.zip');
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file));
        try {
            $manifest = json_decode($zip->getFromName('office-telephony.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('1001', $manifest['extensions'][0]['number']);
            $this->assertTrue($manifest['ivr']['enabled']);
            $this->assertStringNotContainsString('password', json_encode($manifest));
            $this->assertStringNotContainsString('audio_path', json_encode($manifest));
            $this->assertNotFalse($zip->getFromName('greeting.wav'));
            $this->assertNotFalse($zip->getFromName('Apply-OfficeConfig.ps1'));
        } finally {
            $zip->close();
            @unlink($file);
        }
    }

    public function test_disabled_module_rejects_new_routes_even_when_cached(): void
    {
        config(['telephony.enabled' => false]);
        $this->actingAs($this->manager())->get(route('telephony.extensions.index'))->assertNotFound();
        $this->get(route('telephony.ivr'))->assertNotFound();
        $this->get(route('telephony.configuration.download'))->assertNotFound();
    }

    public function test_menu_without_digit_shortcuts_can_be_saved(): void
    {
        $owner = $this->manager();
        Extension::query()->create($this->extension($owner));
        $data = $this->menu(['audio' => $this->wave()]);
        unset($data['choices']);
        $this->actingAs($owner)->post(route('telephony.ivr.save'), $data)->assertSessionHas('success');
        $this->assertSame([], app(PbxConfigurationService::class)->menu()['choices']);
    }

    public function test_corrupt_or_non_pcm_wave_cannot_replace_existing_prompt(): void
    {
        $owner = $this->manager();
        Extension::query()->create($this->extension($owner));
        $this->actingAs($owner)->post(route('telephony.ivr.save'), $this->menu(['audio' => $this->wave()]))->assertSessionHas('success');
        $before = app(PbxConfigurationService::class)->menu();
        $this->post(route('telephony.ivr.save'), $this->menu(['audio' => UploadedFile::fake()->createWithContent('bad.wav', 'RIFF'.pack('V', 12).'WAVEdata'.pack('V', 2000000))]))->assertSessionHasErrors('audio');
        $this->assertSame($before, app(PbxConfigurationService::class)->menu());
    }
}
