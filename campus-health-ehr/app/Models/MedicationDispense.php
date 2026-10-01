<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicationDispense extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'medication_request_id',
        'patient_id',
        'dispenser_id',
        'drug_name',
        'sapc_schedule',
        'batch_number',
        'expires_at',
        'quantity_dispensed',
        'dosage',
        'status',
        'dispensed_at',
        'collected_at',
        'collection_method',
        'qr_code_token',
        'fhir_data',
        'pharmacist_notes',
        'counselling_provided',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sapc_schedule' => 'integer',
        'quantity_dispensed' => 'integer',
        'expires_at' => 'date',
        'dispensed_at' => 'datetime',
        'collected_at' => 'datetime',
        'fhir_data' => 'json',
        'counselling_provided' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the medication request this dispense fulfills.
     */
    public function medicationRequest(): BelongsTo
    {
        return $this->belongsTo(MedicationRequest::class);
    }

    /**
     * Get the patient receiving the medication.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the pharmacist who dispensed the medication.
     */
    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispenser_id');
    }

    /**
     * Mark as dispensed and update medication request.
     * Decrements repeats_remaining on the parent request.
     */
    public function markAsDispensed(): bool
    {
        $this->status = 'dispensed';
        $this->dispensed_at = now();

        $saved = $this->save();

        if ($saved && $this->medicationRequest) {
            $this->medicationRequest->decrement('repeats_remaining');

            if ($this->medicationRequest->repeats_remaining <= 0) {
                $this->medicationRequest->status = 'dispensed';
                $this->medicationRequest->save();
            }
        }

        return $saved;
    }

    /**
     * Mark as collected by patient.
     * Validates QR code token for secure pickup.
     */
    public function markAsCollected(string $qrToken): bool
    {
        if ($this->qr_code_token !== $qrToken) {
            return false;
        }

        $this->status = 'collected';
        $this->collected_at = now();

        return $this->save();
    }

    /**
     * Get FHIR R4 MedicationDispense resource representation.
     */
    public function toFhirMedicationDispenseResource(): array
    {
        $fhir = $this->fhir_data ?? [];

        return array_merge([
            'resourceType' => 'MedicationDispense',
            'id' => (string) $this->id,
            'status' => $this->status,
            'medicationCodeableConcept' => [
                'coding' => [
                    [
                        'system' => 'http://www.sahpra.org.za/medicines',
                        'code' => $this->drug_name,
                        'display' => $this->drug_name,
                    ],
                ],
                'text' => $this->drug_name,
            ],
            'subject' => [
                'reference' => 'Patient/'.$this->patient_id,
            ],
            'performer' => [
                [
                    'actor' => [
                        'reference' => 'Practitioner/'.$this->dispenser_id,
                    ],
                ],
            ],
            'authorizingPrescription' => [
                [
                    'reference' => 'MedicationRequest/'.$this->medication_request_id,
                ],
            ],
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/medicationdispense-category',
                        'code' => 'inpatient', // or outpatient
                    ],
                ],
            ],
            'quantity' => [
                'value' => $this->quantity_dispensed,
                'unit' => 'tablets', // Would be dynamic based on medication
            ],
            'daysSupply' => [
                'value' => $this->medicationRequest?->duration_days ?? 30,
            ],
            'whenPrepared' => $this->dispensed_at?->format('c'),
            'whenHandedOver' => $this->collected_at?->format('c'),
            'dosageInstruction' => [
                [
                    'text' => $this->dosage,
                ],
            ],
        ], $fhir);
    }
}
