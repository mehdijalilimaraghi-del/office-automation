<?php

namespace Tests\Feature\Modules\Telephony;

use App\Core\Permissions\Models\Permission;
use App\Core\Roles\Models\Role;
use App\Models\User;
use App\Modules\Telephony\Models\Discovery;
use App\Modules\Telephony\Models\Modem;
use App\Modules\Telephony\Providers\TelephonyServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModemDiscoveryTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void {
        parent::setUp();
        $this->seed(\Database\Seeders\CorePermissionSeeder::class);
        config(['telephony.enabled' => true]);
        $this->app->register(TelephonyServiceProvider::class);
        $this->app['router']->getRoutes()->refreshNameLookups();
    }
    public function test_disabled_configuration_blocks_already_registered_routes(): void {
        config(['telephony.enabled' => false]);
        $this->get('/telephony')->assertNotFound();
        $this->postJson('/telephony/agent/reports/'.str_repeat('a',64),$this->reportData())->assertNotFound();
    }
    private function manager(): User {
        $role = Role::query()->create(['name' => 'Telephony '.Str::random(8), 'code' => 'telephony-'.Str::random(12), 'is_active' => true, 'is_system' => false]);
        $role->permissions()->attach(Permission::query()->where('code', 'settings.manage')->firstOrFail()->id, ['scope' => 'organization']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($role);
        return $user->fresh();
    }
    private function requestDiscovery(User $user): array {
        $this->actingAs($user)->post(route('telephony.discoveries.store'))->assertRedirect();
        $discovery = Discovery::query()->latest('id')->firstOrFail();
        return [$discovery, session('telephony.discovery.'.$discovery->id)];
    }
    private function reportData(): array {
        return ['computer_key' => '38be7f4e-e2ed-453b-a0c6-ae1e8b549f70', 'computer_name' => 'MODEM-PC', 'devices' => [[
            'device_key' => str_repeat('a',64), 'name' => 'LSI PCI-SV92PP Soft Modem',
            'port' => 'COM3', 'driver_status' => 'OK', 'probe_status' => 'queried',
            'evidence' => ['classes' => "AT+FCLASS=?\r\n+FCLASS: 0,1,8\r\nOK\r\n", 'identity' => 'LSI'],
        ]]];
    }
    public function test_manager_pairs_helper_and_selects_only_a_fresh_voice_candidate(): void {
        $user = $this->manager();
        $this->actingAs($user)->get(route('telephony.index'))->assertInertia(fn($page) => $page->component('Telephony/Index')->has('modems',0));
        [$discovery,$token] = $this->requestDiscovery($user);
        $this->assertNotSame($token,$discovery->token_hash);
        $this->get(route('telephony.discoveries.show',$discovery))->assertInertia(fn($page) => $page->where('protocolUrl','office-telephony://discover?upload='.rawurlencode(route('telephony.agent.report',$token))));
        $this->postJson(route('telephony.agent.report',$token),$this->reportData())->assertOk();
        $this->get(route('telephony.discoveries.status',$discovery))->assertJson(['complete'=>true]);
        $modem=Modem::query()->firstOrFail();
        $this->assertTrue($modem->voice_advertised);
        $this->put(route('telephony.modems.select'),['modem_id'=>$modem->id])->assertSessionHas('success');
        $this->assertDatabaseHas('system_settings',['key'=>'telephony.modem.selected_id','value'=>(string)$modem->id]);
        $this->postJson(route('telephony.agent.report',$token),$this->reportData())->assertStatus(409);
        $this->travel(16)->minutes();
        $this->put(route('telephony.modems.select'),['modem_id'=>$modem->id])->assertSessionHasErrors('modem_id');
    }
    public function test_helper_token_expires_and_revoked_owner_cannot_submit(): void {
        $user=$this->manager();
        [$discovery,$token]=$this->requestDiscovery($user);
        $this->travel(11)->minutes();
        $this->postJson(route('telephony.agent.report',$token),$this->reportData())->assertStatus(410);
        [$discovery,$token]=$this->requestDiscovery($user);
        $user->update(['status'=>'inactive']);
        $this->postJson(route('telephony.agent.report',$token),$this->reportData())->assertForbidden();
        $this->assertDatabaseCount('telephony_modems',0);
    }
    public function test_other_users_cannot_manage_or_read_discoveries(): void {
        $owner=$this->manager();
        [$discovery]=$this->requestDiscovery($owner);
        $other=$this->manager();
        $this->actingAs($other)->get(route('telephony.discoveries.show',$discovery))->assertForbidden();
        $this->get(route('telephony.discoveries.status',$discovery))->assertForbidden();
        $this->actingAs(User::factory()->create(['status'=>'active']))->get(route('telephony.index'))->assertForbidden();
        $this->postJson(route('telephony.agent.report',str_repeat('b',64)),$this->reportData())->assertNotFound();
    }
    public function test_unproven_and_missing_devices_are_not_selectable_and_reports_are_bounded(): void {
        $user=$this->manager();
        [$discovery,$token]=$this->requestDiscovery($user);
        $data=$this->reportData();
        $data['devices'][0]['evidence']['classes']="+FCLASS: 0,1\r\nOK";
        $this->postJson(route('telephony.agent.report',$token),$data)->assertOk();
        $modem=Modem::query()->firstOrFail();
        $this->assertFalse($modem->voice_advertised);
        $this->put(route('telephony.modems.select'),['modem_id'=>$modem->id])->assertSessionHasErrors('modem_id');
        [$discovery,$token]=$this->requestDiscovery($user);
        $data['devices'][0]['port']='COM3;call';
        $this->postJson(route('telephony.agent.report',$token),$data)->assertUnprocessable();
        $data=$this->reportData();
        $data['devices'][0]['evidence']['classes']=str_repeat('x',4097);
        $this->postJson(route('telephony.agent.report',$token),$data)->assertUnprocessable();
        $data=$this->reportData();$data['devices']=[];
        $this->postJson(route('telephony.agent.report',$token),$data)->assertOk();
        $this->assertSame('unavailable',$modem->fresh()->driver_status);
        $this->assertFalse($modem->fresh()->voice_advertised);
    }
    public function test_helper_download_contains_exact_origin_without_discovery_secrets(): void {
        $user=$this->manager();
        $response=$this->actingAs($user)->get('http://127.0.0.1:8000/telephony/helper');
        $response->assertOk();
        $path=$response->baseResponse->getFile()->getPathname();
        $zip=new \ZipArchive();
        $this->assertTrue($zip->open($path));
        $this->assertSame('http://127.0.0.1:8000',json_decode($zip->getFromName('settings.json'),true)['server_url']);
        $this->assertNotFalse($zip->getFromName('ModemHelper.ps1'));
        $this->assertNotFalse($zip->getFromName('Install.ps1'));
        $zip->close();unlink($path);
    }
}
