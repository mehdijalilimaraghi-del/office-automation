<?php

use App\Modules\Telephony\Http\Controllers\ModemController;
use Illuminate\Support\Facades\Route;

Route::middleware([\App\Modules\Telephony\Http\Middleware\EnsureTelephonyEnabled::class, 'web', 'auth', 'verified', 'can:telephony.manage'])->prefix('telephony')->name('telephony.')->group(function (): void {
    Route::get('/extensions', [\App\Modules\Telephony\Http\Controllers\PbxController::class, 'index'])->name('extensions.index');
    Route::post('/extensions', [\App\Modules\Telephony\Http\Controllers\PbxController::class, 'store'])->name('extensions.store');
    Route::put('/extensions/{extension}', [\App\Modules\Telephony\Http\Controllers\PbxController::class, 'update'])->whereNumber('extension')->name('extensions.update');
    Route::get('/ivr', [\App\Modules\Telephony\Http\Controllers\PbxController::class, 'ivr'])->name('ivr');
    Route::post('/ivr', [\App\Modules\Telephony\Http\Controllers\PbxController::class, 'saveIvr'])->name('ivr.save');
    Route::get('/configuration', [\App\Modules\Telephony\Http\Controllers\PbxController::class, 'download'])->name('configuration.download');
    Route::get('/', [ModemController::class, 'index'])->name('index');
    Route::get('/helper', [ModemController::class, 'helper'])->name('helper');
    Route::post('/discoveries', [ModemController::class, 'store'])->middleware('throttle:5,1')->name('discoveries.store');
    Route::get('/discoveries/{discovery}', [ModemController::class, 'show'])->whereNumber('discovery')->name('discoveries.show');
    Route::get('/discoveries/{discovery}/status', [ModemController::class, 'status'])->whereNumber('discovery')->name('discoveries.status');
    Route::put('/modem-selection', [ModemController::class, 'select'])->name('modems.select');
});
Route::post('/telephony/agent/reports/{token}', [ModemController::class, 'report'])
    ->where('token', '[a-f0-9]{64}')->middleware([\App\Modules\Telephony\Http\Middleware\EnsureTelephonyEnabled::class, 'throttle:10,1'])->name('telephony.agent.report');
