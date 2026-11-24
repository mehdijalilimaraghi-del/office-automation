<?php

namespace Tests\Feature\Modules\Telephony;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Telephony\Models\Device;
use App\Modules\Telephony\Models\Extension;
use App\Modules\Telephony\Providers\TelephonyServiceProvider;
use Database\Seeders\CorePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceConnectionTest extends TestCase
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
        $role = Role::query()->create(['name' => 'Device '.Str::random(8), 'code' => 'device-'.Str::random(10), 'is_active' => true, 'is_system' => false]);
        $role->permissions()->attach(Permission::query()->where('code', 'settings.manage')->firstOrFail()->id, ['scope' => 'organization']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role);

        return $user->fresh();
    }

    private function pending(): array
    {
        $manager = $this->manager();
        $this->actingAs($manager)->post(route('telephony.devices.store'), ['name' => 'مرکز تلفن'])
            ->assertRedirect(route('telephony.devices.index'))->assertSessionHas('telephony_pairing');
        $code = session('telephony_pairing.code');
        $device = Device::query()->latest('id')->firstOrFail();

        return [$manager, $device, $code];
    }

    private function paired(): array
    {
        [$manager, $device, $code] = $this->pending();
        $response = $this->postJson(route('telephony.agent.pair'), ['pairing_code' => $code, 'computer_name' => 'TEST-MODEM-PC']);
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        return [$manager, $device->fresh(), $response->json('token')];
    }

    private function extension(User $owner): Extension
    {
        return Extension::query()->create(['number' => '1001', 'label' => 'دبیرخانه', 'owner_type' => 'user', 'user_id' => $owner->id, 'is_active' => true]);
    }

    public function test_device_management_requires_active_manager(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user)->get(route('telephony.devices.index'))->assertForbidden();
        $this->post(route('telephony.devices.store'), ['name' => 'Computer'])->assertForbidden();
        $manager = $this->manager();
        $this->actingAs($manager)->get(route('telephony.devices.index'))->assertOk();
        $manager->update(['status' => 'inactive']);
        $this->post(route('telephony.devices.store'), ['name' => 'Computer'])->assertForbidden();
    }

    public function test_pairing_is_one_time_and_only_hashes_are_stored(): void
    {
        [, $device, $code] = $this->pending();
        $this->assertNotSame($code, $device->pairing_hash);
        $this->assertSame(hash('sha256', $code), $device->pairing_hash);
        $response = $this->postJson(route('telephony.agent.pair'), ['pairing_code' => $code, 'computer_name' => 'MODEM-PC']);
        $response->assertOk();
        $token = $response->json('token');
        $device->refresh();
        $this->assertSame(hash('sha256', $token), $device->token_hash);
        $this->assertNull($device->pairing_hash);
        $this->assertArrayNotHasKey('token_hash', $device->toArray());
        $this->postJson(route('telephony.agent.pair'), ['pairing_code' => $code, 'computer_name' => 'OTHER-PC'])->assertUnauthorized();
        $this->getJson(route('telephony.agent.configuration'))->assertUnauthorized();
    }

    public function test_expired_pairing_and_disabled_module_are_rejected(): void
    {
        [, $device, $code] = $this->pending();
        $device->update(['pairing_expires_at' => now()->subSecond()]);
        $this->postJson(route('telephony.agent.pair'), ['pairing_code' => $code, 'computer_name' => 'PC'])->assertUnauthorized();
        config(['telephony.enabled' => false]);
        $this->postJson(route('telephony.agent.pair'), ['pairing_code' => $code, 'computer_name' => 'PC'])->assertNotFound();
        $this->postJson(route('telephony.agent.heartbeat'), ['runtime_state' => 'ready'])->assertNotFound();
    }

    public function test_device_receives_configuration_revision_and_reports_only_its_own_state(): void
    {
        [$manager, $device, $token] = $this->paired();
        $extension = $this->extension($manager);
        $other = Device::query()->create(['name' => 'Other', 'status' => 'awaiting_pairing']);
        $response = $this->withToken($token)->postJson(route('telephony.agent.heartbeat'), ['runtime_state' => 'ready', 'device_id' => $other->id]);
        $response->assertOk()->assertJsonPath('configuration_ready', true);
        $revision = $response->json('configuration_revision');
        $this->assertMatchesRegularExpression('/\\A[a-f0-9]{64}\\z/', $revision);
        $this->assertSame('ready', $device->fresh()->runtime_state);
        $this->assertNull($other->fresh()->last_seen_at);
        $this->withToken($token)->postJson(route('telephony.agent.heartbeat'), ['runtime_state' => 'busy', 'applied_revision' => $revision])->assertOk();
        $this->assertSame($revision, $device->fresh()->applied_revision);
        $extension->update(['label' => 'عنوان جدید']);
        $updated = $this->withToken($token)->postJson(route('telephony.agent.heartbeat'), ['runtime_state' => 'ready'])->assertOk();
        $this->assertNotSame($revision, $updated->json('configuration_revision'));
    }

    public function test_empty_configuration_remains_visible_without_error(): void
    {
        [, $device, $token] = $this->paired();
        $this->withToken($token)->postJson(route('telephony.agent.heartbeat'), ['runtime_state' => 'stopped'])
            ->assertOk()->assertJsonPath('configuration_ready', false)->assertJsonPath('configuration_revision', null);
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_agent_archive_contains_configuration_without_executable_code(): void
    {
        [$manager, , $token] = $this->paired();
        $this->extension($manager);
        $response = $this->withToken($token)->get(route('telephony.agent.configuration'))->assertOk();
        $this->assertMatchesRegularExpression('/\\A[a-f0-9]{64}\\z/', $response->headers->get('X-Office-Configuration-Revision'));
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($file));
        $this->assertSame(1, $zip->numFiles);
        $manifest = json_decode($zip->getFromName('office-telephony.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('office_telephony_configuration', $manifest['type']);
        $this->assertSame('1001', $manifest['extensions'][0]['number']);
        $zip->close();
        @unlink($file);
    }

    public function test_revocation_immediately_blocks_existing_agent_token(): void
    {
        [$manager, $device, $token] = $this->paired();
        $this->actingAs($manager)->post(route('telephony.devices.revoke', $device))->assertSessionHas('success');
        $this->withToken($token)->postJson(route('telephony.agent.heartbeat'), ['runtime_state' => 'ready'])->assertUnauthorized();
        $this->withToken($token)->getJson(route('telephony.agent.configuration'))->assertUnauthorized();
        $this->assertNull($device->fresh()->token_hash);
    }

    public function test_renewal_invalidates_old_code_but_does_not_rotate_a_linked_device_silently(): void
    {
        [$manager, $device, $old] = $this->pending();
        $this->actingAs($manager)->post(route('telephony.devices.pairing', $device))->assertSessionHas('telephony_pairing');
        $code = session('telephony_pairing.code');
        $this->assertNotSame($old, $code);
        $this->postJson(route('telephony.agent.pair'), ['pairing_code' => $old, 'computer_name' => 'PC'])->assertUnauthorized();
        $this->postJson(route('telephony.agent.pair'), ['pairing_code' => $code, 'computer_name' => 'PC'])->assertOk();
        $this->actingAs($manager)->post(route('telephony.devices.pairing', $device))->assertSessionHasErrors('pairing');
    }
}
