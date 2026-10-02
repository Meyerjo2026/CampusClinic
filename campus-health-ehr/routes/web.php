<?php

use App\Events\CrisisAlertEvent;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Campus Health EHR Web Routes (Blade + Alpine.js)
|
*/

// Public landing page
Route::get('/', function () {
    return redirect()->route('login');
});

// Authentication routes (Laravel Breeze/Fortify would handle these)
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::get('/register', fn () => view('auth.register'))->name('register');
    Route::post('/login', [LoginController::class, 'store']);
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

// Protected routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Student routes
    Route::prefix('student')->name('student.')->group(function () {
        Route::get('/dashboard', fn () => view('student.dashboard'))->name('dashboard');
        Route::get('/appointments', fn () => view('student.appointments.index'))->name('appointments');
        Route::get('/appointments/{appointment}', fn () => view('student.appointments.show'))->name('appointments.show');
        Route::get('/medications', fn () => view('student.medications.index'))->name('medications');
        Route::get('/medications/refill/{medicationRequest}', fn () => view('student.medications.refill'))->name('medications.refill');
        Route::get('/profile', fn () => view('student.profile.index'))->name('profile');
        Route::get('/health-records', fn () => view('student.health-records'))->name('health-records');
    });

    // Clinical staff routes
    Route::prefix('clinical')->name('clinical.')->middleware('role:cnp,gp,psychologist,pharmacist,social_worker,admin')->group(function () {
        Route::get('/dashboard', fn () => view('clinical.dashboard'))->name('dashboard');
        Route::get('/queue', fn () => view('clinical.queue'))->name('queue');
        Route::get('/encounters', fn () => view('clinical.encounters.index'))->name('encounters.index');
        Route::get('/encounters/create', fn () => view('clinical.encounters.create'))->name('encounters.create');
        Route::get('/encounters/{encounter}', fn () => view('clinical.encounters.show'))->name('encounters.show');
        Route::get('/encounters/{encounter}/edit', fn () => view('clinical.encounters.edit'))->name('encounters.edit');
        Route::get('/encounters/patient/{patient}', fn () => view('clinical.encounters.patient'))->name('encounters.patient');

        // Pharmacy
        Route::prefix('pharmacy')->name('pharmacy.')->group(function () {
            Route::get('/queue', fn () => view('clinical.pharmacy.queue'))->name('queue');
            Route::get('/chronic-refills', fn () => view('clinical.pharmacy.chronic-refills'))->name('chronic-refills');
            Route::get('/inventory', fn () => view('clinical.pharmacy.inventory'))->name('inventory');
            Route::get('/controlled-register', fn () => view('clinical.pharmacy.controlled-register'))->name('controlled-register');
        });

        // Outreach
        Route::prefix('outreach')->name('outreach.')->group(function () {
            Route::get('/', fn () => view('clinical.outreach.index'))->name('index');
            Route::get('/{patient}', fn () => view('clinical.outreach.show'))->name('show');
        });

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/utilization', fn () => view('clinical.reports.utilization'))->name('utilization');
            Route::get('/clinical', fn () => view('clinical.reports.clinical'))->name('clinical');
        });
    });

    // Admin routes
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', fn () => view('admin.dashboard'))->name('dashboard');
        Route::get('/users', fn () => view('admin.users.index'))->name('users.index');
        Route::get('/roles', fn () => view('admin.roles.index'))->name('roles.index');
        Route::get('/audit-logs', fn () => view('admin.audit-logs'))->name('audit-logs');
        Route::get('/system-settings', fn () => view('admin.settings'))->name('settings');
    });
});

// Crisis alert test route (development only)
if (app()->environment('local')) {
    Route::get('/test/crisis-alert', function () {
        event(new CrisisAlertEvent([
            'patient_id' => 1,
            'student_number' => 'S1234567',
            'patient_name' => 'Test Student',
            'crisis_reasons' => ['Test crisis alert'],
            'triage_screening' => ['chief_complaint' => 'Test'],
            'timestamp' => now()->toIso8601String(),
            'source' => 'test',
            'severity' => 'critical',
        ]));

        return response()->json(['message' => 'Crisis alert dispatched']);
    });
}
