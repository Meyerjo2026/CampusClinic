<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Patient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePatientDataConsent
{
    /**
     * Protected resource types that require POPIA consent.
     */
    protected array $protectedResources = [
        'patients',
        'encounters',
        'appointments',
        'medication-requests',
        'medication-dispenses',
    ];

    /**
     * Handle an incoming request.
     *
     * POPIA Section 26 & 32: Health data is Special Personal Information
     * Requires explicit, informed consent for processing.
     * Every access must be logged in immutable audit trail.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for non-API routes or auth endpoints
        if (! $request->is('api/*') || $request->is('api/auth/*')) {
            return $next($request);
        }

        // Get patient ID from route parameters or query
        $patientId = $this->extractPatientId($request);

        if (! $patientId) {
            // No patient context - allow but log if accessing protected resource
            if ($this->isProtectedResource($request)) {
                AuditLog::logAccess(
                    'READ',
                    $this->getResourceType($request),
                    $this->getResourceId($request),
                    null,
                    'System-level access (no patient context)',
                    ['ip' => $request->ip(), 'route' => $request->route()?->getName()]
                );
            }

            return $next($request);
        }

        $patient = Patient::withTrashed()->find($patientId);

        if (! $patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found.',
            ], 404);
        }

        // Check if patient has valid POPIA consent
        $hasConsent = $patient->hasValidPopiaConsent();
        $isEmergency = $this->isEmergencyAccess($request);

        // Emergency access override (HPCSA statutory exemption)
        // Imminent risk to life or safety of student or others
        if (! $hasConsent && ! $isEmergency) {
            // Log denied access
            AuditLog::logDenied(
                'READ',
                $this->getResourceType($request),
                $this->getResourceId($request),
                $patient,
                'POPIA consent not given or revoked'
            );

            return response()->json([
                'success' => false,
                'message' => 'Access denied: Patient POPIA consent required for health data access.',
                'error_code' => 'POPIA_CONSENT_REQUIRED',
                'consent_status' => [
                    'given' => $patient->popia_consent_given,
                    'consented_at' => $patient->popia_consent_at?->toIso8601String(),
                ],
            ], 403);
        }

        // Log the access (even for emergency)
        AuditLog::logAccess(
            $this->getActionFromMethod($request->method()),
            $this->getResourceType($request),
            $this->getResourceId($request),
            $patient,
            $this->getClinicalReason($request),
            [
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'params' => $request->route()?->parameters(),
            ],
            $isEmergency
        );

        // Add consent info to request for downstream use
        $request->merge([
            'patient_popia_consent' => $hasConsent,
            'patient_emergency_access' => $isEmergency,
        ]);

        return $next($request);
    }

    /**
     * Extract patient ID from request.
     */
    protected function extractPatientId(Request $request): ?int
    {
        // Check route parameters
        $params = $request->route()?->parameters() ?? [];

        foreach (['patient', 'patient_id', 'id'] as $key) {
            if (isset($params[$key])) {
                $value = $params[$key];
                if ($value instanceof Patient) {
                    return $value->id;
                }
                if (is_numeric($value)) {
                    return (int) $value;
                }
            }
        }

        // Check query parameters
        if ($request->has('patient_id')) {
            return (int) $request->query('patient_id');
        }

        // Check if authenticated user is a patient
        if ($request->user()?->patient) {
            return $request->user()->patient->id;
        }

        return null;
    }

    /**
     * Check if request is for a protected resource.
     */
    protected function isProtectedResource(Request $request): bool
    {
        $routeName = $request->route()?->getName() ?? '';
        $path = $request->path();

        foreach ($this->protectedResources as $resource) {
            if (str_contains($routeName, $resource) || str_contains($path, $resource)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get resource type from request.
     */
    protected function getResourceType(Request $request): string
    {
        $routeName = $request->route()?->getName() ?? '';

        foreach ($this->protectedResources as $resource) {
            if (str_contains($routeName, $resource) || str_contains($request->path(), $resource)) {
                return ucfirst(str_replace('-', ' ', $resource));
            }
        }

        return 'Unknown';
    }

    /**
     * Get resource ID from request.
     */
    protected function getResourceId(Request $request): ?int
    {
        $params = $request->route()?->parameters() ?? [];

        foreach (['id', 'appointment', 'encounter', 'medication_request', 'medication_dispense'] as $key) {
            if (isset($params[$key])) {
                $value = $params[$key];
                if (is_numeric($value)) {
                    return (int) $value;
                }
                if (method_exists($value, 'getKey')) {
                    return $value->getKey();
                }
            }
        }

        return null;
    }

    /**
     * Determine action from HTTP method.
     */
    protected function getActionFromMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD' => 'READ',
            'POST' => 'WRITE',
            'PUT', 'PATCH' => 'WRITE',
            'DELETE' => 'DELETE',
            default => 'READ',
        };
    }

    /**
     * Check if request is emergency access.
     * Emergency access requires explicit header or parameter.
     */
    protected function isEmergencyAccess(Request $request): bool
    {
        // Check for emergency access header (set by crisis system)
        if ($request->header('X-Emergency-Access') === 'true') {
            return true;
        }

        // Check for emergency reason in request
        if ($request->input('emergency_reason')) {
            return true;
        }

        // Check if practitioner has emergency override permission
        if ($request->user()?->hasPermissionTo('emergency-record-access')) {
            return true;
        }

        return false;
    }

    /**
     * Get clinical reason for access from request.
     */
    protected function getClinicalReason(Request $request): ?string
    {
        // Try to get reason from request
        if ($request->has('clinical_reason')) {
            return $request->input('clinical_reason');
        }

        // Infer from route/action
        $action = $request->route()?->getActionMethod() ?? '';

        return match (true) {
            str_contains($action, 'show') => 'Clinical record review',
            str_contains($action, 'index') => 'Patient record listing',
            str_contains($action, 'store') => 'New clinical documentation',
            str_contains($action, 'update') => 'Clinical record update',
            str_contains($action, 'destroy') => 'Record deletion (audit)',
            default => 'Healthcare operations',
        };
    }
}
