<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicationRequest extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'patient_id',
        'prescriber_id',
        'encounter_id',
        'drug_name',
        'generic_name',
        'sapc_schedule',
        'dosage',
        'route',
        'frequency',
        'duration_days',
        'quantity',
        'repeats_issued',
        'repeats_remaining',
        'status',
        'is_chronic',
        'prescribed_at',
        'expires_at',
        'fhir_data',
        'clinical_indication',
        'drug_interactions_checked',
        'allergy_alerts',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sapc_schedule' => 'integer',
        'duration_days' => 'integer',
        'quantity' => 'integer',
        'repeats_issued' => 'integer',
        'repeats_remaining' => 'integer',
        'is_chronic' => 'boolean',
        'prescribed_at' => 'date',
        'expires_at' => 'date',
        'fhir_data' => 'json',
        'drug_interactions_checked' => 'json',
        'allergy_alerts' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the patient associated with the prescription.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the prescriber (practitioner).
     */
    public function prescriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prescriber_id');
    }

    /**
     * Get the originating encounter.
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * Get all dispenses for this prescription.
     */
    public function dispenses(): HasMany
    {
        return $this->hasMany(MedicationDispense::class);
    }

    /**
     * Check if prescription is active and valid for dispensing.
     * Per SAPC: Schedule 1-6 drugs have specific dispensing rules.
     * Schedule 5-6 require special authorization.
     */
    public function isDispensable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Schedule 5 & 6 require additional checks
        if (in_array($this->sapc_schedule, [5, 6])) {
            // Additional authorization checks would go here
            return $this->repeats_remaining > 0 || $this->quantity > 0;
        }

        return $this->repeats_remaining > 0 || $this->quantity > 0;
    }

    /**
     * Check if this is a chronic medication due for refill.
     * Used by SendChronicRefillRemindersJob.
     */
    public function isDueForRefill(int $daysAhead = 5): bool
    {
        if (! $this->is_chronic || $this->status !== 'active') {
            return false;
        }

        // Check if last dispense was more than (duration - daysAhead) days ago
        $lastDispense = $this->dispenses()
            ->where('status', 'dispensed')
            ->latest('dispensed_at')
            ->first();

        if (! $lastDispense) {
            // Never dispensed, check if prescribed recently
            return $this->prescribed_at->diffInDays(now()) >= ($this->duration_days - $daysAhead);
        }

        $daysSinceLastDispense = $lastDispense->dispensed_at->diffInDays(now());

        return $daysSinceLastDispense >= ($this->duration_days - $daysAhead);
    }

    /**
     * Check SAPC schedule compliance.
     * Schedule 1: Over-the-counter
     * Schedule 2: Pharmacy-only (pharmacist initiated sale)
     * Schedule 3: Pharmacist initiated therapy
     * Schedule 4: Prescription only
     * Schedule 5: Prescription only (special control)
     * Schedule 6: Special prescription (narcotics)
     */
    public function getSapcScheduleLabel(): string
    {
        $labels = [
            1 => 'Schedule 1 (OTC)',
            2 => 'Schedule 2 (Pharmacy Only)',
            3 => 'Schedule 3 (Pharmacist Initiated)',
            4 => 'Schedule 4 (Prescription Only)',
            5 => 'Schedule 5 (Special Control)',
            6 => 'Schedule 6 (Narcotic/Controlled)',
        ];

        return $labels[$this->sapc_schedule] ?? 'Unknown Schedule';
    }

    /**
     * Get FHIR R4 MedicationRequest resource representation.
     */
    public function toFhirMedicationRequestResource(): array
    {
        $fhir = $this->fhir_data ?? [];

        return array_merge([
            'resourceType' => 'MedicationRequest',
            'id' => (string) $this->id,
            'status' => $this->status === 'active' ? 'active' : ($this->status === 'cancelled' ? 'cancelled' : 'completed'),
            'intent' => 'order',
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
            'requester' => [
                'reference' => 'Practitioner/'.$this->prescriber_id,
            ],
            'authoredOn' => $this->prescribed_at?->format('c'),
            'dosageInstruction' => [
                [
                    'text' => $this->dosage,
                    'route' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/route-of-administration',
                                'code' => $this->route,
                            ],
                        ],
                    ],
                    'doseAndRate' => [
                        [
                            'type' => [
                                'coding' => [
                                    [
                                        'system' => 'http://terminology.hl7.org/CodeSystem/dose-rate-type',
                                        'code' => 'ordered',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'dispenseRequest' => [
                'validityPeriod' => [
                    'start' => $this->prescribed_at?->format('c'),
                    'end' => $this->expires_at?->format('c'),
                ],
                'numberOfRepeatsAllowed' => $this->repeats_issued,
                'quantity' => [
                    'value' => $this->quantity,
                ],
            ],
        ], $fhir);
    }
}
