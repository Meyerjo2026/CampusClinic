<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * Indicates if the model should be timestamped.
     * We use custom 'timestamp' field instead of created_at/updated_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'operator_id',
        'patient_id',
        'action',
        'resource_type',
        'resource_id',
        'ip_address',
        'user_agent',
        'session_id',
        'reason',
        'metadata',
        'consent_status',
        'is_emergency_access',
        'timestamp',
        'outcome',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'resource_id' => 'integer',
        'metadata' => 'json',
        'is_emergency_access' => 'boolean',
        'timestamp' => 'datetime',
    ];

    /**
     * Get the operator (user) who performed the action.
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /**
     * Get the patient whose data was accessed.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Create an audit log entry.
     * This method should be used instead of direct model creation
     * to ensure immutability and compliance.
     *
     * POPIA Section 18 & HIPAA §164.312(b) - Audit Controls
     */
    public static function logAccess(
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?Patient $patient = null,
        ?string $reason = null,
        array $metadata = [],
        bool $isEmergency = false
    ): self {
        $user = auth()->user();
        $patientId = $patient?->id ?? $metadata['patient_id'] ?? null;
        $consentStatus = 'valid';

        if ($patient && ! $patient->hasValidPopiaConsent() && ! $isEmergency) {
            $consentStatus = 'revoked';
        } elseif ($isEmergency) {
            $consentStatus = 'emergency_override';
        }

        return self::create([
            'operator_id' => $user?->id,
            'patient_id' => $patientId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => request()->session()->getId(),
            'reason' => $reason,
            'metadata' => $metadata,
            'consent_status' => $consentStatus,
            'is_emergency_access' => $isEmergency,
            'timestamp' => now(),
            'outcome' => 'success',
        ]);
    }

    /**
     * Log a denied access attempt.
     */
    public static function logDenied(
        string $action,
        string $resourceType,
        ?int $resourceId = null,
        ?Patient $patient = null,
        string $reason = 'Access denied'
    ): self {
        return self::create([
            'operator_id' => auth()->id(),
            'patient_id' => $patient?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => request()->session()->getId(),
            'reason' => $reason,
            'consent_status' => $patient?->hasValidPopiaConsent() ? 'valid' : 'revoked',
            'is_emergency_access' => false,
            'timestamp' => now(),
            'outcome' => 'denied',
        ]);
    }
}
