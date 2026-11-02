<?php

use App\Enums\UserRole;
use App\Http\Controllers\Admin\ComplaintCategoryController;
use App\Http\Controllers\Admin\HierarchyController;
use App\Http\Controllers\Admin\PortalMenuController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GrievanceController;
use App\Http\Controllers\UserNotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Portal home — consumer landing. `?forget=1` clears a pending OTP step.
Route::get('/', function (Request $request) {
    if ($request->boolean('forget')) {
        $request->session()->forget(OtpLoginController::OTP_SESSION_KEYS);
    }

    if (Auth::check() || Auth::guard('consumer')->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.attempt');

    // Consumer OTP login: request the code, then verify it.
    Route::post('/login/otp/request', [OtpLoginController::class, 'request'])
        ->middleware('throttle:otp-request')
        ->name('login.otp.request');
    Route::post('/login/otp/verify', [OtpLoginController::class, 'verify'])
        ->name('login.otp.verify');
});

// Consumer-guarded routes (external users).
Route::middleware('auth:consumer')->group(function (): void {
    // Password change page reached from the consumer sidebar.
    Route::get('/password/change', [ChangePasswordController::class, 'create'])->name('password.change');
    Route::post('/password/change', [ChangePasswordController::class, 'store'])
        ->name('password.change.store');

    Route::get('/complaints/create', [GrievanceController::class, 'create'])->name('complaints.create');
    Route::post('/complaints', [GrievanceController::class, 'register'])->name('complaints.register');
});

// Routes reachable while either guard holds a session.
Route::middleware('auth:web,consumer')->group(function (): void {
    // Role-aware dashboard — personal for consumers, region/assignment queues
    // for officials.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/me', [AuthController::class, 'me'])->name('me');

    Route::get('/complaints', [GrievanceController::class, 'index'])->name('complaints.index');
    Route::get('/complaints/{complaint}', [GrievanceController::class, 'show'])->name('complaints.show');
    Route::get('/complaints/{complaint}/track', [GrievanceController::class, 'track'])
        ->name('complaints.track');
});

// Official-only routes — pinned to the web guard: officials exist only on
// the `users` table, so consumer sessions are unauthenticated here.
Route::middleware(['auth:web', 'role:'.UserRole::SiteAdmin->value])->prefix('admin')->name('admin.')->group(function (): void {
    // Site administration: the portal sidebar navigation is managed here.
    Route::get('/menu', [PortalMenuController::class, 'index'])->name('menu.index');
    Route::get('/menu/{item}/edit', [PortalMenuController::class, 'edit'])->name('menu.edit');
    Route::post('/menu', [PortalMenuController::class, 'store'])->name('menu.store');
    Route::post('/menu/{item}/update', [PortalMenuController::class, 'update'])->name('menu.update');
    Route::post('/menu/{item}/toggle', [PortalMenuController::class, 'toggle'])->name('menu.toggle');
    Route::post('/menu/{item}/move', [PortalMenuController::class, 'move'])->name('menu.move');
    Route::post('/menu/{item}/delete', [PortalMenuController::class, 'destroy'])->name('menu.destroy');

    // Master data
    Route::resource('complaint-categories', ComplaintCategoryController::class)->except(['show']);

    // Cascading hierarchy lookups that feed the role-mapping selects.
    Route::get('/hierarchy/circles', [HierarchyController::class, 'circles'])->name('hierarchy.circles');
    Route::get('/hierarchy/divisions', [HierarchyController::class, 'divisions'])->name('hierarchy.divisions');
    Route::get('/hierarchy/subdivisions', [HierarchyController::class, 'subdivisions'])->name('hierarchy.subdivisions');
    Route::get('/hierarchy/sections', [HierarchyController::class, 'sections'])->name('hierarchy.sections');

    // Role mapping
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{user}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{user}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{user}', [RoleController::class, 'destroy'])->name('roles.destroy');
});

Route::get('/notifications/{notification}', [UserNotificationController::class, 'open'])
    ->middleware('auth:web,consumer')
    ->name('notifications.open');

Route::middleware(['auth:web', 'role:relationship_manager'])->group(function (): void {
    Route::post('/complaints/{complaint}/forward', [GrievanceController::class, 'forwardToAuthority'])
        ->name('complaints.forward');
});

Route::middleware(['auth:web', 'role:competent_authority'])->group(function (): void {
    Route::post('/complaints/{complaint}/resolve', [GrievanceController::class, 'resolveGrievance'])
        ->name('complaints.resolve');
});
