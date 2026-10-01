<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEncounterRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Encounter;
use App\Models\MedicationRequest;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClinicalEncounterController extends Controller
{
    /**
     * Store a newly created clinical encounter with SOAP notes.
     *
     * Implements HPCSA/SACSSP compliant documentation:
     * - Subjective: Chief complaint, HPI, allergies, social history
     * - Objective: Vital signs, physical exam findings
     * - Assessment: ICD-10 diagnostic codes (primary & secondary)
     * - Plan: e-Prescriptions, lab requisitions, referrals, follow-up
     */
    public function store(StoreEncounterRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify POPIA consent before creating encounter
        $patient = Patient::find($request->patient_id);
        if (! $patient || ! $patient->hasValidPopiaConsent()) {
            return response()->json([
                'success' => false,
                'message' => 'Patient POPIA consent required for clinical documentation.',
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Get appointment if provided
            $appointment = Appointment::find($request->appointment_id);

            // Update appointment status to in-consultation
            if ($appointment && $appointment->status === 'arrived') {
                $appointment->transitionStatus('in-consultation');
            }

            // Create encounter
            $encounter = Encounter::create([
                'appointment_id' => $request->appointment_id,
                'patient_id' => $request->patient_id,
                'practitioner_id' => $user->id,
                'soap_subjective' => $request->input('soap_subjective'),
                'soap_objective' => $request->input('soap_objective'),
                'soap_assessment_icd10' => $request->input('soap_assessment_icd10'),
                'soap_plan' => $request->input('soap_plan'),
                'triage_level' => $request->input('triage_level', 'routine'),
                'vital_signs' => $request->input('vital_signs'),
                'allergies' => $request->input('soap_subjective.allergies'),
                'medications_prescribed' => $request->input('medications_prescribed'),
                'referrals' => $request->input('referrals'),
                'follow_up_instructions' => $request->input('follow_up_instructions'),
                'is_mental_health_high_risk' => $request->input('is_mental_health_high_risk', false),
                'confidentiality_level' => $request->input('confidentiality_level', 'standard'),
                'fhir_data' => $this->buildFhirEncounterData($request, $user),
            ]);

            // Process medication prescriptions
            if ($request->has('medications_prescribed')) {
                $this->processPrescriptions($encounter, $request->input('medications_prescribed'));
            }

            // Update appointment to fulfilled if exists
            if ($appointment) {
                $appointment->transitionStatus('fulfilled');
            }

            // Log audit trail
            AuditLog::logAccess(
                'WRITE',
                'Encounter',
                $encounter->id,
                $patient,
                'Clinical encounter documented with SOAP notes',
                [
                    'icd10_codes' => collect($request->input('soap_assessment_icd10'))->pluck('code')->toArray(),
                    'triage_level' => $encounter->triage_level,
                    'is_mental_health_high_risk' => $encounter->is_mental_health_high_risk,
                    'medications_prescribed' => count($request->input('medications_prescribed', [])),
                ]
            );

            // If mental health high-risk, flag for care continuum
            if ($encounter->is_mental_health_high_risk) {
                $patient->update(['is_high_risk_mental_health' => true]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Clinical encounter documented successfully.',
                'data' => [
                    'encounter' => $encounter->load(['patient.user', 'practitioner', 'appointment', 'medicationRequests']),
                    'fhir_resource' => $encounter->toFhirEncounterResource(),
                    'prescriptions_created' => $encounter->medicationRequests->count(),
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Encounter creation failed: '.$e->getMessage(), [
                'practitioner_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to document encounter. Please try again.',
            ], 500);
        }
    }

    /**
     * Process medication prescriptions from encounter.
     */
    protected function processPrescriptions(Encounter $encounter, array $prescriptions): void
    {
        foreach ($prescriptions as $prescription) {
            MedicationRequest::create([
                'patient_id' => $encounter->patient_id,
                'prescriber_id' => $encounter->practitioner_id,
                'encounter_id' => $encounter->id,
                'drug_name' => $prescription['drug_name'],
                'generic_name' => $prescription['generic_name'] ?? null,
                'sapc_schedule' => $prescription['sapc_schedule'],
                'dosage' => $prescription['dosage'],
                'route' => $prescription['route'] ?? 'oral',
                'frequency' => $prescription['frequency'],
                'duration_days' => $prescription['duration_days'],
                'quantity' => $prescription['quantity'],
                'repeats_issued' => $prescription['repeats'] ?? 0,
                'repeats_remaining' => $prescription['repeats'] ?? 0,
                'status' => 'active',
                'is_chronic' => $prescription['is_chronic'] ?? false,
                'prescribed_at' => now()->toDateString(),
                'expires_at' => now()->addMonths(6)->toDateString(), // SAPC: 6 month validity
                'clinical_indication' => $encounter->getPrimaryIcd10(),
                'fhir_data' => [
                    'resourceType' => 'MedicationRequest',
                    'status' => 'active',
                    'intent' => 'order',
                ],
            ]);
        }
    }

    /**
     * Build FHIR Encounter resource data.
     */
    protected function buildFhirEncounterData(StoreEncounterRequest $request, $practitioner): array
    {
        return [
            'resourceType' => 'Encounter',
            'status' => 'in-progress',
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => 'AMB',
            ],
        ];
    }

    /**
     * Display the specified encounter.
     * Requires POPIA consent verification via middleware.
     */
    public function show(Encounter $encounter): JsonResponse
    {
        // Log audit access
        AuditLog::logAccess(
            'READ',
            'Encounter',
            $encounter->id,
            $encounter->patient,
            'Clinical encounter viewed',
            ['confidentiality_level' => $encounter->confidentiality_level]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'encounter' => $encounter->load(['patient.user', 'practitioner', 'appointment', 'medicationRequests']),
                'fhir_resource' => $encounter->toFhirEncounterResource(),
                'soap_summary' => [
                    'subjective' => $encounter->soap_subjective,
                    'objective' => $encounter->soap_objective,
                    'assessment' => $encounter->soap_assessment_icd10,
                    'plan' => $encounter->soap_plan,
                ],
            ],
        ]);
    }

    /**
     * List encounters for a patient (with pagination).
     */
    public function index(Patient $patient): JsonResponse
    {
        // Verify POPIA consent
        if (! $patient->hasValidPopiaConsent()) {
            AuditLog::logDenied('READ', 'Encounter', null, $patient, 'POPIA consent not valid');

            return response()->json([
                'success' => false,
                'message' => 'Patient POPIA consent required to view clinical records.',
            ], 403);
        }

        // Log audit access
        AuditLog::logAccess(
            'READ',
            'Encounter',
            null,
            $patient,
            'Patient encounter list accessed'
        );

        $encounters = $patient->encounters()
            ->with(['practitioner', 'appointment'])
            ->latest('created_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $encounters->map(function ($encounter) {
                return [
                    'id' => $encounter->id,
                    'date' => $encounter->created_at->format('Y-m-d'),
                    'practitioner' => $encounter->practitioner->name,
                    'service_type' => $encounter->appointment?->service_type,
                    'primary_diagnosis' => $encounter->getPrimaryIcd10(),
                    'triage_level' => $encounter->triage_level,
                    'is_mental_health_high_risk' => $encounter->is_mental_health_high_risk,
                    'fhir_resource' => $encounter->toFhirEncounterResource(),
                ];
            }),
        ]);
    }

    /**
     * Update encounter (addendum/correction).
     * Per HPCSA: Corrections must be additive, not destructive.
     */
    public function update(StoreEncounterRequest $request, Encounter $encounter): JsonResponse
    {
        // Only the original practitioner or admin can update
        if (auth()->id() !== $encounter->practitioner_id && ! auth()->user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            // Create addendum rather than overwriting
            $addendum = [
                'added_by' => auth()->id(),
                'added_at' => now()->toIso8601String(),
                'reason' => $request->input('addendum_reason', 'Clinical addendum'),
                'subjective' => $request->input('soap_subjective'),
                'objective' => $request->input('soap_objective'),
                'assessment' => $request->input('soap_assessment_icd10'),
                'plan' => $request->input('soap_plan'),
            ];

            $encounter->update([
                'soap_subjective' => $encounter->soap_subjective."\n\n[ADDENDUM - ".now()->format('Y-m-d H:i')."]\n".$request->input('soap_subjective'),
                'soap_objective' => $encounter->soap_objective."\n\n[ADDENDUM - ".now()->format('Y-m-d H:i')."]\n".$request->input('soap_objective'),
                'soap_assessment_icd10' => $request->input('soap_assessment_icd10'),
                'soap_plan' => $encounter->soap_plan."\n\n[ADDENDUM - ".now()->format('Y-m-d H:i')."]\n".$request->input('soap_plan'),
                'fhir_data' => array_merge($encounter->fhir_data ?? [], [
                    'addendums' => array_merge($encounter->fhir_data['addendums'] ?? [], [$addendum]),
                ]),
            ]);

            AuditLog::logAccess(
                'WRITE',
                'Encounter',
                $encounter->id,
                $encounter->patient,
                'Clinical encounter addendum added',
                ['addendum' => $addendum]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Encounter updated with addendum.',
                'data' => $encounter->fresh(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Encounter update failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to update encounter.'], 500);
        }
    }
}
