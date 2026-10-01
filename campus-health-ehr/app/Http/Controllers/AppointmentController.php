<?php

namespace App\Http\Controllers;

use App\Events\CrisisAlertEvent;
use App\Http\Requests\StoreAppointmentRequest;
use App\Jobs\HandleNoShowAppointmentJob;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    /**
     * Store a newly created appointment with intelligent triage.
     *
     * Implements UCT SWS triage protocol:
     * - Mental health crisis (suicidal ideation + plan) -> Crisis Alert
     * - Physical emergency (chest pain, dyspnea, trauma) -> Crisis Alert
     * - Routine -> Slot allocation per service type rules
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        // Check for crisis flags from form request validation
        if ($request->isCrisis()) {
            return $this->handleCrisis($request);
        }

        $user = $request->user();
        $patient = $user->patient;

        if (! $patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient profile not found. Please complete your health profile first.',
            ], 404);
        }

        // Verify POPIA consent for booking
        if (! $patient->hasValidPopiaConsent()) {
            return response()->json([
                'success' => false,
                'message' => 'POPIA consent required for appointment booking. Please provide consent in your profile.',
                'consent_required' => true,
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Determine practitioner assignment based on service type
            $practitionerId = $this->assignPractitioner($request, $patient);

            // Check availability
            $slot = $this->findAvailableSlot($request->service_type, $request->scheduled_at, $practitionerId);

            if (! $slot) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available slots for the requested time. Please select another time.',
                ], 409);
            }

            // Create appointment
            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'practitioner_id' => $practitionerId,
                'service_type' => $request->service_type,
                'status' => 'booked',
                'scheduled_at' => $slot['scheduled_at'],
                'duration_minutes' => $request->duration_minutes ?? $this->getDefaultDuration($request->service_type),
                'is_walk_in' => $request->is_walk_in ?? false,
                'triage_screening' => $request->triage_screening,
                'crisis_flag_triggered' => false,
                'fhir_data' => $this->buildFhirAppointmentData($request, $patient, $practitionerId),
            ]);

            // Log audit trail for booking creation
            AuditLog::logAccess(
                'WRITE',
                'Appointment',
                $appointment->id,
                $patient,
                'Appointment booking via intelligent triage',
                ['service_type' => $request->service_type, 'triage_level' => $this->calculateTriageLevel($request->triage_screening)]
            );

            DB::commit();

            // Dispatch booking confirmation notification (queued)
            $this->dispatchBookingConfirmation($appointment);

            return response()->json([
                'success' => true,
                'message' => 'Appointment booked successfully.',
                'data' => [
                    'appointment' => $appointment->load(['patient.user', 'practitioner']),
                    'fhir_resource' => $appointment->toFhirAppointmentResource(),
                    'slot_details' => [
                        'scheduled_at' => $appointment->scheduled_at->format('c'),
                        'duration_minutes' => $appointment->duration_minutes,
                        'service_type' => $appointment->service_type,
                        'practitioner' => $appointment->practitioner?->name,
                        'location' => $this->getServiceLocation($request->service_type),
                    ],
                    'fee_information' => $this->getFeeInformation($request->service_type, $patient),
                    'preparation_instructions' => $this->getPreparationInstructions($request->service_type),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Appointment booking failed: '.$e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to book appointment. Please try again or contact SWS reception.',
            ], 500);
        }
    }

    /**
     * Handle crisis detection during booking.
     * Immediately halts booking, dispatches Reverb alert, returns emergency hotlines.
     */
    protected function handleCrisis(StoreAppointmentRequest $request): JsonResponse
    {
        $user = $request->user();
        $patient = $user->patient;
        $crisisReasons = $request->getCrisisReasons();

        // Log crisis detection attempt
        if ($patient) {
            AuditLog::logAccess(
                'WRITE',
                'Appointment',
                null,
                $patient,
                'Crisis detected during booking attempt - booking blocked',
                [
                    'crisis_reasons' => $crisisReasons,
                    'triage_screening' => $request->triage_screening,
                    'action' => 'crisis_alert_triggered',
                ],
                true // emergency access flag
            );
        }

        // Dispatch CrisisAlertEvent via Laravel Reverb to SWS-Triage-Dashboard
        $crisisData = [
            'patient_id' => $patient?->id,
            'student_number' => $patient?->student_number,
            'patient_name' => $user->name,
            'crisis_reasons' => $crisisReasons,
            'triage_screening' => $request->triage_screening,
            'timestamp' => now()->toIso8601String(),
            'source' => 'booking_triage',
            'severity' => 'critical',
        ];

        CrisisAlertEvent::dispatch($crisisData);

        // Return immediate HTTP 422 with emergency hotlines
        return response()->json([
            'success' => false,
            'crisis_detected' => true,
            'message' => 'CRISIS DETECTED: Your responses indicate you may be in immediate danger. Booking has been halted. Please contact emergency services NOW.',
            'emergency_hotlines' => $request->getEmergencyHotlines(),
            'crisis_reasons' => $crisisReasons,
            'immediate_actions' => [
                'If you are in immediate danger, call 10111 (Police) or 10177 (Ambulance)',
                'Go directly to Groote Schuur Hospital Emergency Centre',
                'Contact UCT Campus Protection Services: 080 650 2222 (24/7)',
                'Contact UCT Careline (SADAG): 0800 24 25 26 (24/7)',
                'Contact SWS Crisis Line: 021 650 1271 (Office hours: 08:30-16:00)',
            ],
        ], 422);
    }

    /**
     * Assign practitioner based on service type and availability.
     * CNP = Free, GP = Medical aid check, Psychologist = Stepped-care
     */
    protected function assignPractitioner(StoreAppointmentRequest $request, Patient $patient): ?int
    {
        $serviceType = $request->service_type;
        $preferredId = $request->input('triage_screening.preferred_practitioner_id');

        // If patient has preferred practitioner and they're available
        if ($preferredId) {
            $practitioner = User::find($preferredId);
            if ($practitioner && $this->isPractitionerAvailableForService($practitioner, $serviceType)) {
                return $practitioner->id;
            }
        }

        // Find available practitioner for service type
        $query = User::role($this->getRoleForServiceType($serviceType))
            ->whereHas('schedule', function ($q) use ($request) {
                $q->where('day_of_week', Carbon::parse($request->scheduled_at)->dayOfWeekIso)
                    ->where('start_time', '<=', Carbon::parse($request->scheduled_at)->format('H:i'))
                    ->where('end_time', '>=', Carbon::parse($request->scheduled_at)->addMinutes(30)->format('H:i'));
            });

        // For psychologist: apply stepped-care matching
        if ($serviceType === 'psychologist') {
            $steppedCareLevel = $request->input('triage_screening.stepped_care_level', 'medium');
            $query->where('stepped_care_level', $steppedCareLevel);
        }

        $practitioner = $query->inRandomOrder()->first();

        return $practitioner?->id;
    }

    /**
     * Find available time slot for the service type.
     */
    protected function findAvailableSlot(string $serviceType, string $requestedTime, ?int $practitionerId): ?array
    {
        $requested = Carbon::parse($requestedTime);
        $duration = $this->getDefaultDuration($serviceType);

        // Check if requested slot is available
        $conflict = Appointment::where('practitioner_id', $practitionerId)
            ->where('scheduled_at', '<', $requested->copy()->addMinutes($duration))
            ->where('scheduled_at', '>', $requested->copy()->subMinutes($duration))
            ->where('status', '!=', 'cancelled')
            ->exists();

        if (! $conflict) {
            return ['scheduled_at' => $requested];
        }

        // Find next available slot (simple implementation - in production use scheduler)
        for ($i = 1; $i <= 20; $i++) {
            $nextSlot = $requested->copy()->addMinutes($i * 30);
            $conflict = Appointment::where('practitioner_id', $practitionerId)
                ->where('scheduled_at', '<', $nextSlot->copy()->addMinutes($duration))
                ->where('scheduled_at', '>', $nextSlot->copy()->subMinutes($duration))
                ->where('status', '!=', 'cancelled')
                ->exists();

            if (! $conflict) {
                return ['scheduled_at' => $nextSlot];
            }
        }

        return null;
    }

    /**
     * Get default duration for service type.
     */
    protected function getDefaultDuration(string $serviceType): int
    {
        return match ($serviceType) {
            'cnp' => 20,
            'gp' => 30,
            'psychologist' => 50,
            'pharmacy_pickup' => 10,
            default => 30,
        };
    }

    /**
     * Get role name for service type.
     */
    protected function getRoleForServiceType(string $serviceType): string
    {
        return match ($serviceType) {
            'cnp' => 'cnp',
            'gp' => 'gp',
            'psychologist' => 'psychologist',
            'pharmacy_pickup' => 'pharmacist',
            default => 'practitioner',
        };
    }

    /**
     * Check if practitioner is available for service type.
     */
    protected function isPractitionerAvailableForService(User $practitioner, string $serviceType): bool
    {
        $requiredRole = $this->getRoleForServiceType($serviceType);

        return $practitioner->hasRole($requiredRole);
    }

    /**
     * Calculate triage level from screening.
     */
    protected function calculateTriageLevel(array $screening): string
    {
        $severity = $screening['severity'] ?? 'routine';

        return match ($severity) {
            'critical' => 'emergency',
            'severe' => 'urgent',
            default => 'routine',
        };
    }

    /**
     * Build FHIR Appointment resource data.
     */
    protected function buildFhirAppointmentData(StoreAppointmentRequest $request, Patient $patient, ?int $practitionerId): array
    {
        return [
            'resourceType' => 'Appointment',
            'status' => 'booked',
            'serviceType' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/service-type',
                            'code' => $request->service_type,
                        ],
                    ],
                ],
            ],
            'reasonCode' => [
                [
                    'text' => $request->input('triage_screening.chief_complaint'),
                ],
            ],
        ];
    }

    /**
     * Get service location for service type.
     */
    protected function getServiceLocation(string $serviceType): string
    {
        return match ($serviceType) {
            'cnp', 'gp' => 'Ivan Toms Building - Clinical Wing',
            'psychologist' => 'Ivan Toms Building - Psychological Services',
            'pharmacy_pickup' => 'Ivan Toms Building - SWS Pharmacy',
            default => 'Ivan Toms Building',
        };
    }

    /**
     * Get fee information for service type.
     */
    protected function getFeeInformation(string $serviceType, Patient $patient): array
    {
        return match ($serviceType) {
            'cnp' => [
                'fee' => 0,
                'description' => 'CNP Nurse consultations are free for registered students.',
                'medical_aid_required' => false,
            ],
            'gp' => [
                'fee' => 450.00, // Example rate
                'description' => 'GP consultation fee. Medical aid will be billed directly.',
                'medical_aid_required' => true,
                'medical_aid_verified' => $patient->medical_aid_verified ?? false,
            ],
            'psychologist' => [
                'fee' => 0,
                'description' => 'Psychology consultations are covered by UCT SWS (stepped-care model).',
                'medical_aid_required' => false,
            ],
            'pharmacy_pickup' => [
                'fee' => 0,
                'description' => 'Medication collection only. Prescription fees apply separately.',
                'medical_aid_required' => false,
            ],
            default => [
                'fee' => 0,
                'description' => 'Please contact reception for fee information.',
                'medical_aid_required' => false,
            ],
        };
    }

    /**
     * Get preparation instructions for service type.
     */
    protected function getPreparationInstructions(string $serviceType): array
    {
        return match ($serviceType) {
            'cnp' => [
                'Bring your student card',
                'Bring any current medications',
                'Wear comfortable clothing for vital signs',
            ],
            'gp' => [
                'Bring your student card and medical aid card',
                'Bring any current medications and referral letters',
                'Fast for 8 hours if blood work is anticipated',
                'Wear comfortable clothing for examination',
            ],
            'psychologist' => [
                'Bring your student card',
                'Complete any pre-session questionnaires sent via email',
                'Consider what you want to discuss in therapy',
            ],
            'pharmacy_pickup' => [
                'Bring your student card',
                'Bring the QR code sent via SMS/Email',
                'Know your medication names for verification',
            ],
            default => [
                'Bring your student card',
            ],
        };
    }

    /**
     * Dispatch booking confirmation notification (queued).
     */
    protected function dispatchBookingConfirmation(Appointment $appointment): void
    {
        // In production: dispatch queued notification job
        // BookingConfirmationJob::dispatch($appointment);
    }

    /**
     * Display the specified appointment.
     */
    public function show(Appointment $appointment): JsonResponse
    {
        // Check authorization - patient can only see their own appointments
        if (auth()->id() !== $appointment->patient->user_id && ! auth()->user()->hasRole(['practitioner', 'admin'])) {
            AuditLog::logDenied('READ', 'Appointment', $appointment->id, $appointment->patient);

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Log audit access
        AuditLog::logAccess('READ', 'Appointment', $appointment->id, $appointment->patient);

        return response()->json([
            'success' => true,
            'data' => [
                'appointment' => $appointment->load(['patient.user', 'practitioner', 'encounter']),
                'fhir_resource' => $appointment->toFhirAppointmentResource(),
            ],
        ]);
    }

    /**
     * Update appointment status (arrived, in-consultation, fulfilled, cancelled, noshow).
     */
    public function updateStatus(Appointment $appointment, string $status): JsonResponse
    {
        $validStatuses = ['arrived', 'in-consultation', 'fulfilled', 'cancelled', 'noshow'];

        if (! in_array($status, $validStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status transition.',
            ], 422);
        }

        // Authorization check
        $user = auth()->user();
        $canUpdate = $user->hasRole(['practitioner', 'admin']) || $user->id === $appointment->patient->user_id;

        if (! $canUpdate) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $oldStatus = $appointment->status;

        if (! $appointment->transitionStatus($status)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid status transition from {$oldStatus} to {$status}.",
            ], 422);
        }

        // Log audit trail
        AuditLog::logAccess(
            'WRITE',
            'Appointment',
            $appointment->id,
            $appointment->patient,
            "Status changed from {$oldStatus} to {$status}",
            ['old_status' => $oldStatus, 'new_status' => $status]
        );

        // If no-show, trigger no-show handler job
        if ($status === 'noshow') {
            HandleNoShowAppointmentJob::dispatch($appointment);
        }

        return response()->json([
            'success' => true,
            'message' => "Appointment status updated to {$status}.",
            'data' => $appointment->fresh(),
        ]);
    }
}
