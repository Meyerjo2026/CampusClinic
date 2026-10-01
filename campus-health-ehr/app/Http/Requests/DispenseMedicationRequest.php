<?php

namespace App\Http\Requests;

use App\Models\MedicationRequest;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispenseMedicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Only pharmacists (SAPC registered) can dispense medications.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['pharmacist', 'admin']) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'medication_request_id' => ['required', 'integer', 'exists:medication_requests,id'],
            'batch_number' => ['required', 'string', 'max:50'],
            'expires_at' => ['required', 'date', 'after_or_equal:today'],
            'quantity_dispensed' => ['required', 'integer', 'min:1'],
            'collection_method' => ['sometimes', Rule::in(['counter', 'locker', 'delivery'])],
            'qr_code_token' => ['sometimes', 'string', 'max:100'],
            'pharmacist_notes' => ['sometimes', 'string', 'max:2000'],
            'counselling_provided' => ['sometimes', 'array'],
            'counselling_provided.*' => ['string', 'max:200'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'medication_request_id.required' => 'Medication request reference is required.',
            'medication_request_id.exists' => 'Selected medication request does not exist.',
            'batch_number.required' => 'Manufacturer batch number is required for traceability (SAPC compliance).',
            'expires_at.required' => 'Medication expiry date is required.',
            'expires_at.after_or_equal' => 'Medication cannot be expired at time of dispensing.',
            'quantity_dispensed.required' => 'Quantity dispensed is required.',
            'quantity_dispensed.min' => 'Must dispense at least 1 unit.',
        ];
    }

    /**
     * Configure the validator instance.
     * Add SAPC compliance checks.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $medicationRequest = MedicationRequest::find($this->input('medication_request_id'));

            if (! $medicationRequest) {
                return;
            }

            // Check if prescription is dispensable
            if (! $medicationRequest->isDispensable()) {
                $validator->errors()->add('medication_request_id',
                    'Prescription is not active, expired, or has no repeats remaining.');

                return;
            }

            // Validate quantity doesn't exceed prescribed
            $quantityDispensed = $this->input('quantity_dispensed');
            if ($quantityDispensed > $medicationRequest->quantity) {
                $validator->errors()->add('quantity_dispensed',
                    "Cannot dispense more than prescribed quantity ({$medicationRequest->quantity}).");
            }

            // SAPC Schedule 5 & 6 additional checks
            if (in_array($medicationRequest->sapc_schedule, [5, 6])) {
                // Check for controlled substance register entry
                if (empty($this->input('controlled_substance_register_entry'))) {
                    $validator->errors()->add('controlled_substance_register_entry',
                        "SAPC Schedule {$medicationRequest->sapc_schedule} requires controlled substance register entry.");
                }

                // Check prescriber has valid HPCSA for schedule 5/6
                if (! $medicationRequest->prescriber->hasValidControlledPrescribingLicense()) {
                    $validator->errors()->add('prescriber',
                        "Prescriber not authorized for SAPC Schedule {$medicationRequest->sapc_schedule} medications.");
                }
            }

            // Check drug interactions and allergies were reviewed
            if ($medicationRequest->drug_interactions_checked === null) {
                $validator->errors()->add('drug_interactions',
                    'Drug interaction check must be completed before dispensing.');
            }

            if ($medicationRequest->allergy_alerts === null) {
                $validator->errors()->add('allergy_check',
                    'Patient allergy cross-check must be completed before dispensing.');
            }

            // Expiry validation: medication must not expire before treatment ends
            $expiresAt = Carbon::parse($this->input('expires_at'));
            $treatmentEnds = Carbon::parse($medicationRequest->prescribed_at)->addDays($medicationRequest->duration_days);

            if ($expiresAt->lt($treatmentEnds)) {
                $validator->errors()->add('expires_at',
                    'Medication expires before treatment course completes. Select a different batch.');
            }
        });
    }
}
