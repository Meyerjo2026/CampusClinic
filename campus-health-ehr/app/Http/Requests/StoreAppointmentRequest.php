<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Students can book their own appointments; practitioners can book for patients.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_type' => ['required', Rule::in(['cnp', 'gp', 'psychologist', 'pharmacy_pickup'])],
            'scheduled_at' => ['required', 'date', 'after_or_equal:now'],
            'duration_minutes' => ['sometimes', 'integer', 'min:15', 'max:120'],
            'is_walk_in' => ['sometimes', 'boolean'],

            // Triage screening fields - required for intelligent triage
            'triage_screening' => ['required', 'array'],
            'triage_screening.chief_complaint' => ['required', 'string', 'max:500'],
            'triage_screening.symptoms' => ['required', 'array', 'min:1'],
            'triage_screening.symptoms.*' => ['string', 'max:100'],
            'triage_screening.severity' => ['required', Rule::in(['mild', 'moderate', 'severe', 'critical'])],
            'triage_screening.suicidal_ideation' => ['sometimes', 'boolean'],
            'triage_screening.suicidal_plan' => ['sometimes', 'boolean'],
            'triage_screening.self_harm_risk' => ['sometimes', 'boolean'],
            'triage_screening.chest_pain' => ['sometimes', 'boolean'],
            'triage_screening.shortness_of_breath' => ['sometimes', 'boolean'],
            'triage_screening.acute_trauma' => ['sometimes', 'boolean'],
            'triage_screening.allergies' => ['sometimes', 'array'],
            'triage_screening.current_medications' => ['sometimes', 'array'],
            'triage_screening.preferred_practitioner_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'service_type.required' => 'Service type is required (CNP, GP, Psychologist, or Pharmacy Pickup).',
            'service_type.in' => 'Invalid service type. Must be one of: cnp, gp, psychologist, pharmacy_pickup.',
            'scheduled_at.required' => 'Appointment date and time is required.',
            'scheduled_at.after_or_equal' => 'Appointment must be scheduled for a future date/time.',
            'triage_screening.required' => 'Triage screening is mandatory for all bookings per UCT SWS protocol.',
            'triage_screening.chief_complaint.required' => 'Chief complaint is required for clinical triage.',
            'triage_screening.symptoms.required' => 'At least one symptom must be reported.',
            'triage_screening.severity.required' => 'Symptom severity assessment is required.',
            'triage_screening.severity.in' => 'Severity must be: mild, moderate, severe, or critical.',
        ];
    }

    /**
     * Configure the validator instance.
     * Add custom validation logic for crisis detection.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $screening = $this->input('triage_screening', []);

            // Crisis detection: Suicidal ideation with plan OR acute physical emergency
            $isCrisis = false;
            $crisisReasons = [];

            // Mental health crisis flags (per UCT SWS Crisis Intervention SOP)
            if ($screening['suicidal_ideation'] && $screening['suicidal_plan']) {
                $isCrisis = true;
                $crisisReasons[] = 'Suicidal ideation with plan detected';
            }

            if ($screening['self_harm_risk'] && $screening['severity'] === 'critical') {
                $isCrisis = true;
                $crisisReasons[] = 'Critical self-harm risk detected';
            }

            // Physical emergency flags
            if ($screening['chest_pain'] && $screening['severity'] === 'critical') {
                $isCrisis = true;
                $crisisReasons[] = 'Acute chest pain - possible cardiac emergency';
            }

            if ($screening['shortness_of_breath'] && $screening['severity'] === 'critical') {
                $isCrisis = true;
                $crisisReasons[] = 'Acute dyspnea - respiratory emergency';
            }

            if ($screening['acute_trauma'] && $screening['severity'] === 'critical') {
                $isCrisis = true;
                $crisisReasons[] = 'Acute trauma - requires emergency department';
            }

            // Store crisis flag for controller to use
            $this->merge(['crisis_detected' => $isCrisis, 'crisis_reasons' => $crisisReasons]);

            // Validate service-specific rules
            $serviceType = $this->input('service_type');

            if ($serviceType === 'psychologist') {
                // Stepped-care validation: Check if patient qualifies for psychologist vs peer support
                if (! isset($screening['stepped_care_level'])) {
                    $validator->errors()->add('triage_screening.stepped_care_level',
                        'Stepped-care level assessment required for psychologist booking.');
                }
            }

            if ($serviceType === 'gp') {
                // Medical aid check required for GP
                $patient = $this->user()->patient ?? null;
                if ($patient && ! $patient->medical_aid_verified) {
                    $validator->errors()->add('medical_aid',
                        'Medical aid verification required for GP consultation. Please verify your medical aid details.');
                }
            }
        });
    }

    /**
     * Check if crisis was detected during validation.
     */
    public function isCrisis(): bool
    {
        return $this->input('crisis_detected', false);
    }

    /**
     * Get crisis reasons.
     */
    public function getCrisisReasons(): array
    {
        return $this->input('crisis_reasons', []);
    }

    /**
     * Get emergency hotlines for crisis response.
     * Per UCT SWS Crisis Intervention Flowchart.
     */
    public function getEmergencyHotlines(): array
    {
        return [
            'SWS_Crisis_Line' => '021 650 1271 (Office Hours: 08:30–16:00)',
            'UCT_Careline_SADAG' => '0800 24 25 26 (24/7, operated by SADAG)',
            'UCT_Campus_Protection' => '080 650 2222 (24/7 Campus Protection Services)',
            'HIGHER_HEALTH_Helpline' => '0800 36 36 36 (National 24/7 Student Helpline)',
            'SADAG_Suicide_Crisis' => '0800 567 567 (Suicide Crisis Line)',
            'Emergency_Services' => '10111 (Police) / 10177 (Ambulance)',
            'Nearest_ED' => 'Groote Schuur Hospital Emergency Centre',
        ];
    }
}
