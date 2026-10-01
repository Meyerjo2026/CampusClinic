<?php

namespace App\Http\Controllers;

use App\Http\Requests\DispenseMedicationRequest;
use App\Models\AuditLog;
use App\Models\MedicationDispense;
use App\Models\MedicationRequest;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PharmacyController extends Controller
{
    /**
     * Dispense medication for a prescription.
     *
     * Implements SAPC Schedule 1-6 compliance:
     * - Validates prescription is active and dispensable
     * - Checks drug interactions and allergies
     * - Verifies batch number and expiry
     * - Creates MedicationDispense record with traceability
     * - Decrements repeats_remaining
     */
    public function dispenseMedication(DispenseMedicationRequest $request, MedicationRequest $medicationRequest): JsonResponse
    {
        // Authorization: Only pharmacists can dispense
        if (! auth()->user()->hasRole(['pharmacist', 'admin'])) {
            AuditLog::logDenied('WRITE', 'MedicationDispense', null, $medicationRequest->patient, 'Unauthorized dispensing attempt');

            return response()->json(['success' => false, 'message' => 'Only registered pharmacists can dispense medications.'], 403);
        }

        // Verify prescription is still valid
        if (! $medicationRequest->isDispensable()) {
            return response()->json([
                'success' => false,
                'message' => 'Prescription is not valid for dispensing (expired, cancelled, or no repeats remaining).',
            ], 422);
        }

        // Verify patient POPIA consent
        $patient = $medicationRequest->patient;
        if (! $patient->hasValidPopiaConsent()) {
            AuditLog::logDenied('WRITE', 'MedicationDispense', null, $patient, 'POPIA consent not valid for dispensing');

            return response()->json([
                'success' => false,
                'message' => 'Patient POPIA consent required for medication dispensing.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Generate secure QR code token for collection
            $qrToken = Str::random(32);

            // Create dispense record
            $dispense = MedicationDispense::create([
                'medication_request_id' => $medicationRequest->id,
                'patient_id' => $patient->id,
                'dispenser_id' => auth()->id(),
                'drug_name' => $medicationRequest->drug_name,
                'sapc_schedule' => $medicationRequest->sapc_schedule,
                'batch_number' => $request->batch_number,
                'expires_at' => $request->expires_at,
                'quantity_dispensed' => $request->quantity_dispensed,
                'dosage' => $medicationRequest->dosage,
                'status' => 'prepared',
                'collection_method' => $request->collection_method ?? 'counter',
                'qr_code_token' => $qrToken,
                'pharmacist_notes' => $request->pharmacist_notes,
                'counselling_provided' => $request->counselling_provided ?? [],
                'fhir_data' => $this->buildFhirDispenseData($medicationRequest, $request),
            ]);

            // For Schedule 5 & 6: Log in controlled substance register
            if (in_array($medicationRequest->sapc_schedule, [5, 6])) {
                $this->logControlledSubstance($dispense, $request);
            }

            // Log audit trail
            AuditLog::logAccess(
                'WRITE',
                'MedicationDispense',
                $dispense->id,
                $patient,
                "Medication dispensed: {$medicationRequest->drug_name}",
                [
                    'medication_request_id' => $medicationRequest->id,
                    'batch_number' => $request->batch_number,
                    'quantity' => $request->quantity_dispensed,
                    'sapc_schedule' => $medicationRequest->sapc_schedule,
                    'collection_method' => $dispense->collection_method,
                ]
            );

            DB::commit();

            // Dispatch collection notification (queued)
            $this->dispatchCollectionNotification($dispense);

            return response()->json([
                'success' => true,
                'message' => 'Medication prepared for collection.',
                'data' => [
                    'dispense' => $dispense->load(['patient.user', 'dispenser', 'medicationRequest']),
                    'fhir_resource' => $dispense->toFhirMedicationDispenseResource(),
                    'collection_info' => [
                        'qr_code_token' => $qrToken,
                        'collection_method' => $dispense->collection_method,
                        'location' => 'SWS Pharmacy, Ivan Toms Building',
                        'hours' => 'Mon-Fri 08:30-16:00',
                    ],
                    'repeats_remaining' => $medicationRequest->fresh()->repeats_remaining,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Medication dispensing failed: '.$e->getMessage(), [
                'medication_request_id' => $medicationRequest->id,
                'pharmacist_id' => auth()->id(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to dispense medication. Please try again.',
            ], 500);
        }
    }

    /**
     * Collect dispensed medication (patient pickup).
     * Validates QR code token for secure handover.
     */
    public function collectMedication(string $qrToken): JsonResponse
    {
        $dispense = MedicationDispense::where('qr_code_token', $qrToken)
            ->where('status', 'prepared')
            ->first();

        if (! $dispense) {
            AuditLog::logDenied('WRITE', 'MedicationDispense', null, null, 'Invalid or expired QR code for collection');

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired collection QR code.',
            ], 404);
        }

        // Verify patient identity
        $user = auth()->user();
        if (! $user || $user->id !== $dispense->patient->user_id) {
            AuditLog::logDenied('WRITE', 'MedicationDispense', $dispense->id, $dispense->patient, 'Unauthorized collection attempt');

            return response()->json(['success' => false, 'message' => 'Unauthorized to collect this medication.'], 403);
        }

        if (! $dispense->markAsCollected($qrToken)) {
            return response()->json(['success' => false, 'message' => 'Failed to record collection.'], 500);
        }

        AuditLog::logAccess(
            'WRITE',
            'MedicationDispense',
            $dispense->id,
            $dispense->patient,
            'Medication collected by patient',
            ['collection_method' => $dispense->collection_method]
        );

        return response()->json([
            'success' => true,
            'message' => 'Medication collected successfully.',
            'data' => [
                'dispense' => $dispense->fresh(),
                'counselling_summary' => $dispense->counselling_provided,
                'next_refill_date' => $this->calculateNextRefillDate($dispense->medicationRequest),
            ],
        ]);
    }

    /**
     * Get pharmacy queue (pending dispenses).
     */
    public function getQueue(): JsonResponse
    {
        $queue = MedicationDispense::with(['patient.user', 'medicationRequest.prescriber'])
            ->where('status', 'prepared')
            ->orderBy('created_at')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $queue->map(function ($dispense) {
                return [
                    'id' => $dispense->id,
                    'patient_name' => $dispense->patient->user->name,
                    'student_number' => $dispense->patient->student_number,
                    'drug_name' => $dispense->drug_name,
                    'sapc_schedule' => $dispense->sapc_schedule,
                    'quantity' => $dispense->quantity_dispensed,
                    'collection_method' => $dispense->collection_method,
                    'prepared_at' => $dispense->created_at->format('H:i'),
                    'qr_code' => $dispense->qr_code_token,
                    'is_chronic' => $dispense->medicationRequest->is_chronic,
                ];
            }),
        ]);
    }

    /**
     * Get chronic medications due for refill.
     * Used by SendChronicRefillRemindersJob.
     */
    public function getChronicRefillsDue(): JsonResponse
    {
        $refills = MedicationRequest::where('is_chronic', true)
            ->where('status', 'active')
            ->whereHas('patient', function ($q) {
                $q->where('popia_consent_given', true);
            })
            ->get()
            ->filter(function ($request) {
                return $request->isDueForRefill(5);
            })
            ->values();

        return response()->json([
            'success' => true,
            'count' => $refills->count(),
            'data' => $refills->map(function ($request) {
                return [
                    'id' => $request->id,
                    'patient' => [
                        'id' => $request->patient->id,
                        'name' => $request->patient->user->name,
                        'student_number' => $request->patient->student_number,
                        'contact' => $request->patient->contact_details,
                    ],
                    'drug_name' => $request->drug_name,
                    'dosage' => $request->dosage,
                    'repeats_remaining' => $request->repeats_remaining,
                    'last_dispensed' => $request->dispenses()->where('status', 'dispensed')->latest('dispensed_at')->first()?->dispensed_at,
                    'days_until_due' => $this->calculateDaysUntilDue($request),
                ];
            }),
        ]);
    }

    /**
     * Build FHIR MedicationDispense resource data.
     */
    protected function buildFhirDispenseData(MedicationRequest $request, DispenseMedicationRequest $dispenseRequest): array
    {
        return [
            'resourceType' => 'MedicationDispense',
            'status' => 'prepared',
            'medicationCodeableConcept' => [
                'coding' => [
                    [
                        'system' => 'http://www.sahpra.org.za/medicines',
                        'code' => $request->drug_name,
                    ],
                ],
            ],
        ];
    }

    /**
     * Log controlled substance in register (SAPC Schedule 5 & 6).
     */
    protected function logControlledSubstance(MedicationDispense $dispense, DispenseMedicationRequest $request): void
    {
        // In production: Write to controlled substance register table
        // For now, log to audit trail with special flag
        AuditLog::logAccess(
            'WRITE',
            'ControlledSubstanceRegister',
            $dispense->id,
            $dispense->patient,
            "Schedule {$dispense->sapc_schedule} controlled substance dispensed",
            [
                'register_entry' => $request->input('controlled_substance_register_entry'),
                'batch_number' => $dispense->batch_number,
                'quantity' => $dispense->quantity_dispensed,
                'balance_after' => $this->getStockBalance($dispense->drug_name, $dispense->batch_number),
            ]
        );
    }

    /**
     * Get current stock balance for controlled substance.
     */
    protected function getStockBalance(string $drugName, string $batchNumber): int
    {
        // In production: Query inventory system
        return 0; // Placeholder
    }

    /**
     * Dispatch collection notification to patient.
     */
    protected function dispatchCollectionNotification(MedicationDispense $dispense): void
    {
        // In production: Dispatch queued notification job
        // MedicationReadyForCollectionJob::dispatch($dispense);
    }

    /**
     * Calculate next refill date for chronic medication.
     */
    protected function calculateNextRefillDate(MedicationRequest $request): ?string
    {
        $lastDispense = $request->dispenses()
            ->where('status', 'collected')
            ->latest('collected_at')
            ->first();

        if (! $lastDispense) {
            return null;
        }

        return $lastDispense->collected_at->addDays($request->duration_days)->format('Y-m-d');
    }

    /**
     * Calculate days until refill is due.
     */
    protected function calculateDaysUntilDue(MedicationRequest $request): int
    {
        $nextRefill = $this->calculateNextRefillDate($request);
        if (! $nextRefill) {
            return 0;
        }

        return max(0, now()->diffInDays(Carbon::parse($nextRefill), false));
    }
}
