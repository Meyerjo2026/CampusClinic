<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEncounterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Only authenticated practitioners (HPCSA/SACSSP registered) can create encounters.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['practitioner', 'cnp', 'gp', 'psychologist', 'pharmacist', 'admin']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],

            // SOAP Subjective
            'soap_subjective' => ['required', 'string', 'max:10000'],
            'soap_subjective.chief_complaint' => ['required', 'string', 'max:500'],
            'soap_subjective.hpi' => ['required', 'string', 'max:5000'],
            'soap_subjective.allergies' => ['sometimes', 'array'],
            'soap_subjective.social_history' => ['sometimes', 'string', 'max:2000'],

            // SOAP Objective
            'soap_objective' => ['required', 'string', 'max:10000'],
            'vital_signs' => ['required', 'array'],
            'vital_signs.blood_pressure_systolic' => ['sometimes', 'integer', 'min:50', 'max:300'],
            'vital_signs.blood_pressure_diastolic' => ['sometimes', 'integer', 'min:30', 'max:200'],
            'vital_signs.heart_rate' => ['sometimes', 'integer', 'min:30', 'max:250'],
            'vital_signs.spo2' => ['sometimes', 'integer', 'min:50', 'max:100'],
            'vital_signs.temperature' => ['sometimes', 'numeric', 'min:30', 'max:45'],
            'vital_signs.blood_glucose' => ['sometimes', 'numeric', 'min:1', 'max:50'],
            'vital_signs.respiratory_rate' => ['sometimes', 'integer', 'min:5', 'max:60'],
            'vital_signs.weight' => ['sometimes', 'numeric', 'min:2', 'max:300'],
            'vital_signs.height' => ['sometimes', 'numeric', 'min:50', 'max:250'],
            'physical_exam_findings' => ['sometimes', 'string', 'max:5000'],

            // SOAP Assessment - ICD-10 codes
            'soap_assessment_icd10' => ['required', 'array', 'min:1'],
            'soap_assessment_icd10.*.code' => ['required', 'string', 'regex:/^[A-Z]\d{2}(\.\d{1,2})?$/'],
            'soap_assessment_icd10.*.display' => ['required', 'string', 'max:200'],
            'soap_assessment_icd10.*.type' => ['sometimes', Rule::in(['primary', 'secondary', 'comorbidity'])],

            // SOAP Plan
            'soap_plan' => ['required', 'string', 'max:10000'],
            'medications_prescribed' => ['sometimes', 'array'],
            'medications_prescribed.*.drug_name' => ['required', 'string'],
            'medications_prescribed.*.dosage' => ['required', 'string'],
            'medications_prescribed.*.frequency' => ['required', 'string'],
            'medications_prescribed.*.duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'medications_prescribed.*.quantity' => ['required', 'integer', 'min:1'],
            'medications_prescribed.*.repeats' => ['sometimes', 'integer', 'min:0', 'max:11'],
            'medications_prescribed.*.sapc_schedule' => ['required', 'integer', 'min:1', 'max:6'],
            'medications_prescribed.*.is_chronic' => ['sometimes', 'boolean'],

            'lab_requisitions' => ['sometimes', 'array'],
            'referrals' => ['sometimes', 'array'],
            'referrals.*.specialty' => ['required', 'string'],
            'referrals.*.reason' => ['required', 'string'],
            'referrals.*.urgency' => ['sometimes', Rule::in(['routine', 'urgent', 'emergency'])],

            'follow_up_instructions' => ['sometimes', 'string', 'max:5000'],
            'follow_up_days' => ['sometimes', 'integer', 'min:1', 'max:365'],

            // Triage level
            'triage_level' => ['sometimes', Rule::in(['emergency', 'urgent', 'routine'])],

            // Mental health high-risk flag
            'is_mental_health_high_risk' => ['sometimes', 'boolean'],

            // Confidentiality level per HPCSA/SACSSP
            'confidentiality_level' => ['sometimes', Rule::in(['standard', 'restricted', 'highly_restricted'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'appointment_id.required' => 'Appointment reference is required.',
            'appointment_id.exists' => 'Selected appointment does not exist.',
            'patient_id.required' => 'Patient reference is required.',
            'patient_id.exists' => 'Selected patient does not exist.',

            'soap_subjective.required' => 'SOAP Subjective section is required (chief complaint, HPI).',
            'soap_subjective.chief_complaint.required' => 'Chief complaint is required.',
            'soap_subjective.hpi.required' => 'History of present illness is required.',

            'soap_objective.required' => 'SOAP Objective section is required (vital signs, exam findings).',
            'vital_signs.required' => 'Vital signs are required for clinical documentation.',

            'soap_assessment_icd10.required' => 'At least one ICD-10 diagnosis code is required.',
            'soap_assessment_icd10.*.code.required' => 'ICD-10 code is required.',
            'soap_assessment_icd10.*.code.regex' => 'Invalid ICD-10 format. Use format like: F32.1, I10, J45.9',
            'soap_assessment_icd10.*.display.required' => 'Diagnosis description is required.',

            'soap_plan.required' => 'SOAP Plan section is required (treatment plan, follow-up).',

            'medications_prescribed.*.sapc_schedule.required' => 'SAPC schedule (1-6) is required for all prescriptions.',
            'medications_prescribed.*.sapc_schedule.min' => 'SAPC schedule must be between 1 and 6.',
            'medications_prescribed.*.sapc_schedule.max' => 'SAPC schedule must be between 1 and 6.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate appointment belongs to patient
            $appointmentId = $this->input('appointment_id');
            $patientId = $this->input('patient_id');

            if ($appointmentId && $patientId) {
                $appointment = Appointment::find($appointmentId);
                if ($appointment && $appointment->patient_id !== $patientId) {
                    $validator->errors()->add('appointment_id', 'Appointment does not belong to the specified patient.');
                }
            }

            // Check for high-risk mental health ICD-10 codes
            $icd10Codes = $this->input('soap_assessment_icd10', []);
            $highRiskCodes = ['F20', 'F21', 'F22', 'F23', 'F24', 'F25', 'F28', 'F29', // Schizophrenia spectrum
                'F30', 'F31', // Bipolar
                'F32', 'F33', // Depression (severe)
                'F40', 'F41', 'F42', 'F43', // Anxiety disorders
                'F60', 'F61', 'F62', 'F63', 'F64', 'F65', 'F66', 'F68', 'F69']; // Personality disorders

            foreach ($icd10Codes as $icd) {
                $code = $icd['code'] ?? '';
                foreach ($highRiskCodes as $hrCode) {
                    if (str_starts_with($code, $hrCode)) {
                        $this->merge(['is_mental_health_high_risk' => true]);
                        break 2;
                    }
                }
            }

            // Validate SAPC schedule 5 & 6 require special handling
            $meds = $this->input('medications_prescribed', []);
            foreach ($meds as $med) {
                if (isset($med['sapc_schedule']) && in_array($med['sapc_schedule'], [5, 6])) {
                    if (empty($med['special_authorization_number'])) {
                        $validator->errors()->add('medications_prescribed.special_authorization_number',
                            "SAPC Schedule {$med['sapc_schedule']} requires special authorization number.");
                    }
                }
            }
        });
    }
}
