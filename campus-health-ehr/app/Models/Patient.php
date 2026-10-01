<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Patient extends Model
{
    use HasFactory, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'fhir_data',
        'popia_consent_given',
        'popia_consent_at',
        'consent_metadata',
        'student_number',
        'date_of_birth',
        'gender',
        'contact_details',
        'address',
        'emergency_contact',
        'is_high_risk_mental_health',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fhir_data' => 'json',
        'popia_consent_given' => 'boolean',
        'popia_consent_at' => 'datetime',
        'consent_metadata' => 'json',
        'date_of_birth' => 'date',
        'contact_details' => 'json',
        'address' => 'json',
        'emergency_contact' => 'json',
        'is_high_risk_mental_health' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user associated with the patient.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all appointments for the patient.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Get all encounters for the patient.
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    /**
     * Get all medication requests for the patient.
     */
    public function medicationRequests(): HasMany
    {
        return $this->hasMany(MedicationRequest::class);
    }

    /**
     * Get all medication dispenses for the patient.
     */
    public function medicationDispenses(): HasMany
    {
        return $this->hasMany(MedicationDispense::class);
    }

    /**
     * Get all audit logs for the patient.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Check if patient has valid POPIA consent.
     * POPIA Section 26 & 32: Special personal information requires explicit consent.
     */
    public function hasValidPopiaConsent(): bool
    {
        return $this->popia_consent_given && $this->popia_consent_at !== null;
    }

    /**
     * Get FHIR R4 Patient resource representation.
     * Maps internal model to FHIR R4 Patient JSON structure.
     */
    public function toFhirPatientResource(): array
    {
        $fhir = $this->fhir_data ?? [];

        return array_merge([
            'resourceType' => 'Patient',
            'id' => (string) $this->id,
            'identifier' => [
                [
                    'system' => 'urn:oid:2.16.840.1.113883.2.4.3.1', // South African ID system
                    'value' => $this->student_number,
                ],
            ],
            'active' => ! $this->deleted_at,
            'name' => [
                [
                    'use' => 'official',
                    'text' => $this->user?->name,
                ],
            ],
            'gender' => $this->gender,
            'birthDate' => $this->date_of_birth?->format('Y-m-d'),
            'telecom' => $this->contact_details ?? [],
            'address' => $this->address ?? [],
        ], $fhir);
    }
}
