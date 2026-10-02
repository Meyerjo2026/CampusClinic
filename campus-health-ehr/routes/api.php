<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ClinicalEncounterController;
use App\Http\Controllers\PharmacyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Campus Health EHR API Routes
| Protected by POPIA consent middleware and Sanctum authentication
|
*/

// Public routes (no authentication required)
Route::prefix('v1')->group(function () {
    // Health check
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'Campus Health EHR']));

    // Emergency hotlines (public access)
    Route::get('/emergency/hotlines', fn () => response()->json([
        'SWS_Crisis_Line' => '021 650 1271',
        'UCT_Careline_SADAG' => '0800 24 25 26',
        'UCT_Campus_Protection' => '080 650 2222',
        'HIGHER_HEALTH_Helpline' => '0800 36 36 36',
        'SADAG_Suicide_Crisis' => '0800 567 567',
        'Emergency_Services' => '10111 / 10177',
    ]));
});

// Protected routes - require authentication
Route::middleware(['auth:sanctum', 'popia.consent'])->prefix('v1')->group(function () {

    // Patient profile
    Route::get('/patient/profile', function () {
        return auth()->user()->patient?->load('user');
    });

    Route::put('/patient/profile', function () {
        // Update patient profile
    });

    Route::put('/patient/emergency-contact', function () {
        // Update emergency contact
    });

    Route::put('/patient/medical-info', function () {
        // Update medical info
    });

    // POPIA Consent management
    Route::post('/patient/consent', function () {
        // Grant POPIA consent
    });

    Route::delete('/patient/consent', function () {
        // Revoke POPIA consent
    });

    Route::get('/patient/access-logs', function () {
        // Get patient's access logs
    });

    Route::get('/patient/export', function () {
        // Export patient data (POPIA)
    });

    // Appointments
    Route::apiResource('appointments', AppointmentController::class)
        ->only(['index', 'store', 'show']);

    Route::patch('/appointments/{appointment}/status/{status}', [AppointmentController::class, 'updateStatus'])
        ->name('appointments.update-status');

    // Clinical Encounters
    Route::apiResource('encounters', ClinicalEncounterController::class)
        ->only(['index', 'store', 'show', 'update']);

    // Patient-specific encounters
    Route::get('/patients/{patient}/encounters', [ClinicalEncounterController::class, 'index']);

    // Pharmacy
    Route::post('/medication-requests/{medicationRequest}/dispense', [PharmacyController::class, 'dispenseMedication']);
    Route::post('/medication-dispenses/collect/{qrToken}', [PharmacyController::class, 'collectMedication']);
    Route::get('/pharmacy/queue', [PharmacyController::class, 'getQueue']);
    Route::get('/pharmacy/chronic-refills', [PharmacyController::class, 'getChronicRefillsDue']);

    // Medication Requests
    Route::get('/medication-requests', function () {
        // List patient's prescriptions
    });

    Route::get('/medication-requests/{medicationRequest}', function () {
        // Show prescription details
    });

    // Crisis Alert Acknowledgment
    Route::post('/crisis/{alertId}/acknowledge', function ($alertId) {
        // Acknowledge crisis alert
    });

    // Audit Logs (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/audit-logs', function () {
            // List audit logs with filters
        });

        Route::get('/audit-logs/patient/{patient}', function () {
            // Patient-specific audit trail
        });
    });
});

// Reverb WebSocket routes
Route::post('/reverb/auth', function () {
    // Reverb authentication endpoint
});
