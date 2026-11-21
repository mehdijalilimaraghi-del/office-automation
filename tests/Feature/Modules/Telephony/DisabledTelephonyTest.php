<?php
namespace Tests\Feature\Modules\Telephony;
use Tests\TestCase;
class DisabledTelephonyTest extends TestCase {
    public function test_disabled_module_does_not_register_its_routes(): void {
        $this->assertFalse((bool)config('telephony.enabled'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('telephony.index'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('telephony.agent.report'));
    }
}
