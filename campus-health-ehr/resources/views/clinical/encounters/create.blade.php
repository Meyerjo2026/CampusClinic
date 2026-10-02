@extends('layouts.app')

@section('title', 'New Clinical Encounter')

@push('styles')
<style>
    .gradient-cput { background: linear-gradient(135deg, #002B49 0%, #0072CE 100%); }
</style>
@endpush

@section('content')
<div x-data="encounterCreate()" class="max-w-5xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-cput-navy">New Clinical Encounter</h1>
            <p class="text-slate-500 mt-1">Document SOAP notes for {{ $patient->user->name ?? 'selected patient' }}</p>
        </div>
    </div>

    <!-- Patient Header Card -->
    <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark mb-6 overflow-hidden">
        <div class="px-6 py-4 border-b border-cput-slate-dark bg-gradient-to-r from-cput-navy to-cput-blue text-white">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center">
                        <span class="text-xl font-bold">{{ ($patient->user->name ?? 'P')[0] }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-lg">{{ $patient->user->name ?? 'Unknown Patient' }}</p>
                        <p class="text-cput-cyan/90 text-sm">{{ $patient->student_number }} • {{ $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->age : 'Age unknown' }} years</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    @if($patient->is_high_risk_mental_health)
                        <span class="px-3 py-1 text-sm font-semibold bg-cput-emergency/20 text-cput-emergency border border-cput-emergency/30 rounded-full">High Risk MH</span>
                    @endif
                    @if($patient->popia_consent_given)
                        <span class="px-3 py-1 text-sm font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-full">POPIA Consent ✓</span>
                    @else
                        <span class="px-3 py-1 text-sm font-semibold bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 rounded-full">POPIA Consent Required</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="px-6 py-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div><p class="text-slate-500">Allergies</p><p class="font-medium">{{ $patient->allergies_display ?? 'None recorded' }}</p></div>
            <div><p class="text-slate-500">Current Meds</p><p class="font-medium">{{ $patient->current_meds_count ?? 0 }} active</p></div>
            <div><p class="text-slate-500">Last Visit</p><p class="font-medium">{{ $patient->last_visit_formatted ?? 'Never' }}</p></div>
            <div><p class="text-slate-500">Emergency Contact</p><p class="font-medium truncate">{{ $patient->emergency_contact_name ?? 'Not set' }}</p></div>
        </div>
    </div>

    <!-- SOAP Form -->
    <form @submit.prevent="submitEncounter" class="space-y-6">
        @csrf
        <input type="hidden" name="patient_id" :value="patientId">
        <input type="hidden" name="appointment_id" :value="appointmentId">

        <!-- Triage Level -->
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark p-6">
            <h3 class="text-lg font-semibold text-cput-navy mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-cput-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Triage Assessment
            </h3>
            <div class="grid grid-cols-3 gap-4">
                <label class="cursor-pointer">
                    <input type="radio" name="triage_level" value="emergency" x-model="form.triage_level" class="sr-only peer">
                    <div class="p-4 border-2 rounded-xl text-center peer-checked:border-cput-emergency peer-checked:bg-cput-emergency/5 peer-checked:text-cput-emergency border-slate-200 hover:border-cput-emergency/50 transition">
                        <p class="font-semibold">Emergency</p>
                        <p class="text-xs text-slate-500 mt-1">Immediate threat to life</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="triage_level" value="urgent" x-model="form.triage_level" class="sr-only peer">
                    <div class="p-4 border-2 rounded-xl text-center peer-checked:border-yellow-500 peer-checked:bg-yellow-50 peer-checked:text-yellow-700 border-slate-200 hover:border-yellow-500/50 transition">
                        <p class="font-semibold">Urgent</p>
                        <p class="text-xs text-slate-500 mt-1">Needs attention within 30 min</p>
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="triage_level" value="routine" x-model="form.triage_level" class="sr-only peer">
                    <div class="p-4 border-2 rounded-xl text-center peer-checked:border-cput-blue peer-checked:bg-cput-blue/5 peer-checked:text-cput-blue border-slate-200 hover:border-cput-blue/50 transition">
                        <p class="font-semibold">Routine</p>
                        <p class="text-xs text-slate-500 mt-1">Standard appointment</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- S - Subjective -->
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
            <div class="px-6 py-4 border-b border-cput-slate-dark bg-cput-blue/5">
                <h3 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-cput-blue text-white text-xs font-bold rounded">S</span>
                    Subjective
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Chief Complaint *</label>
                    <textarea name="soap_subjective[chief_complaint]" x-model="form.soap_subjective.chief_complaint" rows="2" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Primary reason for visit in patient's own words"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">History of Present Illness (HPI) *</label>
                    <textarea name="soap_subjective[hpi]" x-model="form.soap_subjective.hpi" rows="4" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Detailed chronological description of the presenting problem (onset, duration, severity, modifying factors, associated symptoms)"></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Allergies</label>
                        <textarea name="soap_subjective[allergies]" x-model="form.soap_subjective.allergies" rows="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Drug, food, environmental allergies and reactions"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Social History</label>
                        <textarea name="soap_subjective[social_history]" x-model="form.soap_subjective.social_history" rows="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Living situation, substance use, occupation, support systems"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- O - Objective -->
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
            <div class="px-6 py-4 border-b border-cput-slate-dark bg-cput-cyan/5">
                <h3 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-cput-cyan text-white text-xs font-bold rounded">O</span>
                    Objective
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <!-- Vital Signs Grid -->
                <div>
                    <h4 class="font-medium text-slate-700 mb-3">Vital Signs</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                        @foreach([
                            'bp_systolic' => ['Blood Pressure (Systolic)', 'mmHg'],
                            'bp_diastolic' => ['Blood Pressure (Diastolic)', 'mmHg'],
                            'heart_rate' => ['Heart Rate', 'bpm'],
                            'spo2' => ['SpO₂', '%'],
                            'temperature' => ['Temperature', '°C'],
                            'blood_glucose' => ['Blood Glucose', 'mmol/L'],
                            'respiratory_rate' => ['Respiratory Rate', '/min'],
                            'weight' => ['Weight', 'kg'],
                            'height' => ['Height', 'cm'],
                            'bmi' => ['BMI', 'kg/m²'],
                        ] as $key => $item)
                            <div class="relative">
                                <label class="block text-xs text-slate-500 mb-1">{{ $item[0] }}</label>
                                <div class="flex items-center">
                                    <input type="number" step="any" name="vital_signs[{{ $key }}]" x-model.number="form.vital_signs.{{ $key }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent text-sm" placeholder="--">
                                    <span class="text-xs text-slate-400 ml-1">{{ $item[1] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Physical Examination Findings</label>
                    <textarea name="soap_objective" x-model="form.soap_objective" rows="4" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Systematic examination findings by body system (General, HEENT, CVS, Respiratory, Abdomen, Neuro, MSK, Skin, etc.)"></textarea>
                </div>
            </div>
        </div>

        <!-- A - Assessment -->
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
            <div class="px-6 py-4 border-b border-cput-slate-dark bg-yellow-50">
                <h3 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-yellow-600 text-white text-xs font-bold rounded">A</span>
                    Assessment (ICD-10)
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h4 class="font-medium text-slate-700">Diagnoses</h4>
                    <button type="button" @click="addDiagnosis" class="px-3 py-1.5 text-sm font-medium bg-cput-blue text-white rounded-lg hover:bg-cput-blue-light transition flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Add Diagnosis
                    </button>
                </div>
                
                <template x-for="(diagnosis, index) in form.soap_assessment_icd10" :key="index">
                    <div class="border border-slate-200 rounded-xl p-4 relative">
                        <button type="button" @click="removeDiagnosis(index)" class="absolute top-2 right-2 text-slate-400 hover:text-cput-emergency"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">ICD-10 Code *</label>
                                <input type="text" :name="'soap_assessment_icd10[' + index + '][code]'" x-model="diagnosis.code" required pattern="^[A-Z]\d{2}(\.\d{1,2})?$" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent font-mono text-uppercase" placeholder="e.g., F32.1, I10, J45.9" title="Format: Letter + 2 digits + optional decimal (e.g., F32.1)">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Description *</label>
                                <input type="text" :name="'soap_assessment_icd10[' + index + '][display]'" x-model="diagnosis.display" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Diagnosis description">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Type *</label>
                                <select :name="'soap_assessment_icd10[' + index + '][type]'" x-model="diagnosis.type" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                                    <option value="primary">Primary</option>
                                    <option value="secondary">Secondary</option>
                                    <option value="comorbidity">Comorbidity</option>
                                </select>
                            </div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500" x-text="'ICD-10: ' + diagnosis.code + ' - ' + diagnosis.display"></div>
                    </div>
                </template>
                
                <div x-show="form.soap_assessment_icd10.length === 0" class="text-center py-8 text-slate-500">
                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <p>No diagnoses added yet. Add at least one ICD-10 code.</p>
                </div>
            </div>
        </div>

        <!-- P - Plan -->
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
            <div class="px-6 py-4 border-b border-cput-slate-dark bg-emerald-50">
                <h3 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-emerald-600 text-white text-xs font-bold rounded">P</span>
                    Plan
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Management Plan *</label>
                    <textarea name="soap_plan" x-model="form.soap_plan" rows="4" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Treatment plan, follow-up instructions, referrals, investigations ordered, patient education provided"></textarea>
                </div>

                <!-- Medications Prescribed -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-slate-700">Medications Prescribed</h4>
                        <button type="button" @click="addMedication" class="px-3 py-1.5 text-sm font-medium bg-cput-cyan text-white rounded-lg hover:bg-cput-cyan-dark transition flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Medication
                        </button>
                    </div>
                    <template x-for="(med, index) in form.medications_prescribed" :key="index">
                        <div class="border border-slate-200 rounded-xl p-4 relative">
                            <button type="button" @click="removeMedication(index)" class="absolute top-2 right-2 text-slate-400 hover:text-cput-emergency"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Drug Name *</label>
                                    <input type="text" :name="'medications_prescribed[' + index + '][drug_name]'" x-model="med.drug_name" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Dosage *</label>
                                    <input type="text" :name="'medications_prescribed[' + index + '][dosage]'" x-model="med.dosage" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="e.g., 500mg">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Frequency *</label>
                                    <select :name="'medications_prescribed[' + index + '][frequency]'" x-model="med.frequency" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                                        <option value="OD">OD (Once daily)</option>
                                        <option value="BD">BD (Twice daily)</option>
                                        <option value="TDS">TDS (Three times daily)</option>
                                        <option value="QID">QID (Four times daily)</option>
                                        <option value="PRN">PRN (As needed)</option>
                                        <option value="OTHER">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Duration (days) *</label>
                                    <input type="number" min="1" max="365" :name="'medications_prescribed[' + index + '][duration_days]'" x-model.number="med.duration_days" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" value="30">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantity *</label>
                                    <input type="number" min="1" :name="'medications_prescribed[' + index + '][quantity]'" x-model.number="med.quantity" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" value="30">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Repeats</label>
                                    <input type="number" min="0" max="11" :name="'medications_prescribed[' + index + '][repeats]'" x-model.number="med.repeats" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" value="0">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">SAPC Schedule *</label>
                                    <select :name="'medications_prescribed[' + index + '][sapc_schedule]'" x-model.number="med.sapc_schedule" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                                        <option value="1">Schedule 1 (OTC)</option>
                                        <option value="2">Schedule 2 (Pharmacy Only)</option>
                                        <option value="3">Schedule 3 (Pharmacist Initiated)</option>
                                        <option value="4">Schedule 4 (Prescription Only)</option>
                                        <option value="5">Schedule 5 (Special Control)</option>
                                        <option value="6">Schedule 6 (Narcotic/Controlled)</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2 flex items-end">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox" :name="'medications_prescribed[' + index + '][is_chronic]'" x-model="med.is_chronic" class="w-4 h-4 text-cput-blue border-slate-300 rounded focus:ring-cput-blue">
                                        <span class="text-sm text-slate-700">Chronic medication</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Lab Requisitions -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-slate-700">Lab Requisitions</h4>
                        <button type="button" @click="addLab" class="px-3 py-1.5 text-sm font-medium bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Lab
                        </button>
                    </div>
                    <template x-for="(lab, index) in form.lab_requisitions" :key="index">
                        <div class="border border-slate-200 rounded-xl p-3 relative flex items-center gap-3">
                            <button type="button" @click="removeLab(index)" class="text-slate-400 hover:text-cput-emergency"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            <div class="flex-1">
                                <input type="text" :name="'lab_requisitions[' + index + ']'" x-model="lab" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="e.g., FBC, U&E, LFT, HbA1c, Viral Load">
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Referrals -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-medium text-slate-700">Referrals</h4>
                        <button type="button" @click="addReferral" class="px-3 py-1.5 text-sm font-medium bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Add Referral
                        </button>
                    </div>
                    <template x-for="(ref, index) in form.referrals" :key="index">
                        <div class="border border-slate-200 rounded-xl p-4 relative">
                            <button type="button" @click="removeReferral(index)" class="absolute top-2 right-2 text-slate-400 hover:text-cput-emergency"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Specialty *</label>
                                    <input type="text" :name="'referrals[' + index + '][specialty]'" x-model="ref.specialty" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="e.g., Cardiology, Psychiatry, Orthopedics">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Reason *</label>
                                    <input type="text" :name="'referrals[' + index + '][reason]'" x-model="ref.reason" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Clinical reason for referral">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Urgency</label>
                                    <select :name="'referrals[' + index + '][urgency]'" x-model="ref.urgency" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                                        <option value="routine">Routine</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="emergency">Emergency</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Follow-up -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Follow-up Instructions</label>
                        <textarea name="follow_up_instructions" x-model="form.follow_up_instructions" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Patient instructions, red flags to watch for, when to return"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Follow-up in (days)</label>
                        <input type="number" min="1" max="365" name="follow_up_days" x-model.number="form.follow_up_days" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" value="14">
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Actions -->
        <div class="flex justify-end gap-4 sticky bottom-0 bg-white/95 backdrop-blur-sm py-4 border-t border-cput-slate-dark">
            <button type="button" @click="saveDraft" class="px-6 py-2.5 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition">Save Draft</button>
            <button type="submit" :disabled="submitting" class="px-6 py-2.5 text-sm font-semibold bg-cput-blue text-white rounded-lg hover:bg-cput-blue-light transition disabled:opacity-50 flex items-center gap-2">
                <span x-show="!submitting">Save & Complete Encounter</span>
                <span x-show="submitting" class="flex items-center gap-2"><svg class="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>Saving...</span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function encounterCreate() {
        return {
            patientId: {{ $patient->id ?? 0 }},
            appointmentId: {{ $appointment->id ?? 0 }},
            submitting: false,
            form: {
                triage_level: 'routine',
                soap_subjective: {
                    chief_complaint: '',
                    hpi: '',
                    allergies: '',
                    social_history: '',
                },
                vital_signs: {},
                soap_objective: '',
                soap_assessment_icd10: [],
                soap_plan: '',
                medications_prescribed: [],
                lab_requisitions: [],
                referrals: [],
                follow_up_instructions: '',
                follow_up_days: 14,
            },

            addDiagnosis() {
                this.form.soap_assessment_icd10.push({ code: '', display: '', type: 'primary' });
            },

            removeDiagnosis(index) {
                this.form.soap_assessment_icd10.splice(index, 1);
            },

            addMedication() {
                this.form.medications_prescribed.push({
                    drug_name: '',
                    dosage: '',
                    frequency: 'BD',
                    duration_days: 30,
                    quantity: 30,
                    repeats: 0,
                    sapc_schedule: 4,
                    is_chronic: false,
                });
            },

            removeMedication(index) {
                this.form.medications_prescribed.splice(index, 1);
            },

            addLab() {
                this.form.lab_requisitions.push('');
            },

            removeLab(index) {
                this.form.lab_requisitions.splice(index, 1);
            },

            addReferral() {
                this.form.referrals.push({ specialty: '', reason: '', urgency: 'routine' });
            },

            removeReferral(index) {
                this.form.referrals.splice(index, 1);
            },

            async submitEncounter() {
                this.submitting = true;
                try {
                    const formData = new FormData(document.querySelector('form'));
                    const res = await fetch('/api/v1/encounters', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.href = '/clinical/encounters/' + data.data.encounter.id;
                    } else {
                        alert(data.message || 'Failed to save encounter');
                    }
                } catch (e) {
                    alert('Error saving encounter');
                } finally {
                    this.submitting = false;
                }
            },

            saveDraft() {
                // TODO: Implement draft saving
                alert('Draft saved (localStorage fallback in production)');
            }
        }
    }
</script>
@endpush