<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'patient_id',
        'practitioner_id',
        'service_type',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'duration_minutes',
        'fhir_data',
        'cancellation_reason',
        'is_walk_in',
        'triage_screening',
        'crisis_flag_triggered',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'fhir_data' => 'json',
        'triage_screening' => 'json',
        'crisis_flag_triggered' => 'boolean',
        'is_walk_in' => 'boolean',
        'duration_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the patient associated with the appointment.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the practitioner assigned to the appointment.
     */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'practitioner_id');
    }

    /**
     * Get the encounter associated with the appointment.
     */
    public function encounter(): HasOne
    {
        return $this->hasOne(Encounter::class);
    }

    /**
     * Check if appointment is in a bookable state.
     */
    public function isBookable(): bool
    {
        return in_array($this->status, ['booked', 'cancelled']);
    }

    /**
     * Check if appointment requires medical aid verification.
     * GP appointments require medical aid check per UCT SWS policy.
     */
    public function requiresMedicalAidCheck(): bool
    {
        return $this->service_type === 'gp';
    }

    /**
     * Check if appointment is free (CNP Nurse).
     * CNP Nurse consultations are free per UCT SWS policy.
     */
    public function isFree(): bool
    {
        return $this->service_type === 'cnp';
    }

    /**
     * Check if appointment follows stepped-care protocol.
     * Psychologist appointments follow stepped-care allocation.
     */
    public function requiresSteppedCare(): bool
    {
        return $this->service_type === 'psychologist';
    }

    /**
     * Get FHIR R4 Appointment resource representation.
     */
    public function toFhirAppointmentResource(): array
    {
        $fhir = $this->fhir_data ?? [];

        return array_merge([
            'resourceType' => 'Appointment',
            'id' => (string) $this->id,
            'status' => $this->status,
            'serviceType' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/service-type',
                            'code' => $this->service_type,
                            'display' => ucfirst($this->service_type),
                        ],
                    ],
                ],
            ],
            'appointmentType' => [
                'coding' => [
                    [
                        'system' => 'http://hl7.org/fhir/appointment-type',
                        'code' => $this->service_type,
                    ],
                ],
            ],
            'reasonCode' => [
                [
                    'text' => $this->triage_screening['chief_complaint'] ?? 'Routine consultation',
                ],
            ],
            'start' => $this->scheduled_at?->format('c'),
            'end' => $this->scheduled_at?->addMinutes($this->duration_minutes)->format('c'),
            'minutesDuration' => $this->duration_minutes,
            'participant' => [
                [
                    'actor' => [
                        'reference' => 'Patient/'.$this->patient_id,
                        'display' => $this->patient?->user?->name,
                    ],
                    'status' => 'accepted',
                ],
                [
                    'actor' => [
                        'reference' => 'Practitioner/'.$this->practitioner_id,
                        'display' => $this->practitioner?->name,
                    ],
                    'status' => $this->practitioner_id ? 'accepted' : 'needs-action',
                ],
            ],
        ], $fhir);
    }

    /**
     * Transition appointment status with validation.
     */
    public function transitionStatus(string $newStatus): bool
    {
        $validTransitions = [
            'booked' => ['arrived', 'cancelled', 'noshow'],
            'arrived' => ['in-consultation', 'cancelled', 'noshow'],
            'in-consultation' => ['fulfilled', 'cancelled'],
            'fulfilled' => [],
            'noshow' => ['booked'], // Can rebook after no-show
            'cancelled' => ['booked'], // Can rebook after cancellation
        ];

        if (! isset($validTransitions[$this->status]) || ! in_array($newStatus, $validTransitions[$this->status])) {
            return false;
        }

        $this->status = $newStatus;

        if ($newStatus === 'arrived') {
            $this->started_at = now();
        } elseif ($newStatus === 'fulfilled') {
            $this->ended_at = now();
        }

        return $this->save();
    }
}
