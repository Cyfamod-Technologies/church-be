<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BranchTagController;
use App\Http\Controllers\Api\ChurchController;
use App\Http\Controllers\Api\ChurchRegistrationController;
use App\Http\Controllers\Api\ChurchUnitController;
use App\Http\Controllers\Api\GuestResponseEntryController;
use App\Http\Controllers\Api\HomecellAttendanceController;
use App\Http\Controllers\Api\HomecellController;
use App\Http\Controllers\Api\HomecellLeaderController;
use App\Http\Controllers\Api\LocationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'store']);
});

Route::post('churches/register', [ChurchRegistrationController::class, 'store']);
Route::get('churches/{church}', [ChurchController::class, 'show']);
Route::put('churches/{church}', [ChurchController::class, 'update']);
Route::put('churches/{church}/profile', [ChurchController::class, 'updateProfile']);
Route::get('churches/{church}/service-schedules', [ChurchController::class, 'serviceSchedules']);
Route::put('churches/{church}/service-schedules', [ChurchController::class, 'updateServiceSchedules']);
Route::put('churches/{church}/homecell-schedule', [ChurchController::class, 'updateHomecellSchedule']);

Route::get('attendance', [AttendanceController::class, 'index']);
Route::get('attendance/summary', [AttendanceController::class, 'summary']);
Route::get('attendance/{attendanceRecord}', [AttendanceController::class, 'show']);
Route::post('attendance', [AttendanceController::class, 'store']);
Route::put('attendance/{attendanceRecord}', [AttendanceController::class, 'update']);

Route::get('branch-tags', [BranchTagController::class, 'index']);
Route::post('branch-tags', [BranchTagController::class, 'store']);
Route::delete('branch-tags/{branchTag}', [BranchTagController::class, 'destroy']);

Route::get('branch-parents', [BranchController::class, 'parentOptions']);
Route::get('branches', [BranchController::class, 'index']);
Route::post('branches', [BranchController::class, 'store']);
Route::get('branches/{branch}', [BranchController::class, 'show']);
Route::put('branches/{branch}', [BranchController::class, 'update']);
Route::post('branches/{branch}/reassign', [BranchController::class, 'reassign']);
Route::post('branches/{branch}/detach', [BranchController::class, 'detach']);

Route::get('homecells', [HomecellController::class, 'index']);
Route::post('homecells', [HomecellController::class, 'store']);
Route::get('homecells/{homecell}', [HomecellController::class, 'show']);
Route::put('homecells/{homecell}', [HomecellController::class, 'update']);
Route::get('homecell-leaders/{homecellLeader}', [HomecellLeaderController::class, 'show']);
Route::put('homecell-leaders/{homecellLeader}', [HomecellLeaderController::class, 'update']);

Route::get('guest-response-entries', [GuestResponseEntryController::class, 'index']);
Route::post('guest-response-entries', [GuestResponseEntryController::class, 'store']);
Route::get('guest-response-entries/{guestResponseEntry}', [GuestResponseEntryController::class, 'show']);
Route::put('guest-response-entries/{guestResponseEntry}', [GuestResponseEntryController::class, 'update']);

Route::get('church-units', [ChurchUnitController::class, 'index']);
Route::post('church-units', [ChurchUnitController::class, 'store']);
Route::get('church-units/{churchUnit}', [ChurchUnitController::class, 'show']);
Route::put('church-units/{churchUnit}', [ChurchUnitController::class, 'update']);

Route::get('homecell-attendance', [HomecellAttendanceController::class, 'index']);
Route::get('homecell-attendance/summary', [HomecellAttendanceController::class, 'summary']);
Route::get('homecell-attendance/{homecellAttendanceRecord}', [HomecellAttendanceController::class, 'show']);
Route::post('homecell-attendance', [HomecellAttendanceController::class, 'store']);
Route::put('homecell-attendance/{homecellAttendanceRecord}', [HomecellAttendanceController::class, 'update']);

Route::prefix('locations')->group(function (): void {
    Route::get('states', [LocationController::class, 'states']);
    Route::get('lgas', [LocationController::class, 'lgas']);
    Route::get('states/{state}/lgas', [LocationController::class, 'stateLgas']);
});
