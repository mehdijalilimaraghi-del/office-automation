<?php

use App\Modules\Telephony\Http\Controllers\DeviceAgentController;
use App\Modules\Telephony\Http\Controllers\DeviceController;
use App\Modules\Telephony\Http\Controllers\ModemController;
use App\Modules\Telephony\Http\Controllers\PbxController;
use App\Modules\Telephony\Http\Middleware\AuthenticateTelephonyDevice;
use App\Modules\Telephony\Http\Middleware\EnsureTelephonyEnabled;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureTelephonyEnabled::class, 'web', 'auth', 'verified', 'can:telephony.manage'])->prefix('telephony')->name('telephony.')->group(function (): void {
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::post('/devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::post('/devices/{device}/revoke', [DeviceController::class, 'revoke'])->whereNumber('device')->name('devices.revoke');
    Route::post('/devices/{device}/pairing', [DeviceController::class, 'regenerate'])->whereNumber('device')->name('devices.pairing');
    Route::get('/extensions', [PbxController::class, 'index'])->name('extensions.index');
    Route::post('/extensions', [PbxController::class, 'store'])->name('extensions.store');
    Route::put('/extensions/{extension}', [PbxController::class, 'update'])->whereNumber('extension')->name('extensions.update');
    Route::get('/ivr', [PbxController::class, 'ivr'])->name('ivr');
    Route::get('/ivr/audio', [PbxController::class, 'audio'])->name('ivr.audio');
    Route::post('/ivr', [PbxController::class, 'saveIvr'])->name('ivr.save');
    Route::get('/configuration', [PbxController::class, 'download'])->name('configuration.download');
    Route::get('/', [ModemController::class, 'index'])->name('index');
    Route::get('/helper', [ModemController::class, 'helper'])->name('helper');
    Route::post('/discoveries', [ModemController::class, 'store'])->middleware('throttle:5,1')->name('discoveries.store');
    Route::get('/discoveries/{discovery}', [ModemController::class, 'show'])->whereNumber('discovery')->name('discoveries.show');
    Route::get('/discoveries/{discovery}/status', [ModemController::class, 'status'])->whereNumber('discovery')->name('discoveries.status');
    Route::put('/modem-selection', [ModemController::class, 'select'])->name('modems.select');
});
Route::post('/telephony/agent/reports/{token}', [ModemController::class, 'report'])
    ->where('token', '[a-f0-9]{64}')->middleware([EnsureTelephonyEnabled::class, 'throttle:10,1'])->name('telephony.agent.report');

Route::middleware([EnsureTelephonyEnabled::class, 'throttle:12,1'])
    ->post('/telephony/agent/pair', [DeviceAgentController::class, 'pair'])->name('telephony.agent.pair');
Route::middleware([EnsureTelephonyEnabled::class, AuthenticateTelephonyDevice::class, 'throttle:120,1'])
    ->prefix('telephony/agent')->name('telephony.agent.')->group(function (): void {
        Route::post('/heartbeat', [DeviceAgentController::class, 'heartbeat'])->name('heartbeat');
        Route::get('/configuration', [DeviceAgentController::class, 'configuration'])->name('configuration');
    });
