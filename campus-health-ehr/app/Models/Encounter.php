<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Encounter extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'appointment_id',
        'patient_id',
        'practitioner_id',
        'soap_subjective',
        'soap_objective',
        'soap_assessment_icd10',
        'soap_plan',
        'triage_level',
        'fhir_data',
        'vital_signs',
        'allergies',
        'medications_prescribed',
        'referrals',
        'follow_up_instructions',
        'is_mental_health_high_risk',
        'confidentiality_level',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'soap_assessment_icd10' => 'json',
        'fhir_data' => 'json',
        'vital_signs' => 'json',
        'allergies' => 'json',
        'medications_prescribed' => 'json',
        'referrals' => 'json',
        'follow_up_instructions' => 'json',
        'is_mental_health_high_risk' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the appointment associated with the encounter.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Get the patient associated with the encounter.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the practitioner who conducted the encounter.
     */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    /**
     * Get medication requests prescribed during this encounter.
     */
    public function medicationRequests(): HasMany
    {
        return $this->hasMany(MedicationRequest::class);
    }

    /**
     * Check if encounter is mental health high-risk.
     * Triggers special no-show protocol per UCT SWS policy.
     */
    public function isMentalHealthHighRisk(): bool
    {
        return $this->is_mental_health_high_risk;
    }

    /**
     * Get primary ICD-10 diagnosis code.
     */
    public function getPrimaryIcd10(): ?string
    {
        $codes = $this->soap_assessment_icd10;

        return $codes['primary'] ?? ($codes[0]['code'] ?? null);
    }

    /**
     * Get FHIR R4 Encounter resource representation.
     */
    public function toFhirEncounterResource(): array
    {
        $fhir = $this->fhir_data ?? [];

        $diagnosis = [];
        if ($this->soap_assessment_icd10) {
            foreach ($this->soap_assessment_icd10 as $index => $icd) {
                $diagnosis[] = [
                    'condition' => [
                        'coding' => [
                            [
                                'system' => 'http://hl7.org/fhir/sid/icd-10',
                                'code' => $icd['code'] ?? $icd,
                                'display' => $icd['display'] ?? '',
                            ],
                        ],
                    ],
                    'use' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/diagnosis-role',
                                'code' => $index === 0 ? 'DD' : 'SD', // DD = discharge diagnosis, SD = secondary
                            ],
                        ],
                    ],
                    'rank' => $index + 1,
                ];
            }
        }

        return array_merge([
            'resourceType' => 'Encounter',
            'id' => (string) $this->id,
            'status' => 'finished',
            'class' => [
                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                'code' => 'AMB', // Ambulatory
                'display' => 'Ambulatory',
            ],
            'type' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/encounter-type',
                            'code' => $this->appointment?->service_type ?? 'consultation',
                        ],
                    ],
                ],
            ],
            'subject' => [
                'reference' => 'Patient/'.$this->patient_id,
            ],
            'participant' => [
                [
                    'type' => [
                        [
                            'coding' => [
                                [
                                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                                    'code' => 'PPRF',
                                ],
                            ],
                        ],
                    ],
                    'individual' => [
                        'reference' => 'Practitioner/'.$this->practitioner_id,
                    ],
                ],
            ],
            'period' => [
                'start' => $this->appointment?->started_at?->format('c') ?? $this->created_at->format('c'),
                'end' => $this->appointment?->ended_at?->format('c') ?? $this->updated_at->format('c'),
            ],
            'diagnosis' => $diagnosis,
            'serviceProvider' => [
                'reference' => 'Organization/UCT-SWS',
                'display' => 'UCT Student Wellness Service',
            ],
        ], $fhir);
    }
}
