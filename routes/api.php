<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChurchController;
use App\Http\Controllers\Api\ChurchRegistrationController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'store']);
});

Route::post('churches/register', [ChurchRegistrationController::class, 'store']);
Route::get('churches/{church}', [ChurchController::class, 'show']);
Route::get('churches/{church}/service-schedules', [ChurchController::class, 'serviceSchedules']);

Route::get('attendance', [AttendanceController::class, 'index']);
Route::get('attendance/summary', [AttendanceController::class, 'summary']);
Route::post('attendance', [AttendanceController::class, 'store']);

Route::prefix('locations')->group(function (): void {
    Route::get('states', [LocationController::class, 'states']);
    Route::get('lgas', [LocationController::class, 'lgas']);
    Route::get('states/{state}/lgas', [LocationController::class, 'stateLgas']);
});
