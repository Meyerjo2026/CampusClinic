@extends('layouts.app')

@section('title', 'Student Dashboard')

@push('styles')
<style>
    .gradient-cput { background: linear-gradient(135deg, #002B49 0%, #0072CE 100%); }
    .gradient-cput-cyan { background: linear-gradient(135deg, #0072CE 0%, #00A3E0 100%); }
</style>
@endpush

@section('content')
<div x-data="studentDashboard()">
    <!-- Welcome Hero Section -->
    <div class="gradient-cput rounded-2xl p-6 sm:p-8 mb-8 text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-r from-cput-blue/20 to-cput-cyan/20" aria-hidden="true"></div>
        <div class="absolute top-0 right-0 w-72 h-72 bg-cput-cyan/10 rounded-full blur-3xl" aria-hidden="true"></div>
        
        <div class="relative z-10">
            <h1 class="text-2xl sm:text-3xl font-bold mb-2">Welcome back, {{ auth()->user()->name }}</h1>
            <p class="text-cput-cyan/90 mb-6">Your health journey at CPUT Student Wellness Services</p>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                    <p class="text-xs text-cput-cyan/80 uppercase tracking-wide">Next Appointment</p>
                    <p class="text-lg font-semibold">{{ $nextAppointment ? $nextAppointment->scheduled_at->format('M j, g:i A') : 'None scheduled' }}</p>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                    <p class="text-xs text-cput-cyan/80 uppercase tracking-wide">Active Prescriptions</p>
                    <p class="text-lg font-semibold">{{ $activePrescriptionsCount ?? 0 }}</p>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                    <p class="text-xs text-cput-cyan/80 uppercase tracking-wide">Refills Due</p>
                    <p class="text-lg font-semibold text-{{ $refillsDueCount > 0 ? 'yellow' : 'green' }}-400">{{ $refillsDueCount ?? 0 }}</p>
                </div>
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4">
                    <p class="text-xs text-cput-cyan/80 uppercase tracking-wide">POPIA Consent</p>
                    <p class="text-lg font-semibold">
                        @if(auth()->user()->patient?->hasValidPopiaConsent())
                            <span class="text-green-300">Active</span>
                        @else
                            <span class="text-yellow-300">Required</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Appointments & Triage -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Book Appointment Card -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                        <svg class="w-5 h-5 text-cput-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Book Appointment
                    </h2>
                    <span class="px-2 py-1 text-xs font-medium bg-cput-slate text-cput-navy rounded-full">Intelligent Triage</span>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Service Type</label>
                            <select x-model="bookingForm.service_type" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent transition">
                                <option value="">Select service...</option>
                                <option value="cnp">Clinical Nurse Practitioner (Free)</option>
                                <option value="gp">Medical Doctor (Medical Aid)</option>
                                <option value="psychologist">Psychologist (Stepped Care)</option>
                                <option value="pharmacy_pickup">Pharmacy Collection</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Preferred Date</label>
                            <input type="date" x-model="bookingForm.scheduled_at" :min="today" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent transition">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Chief Complaint / Reason</label>
                        <textarea x-model="bookingForm.triage_screening.chief_complaint" rows="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent transition" placeholder="Describe your main concern..."></textarea>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Symptoms (Select all that apply)</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @foreach([
                                'fever' => 'Fever/Chills',
                                'cough' => 'Cough',
                                'shortness_of_breath' => 'Shortness of Breath',
                                'chest_pain' => 'Chest Pain',
                                'headache' => 'Headache',
                                'nausea' => 'Nausea/Vomiting',
                                'anxiety' => 'Anxiety/Panic',
                                'depression' => 'Low Mood/Depression',
                                'suicidal_thoughts' => 'Suicidal Thoughts',
                                'self_harm' => 'Self-harm Urges',
                                'trauma' => 'Recent Trauma',
                                'other' => 'Other'
                            ] as $key => $label)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" :value="$key" x-model="bookingForm.triage_screening.symptoms" class="w-4 h-4 text-cput-blue border-slate-300 rounded focus:ring-cput-blue">
                                    <span class="text-sm text-slate-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Severity</label>
                        <select x-model="bookingForm.triage_screening.severity" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent transition">
                            <option value="mild">Mild - Manageable symptoms</option>
                            <option value="moderate">Moderate - Interfering with daily activities</option>
                            <option value="severe">Severe - Significantly impacting function</option>
                            <option value="critical">Critical - Emergency situation</option>
                        </select>
                    </div>
                    <div class="mt-4 flex gap-3">
                        <button @click="openBookingModal = true" :disabled="submitting" class="flex-1 bg-cput-blue text-white py-3 px-6 rounded-lg font-semibold hover:bg-cput-blue-light transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!submitting">Continue to Triage Screening</span>
                            <span x-show="submitting" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                Processing...
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Upcoming Appointments -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-cput-navy">Upcoming Appointments</h2>
                </div>
                <div class="divide-y divide-cput-slate-dark">
                    @forelse($upcomingAppointments as $appt)
                        <div class="px-6 py-4 flex items-center justify-between hover:bg-cput-slate/50 transition">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-cput-blue/10 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-cput-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <div>
                                    <p class="font-medium text-slate-900">{{ ucfirst($appt->service_type) }}</p>
                                    <p class="text-sm text-slate-500">{{ $appt->scheduled_at->format('l, M j \a\t g:i A') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full {{ $appt->status === 'booked' ? 'bg-blue-100 text-blue-800' : ($appt->status === 'arrived' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-800') }}">
                                    {{ ucfirst(str_replace('_', ' ', $appt->status)) }}
                                </span>
                                <a href="{{ route('student.appointments.show', $appt) }}" class="text-sm text-cput-blue hover:text-cput-blue-dark font-medium">Details</a>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-slate-500">
                            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <p>No upcoming appointments</p>
                            <button @click="openBookingModal = true" class="mt-2 text-cput-blue hover:underline text-sm">Book your first appointment</button>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Column: Medications & Quick Actions -->
        <div class="space-y-6">
            <!-- Chronic Medications Card -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                        <svg class="w-5 h-5 text-cput-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Chronic Medications
                    </h2>
                </div>
                <div class="p-4 divide-y divide-cput-slate-dark">
                    @forelse($chronicMeds as $med)
                        <div class="py-3 flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-slate-900 truncate">{{ $med->drug_name }}</p>
                                <p class="text-sm text-slate-500">{{ $med->dosage }} • {{ $med->repeats_remaining }} refills left</p>
                                @if($med->isDueForRefill(5))
                                    <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold bg-yellow-100 text-yellow-800 rounded">Refill Soon</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 ml-3">
                                @if($med->repeats_remaining > 0)
                                    <a href="{{ route('student.medications.refill', $med) }}" class="px-3 py-1.5 text-xs font-medium bg-cput-cyan text-white rounded hover:bg-cput-cyan-dark transition">Request Refill</a>
                                @else
                                    <span class="px-3 py-1.5 text-xs font-medium bg-slate-100 text-slate-500 rounded">No Refills</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-500">
                            <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <p class="text-sm">No chronic medications</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark">
                    <h2 class="text-lg font-semibold text-cput-navy">Quick Actions</h2>
                </div>
                <div class="p-4 grid grid-cols-2 gap-3">
                    <a href="{{ route('student.appointments') }}" class="p-4 text-center bg-cput-slate rounded-xl hover:bg-cput-slate-dark transition-colors group">
                        <div class="w-10 h-10 mx-auto mb-2 bg-cput-blue/10 rounded-lg flex items-center justify-center group-hover:bg-cput-blue/20 transition">
                            <svg class="w-5 h-5 text-cput-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-700">View All Appointments</p>
                    </a>
                    <a href="{{ route('student.medications') }}" class="p-4 text-center bg-cput-slate rounded-xl hover:bg-cput-slate-dark transition-colors group">
                        <div class="w-10 h-10 mx-auto mb-2 bg-cput-cyan/10 rounded-lg flex items-center justify-center group-hover:bg-cput-cyan/20 transition">
                            <svg class="w-5 h-5 text-cput-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-700">My Medications</p>
                    </a>
                    <a href="{{ route('student.health-records') }}" class="p-4 text-center bg-cput-slate rounded-xl hover:bg-cput-slate-dark transition-colors group">
                        <div class="w-10 h-10 mx-auto mb-2 bg-emerald-100 rounded-lg flex items-center justify-center group-hover:bg-emerald-200 transition">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-700">Health Records</p>
                    </a>
                    <a href="tel:0800242526" class="p-4 text-center bg-cput-emergency/10 rounded-xl hover:bg-cput-emergency/20 transition-colors group">
                        <div class="w-10 h-10 mx-auto mb-2 bg-cput-emergency/10 rounded-lg flex items-center justify-center group-hover:bg-cput-emergency/20 transition">
                            <svg class="w-5 h-5 text-cput-emergency" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        </div>
                        <p class="text-sm font-medium text-cput-emergency">24/7 Crisis Line</p>
                    </a>
                </div>
            </div>

            <!-- POPIA Consent Banner -->
            @unless(auth()->user()->patient?->hasValidPopiaConsent())
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.765 1.36-1.234 2.66-2.722 2.66H5.4c-1.488 0-3.487-1.3-2.722-2.66l5.58-9.92zM10 13a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0v-2.25A.75.75 0 0110 13zM10 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 7z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-yellow-800">POPIA Consent Required</h3>
                        <p class="text-sm text-yellow-700 mt-1">To access health services, please provide consent for processing your special personal information per POPIA Section 26 & 32.</p>
                        <a href="{{ route('patient.consent') }}" class="mt-3 inline-block px-4 py-2 text-sm font-semibold bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition">Give Consent Now</a>
                    </div>
                </div>
            </div>
            @endunless
        </div>
    </div>

    <!-- Triage Booking Modal -->
    <div x-show="openBookingModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="openBookingModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="w-full max-w-2xl bg-white rounded-2xl shadow-xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-cput-slate-dark">
                    <h3 id="modal-title" class="text-lg font-semibold text-cput-navy">Triage Screening Questionnaire</h3>
                    <button @click="openBookingModal = false" class="text-slate-400 hover:text-slate-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <div class="p-6 max-h-[70vh] overflow-y-auto">
                    <form @submit.prevent="submitTriage">
                        <!-- Progress Indicator -->
                        <div class="mb-6">
                            <div class="flex gap-2 mb-2">
                                <template x-for="(step, index) in triageSteps" :key="index">
                                    <div class="flex-1 flex items-center gap-1">
                                        <div :class="['flex-1 h-1 rounded', index < currentStep ? 'bg-cput-blue' : (index === currentStep ? 'bg-cput-cyan' : 'bg-slate-200')]"></div>
                                    </div>
                                </template>
                            </div>
                            <p class="text-sm text-slate-500 text-center" x-text="triageSteps[currentStep]"></p>
                        </div>

                        <!-- Step 1: Mental Health Screening -->
                        <div x-show="currentStep === 0" x-transition>
                            <h4 class="text-sm font-semibold text-cput-navy mb-4">Mental Health Screening (PHQ-2 / GAD-2)</h4>
                            <div class="space-y-4">
                                @foreach([
                                    'phq2_1' => 'Little interest or pleasure in doing things',
                                    'phq2_2' => 'Feeling down, depressed, or hopeless',
                                    'gad2_1' => 'Feeling nervous, anxious, or on edge',
                                    'gad2_2' => 'Not being able to stop or control worrying',
                                ] as $key => $question)
                                    <div>
                                        <p class="text-sm text-slate-700 mb-2">{{ $question }}</p>
                                        <div class="flex gap-4">
                                            @foreach(['Not at all' => 0, 'Several days' => 1, 'More than half the days' => 2, 'Nearly every day' => 3] as $label => $value)
                                                <label class="flex-1 cursor-pointer">
                                                    <input type="radio" :name="$key" :value="$value" x-model="bookingForm.triage_screening[$key]" class="sr-only peer">
                                                    <div class="px-4 py-2 text-center text-sm border-2 rounded-lg peer-checked:border-cput-blue peer-checked:bg-cput-blue/10 peer-checked:text-cput-blue border-slate-200 hover:border-cput-blue/50 transition">{{ $label }}</div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Step 2: Physical Symptoms -->
                        <div x-show="currentStep === 1" x-transition>
                            <h4 class="text-sm font-semibold text-cput-navy mb-4">Physical Emergency Screening</h4>
                            <div class="space-y-3">
                                @foreach([
                                    'chest_pain' => 'Chest pain or discomfort',
                                    'shortness_of_breath' => 'Difficulty breathing / shortness of breath',
                                    'severe_pain' => 'Severe pain (8-10/10)',
                                    'unconsciousness' => 'Fainting, dizziness, or loss of consciousness',
                                    'allergic_reaction' => 'Signs of severe allergic reaction (swelling, hives)',
                                    'bleeding' => 'Uncontrolled bleeding',
                                ] as $key => $question)
                                    <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-cput-slate/50 transition">
                                        <input type="checkbox" :value="true" x-model="bookingForm.triage_screening[$key]" class="w-4 h-4 text-cput-blue border-slate-300 rounded focus:ring-cput-blue">
                                        <span class="text-sm text-slate-700">{{ $question }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Step 3: Emergency Contacts -->
                        <div x-show="currentStep === 2" x-transition>
                            <h4 class="text-sm font-semibold text-cput-navy mb-4">Emergency Contact Verification</h4>
                            <p class="text-sm text-slate-500 mb-4">Please confirm your emergency contact is up to date.</p>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Contact Name</label>
                                    <input type="text" x-model="bookingForm.triage_screening.emergency_contact.name" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Relationship</label>
                                    <input type="text" x-model="bookingForm.triage_screening.emergency_contact.relationship" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                                    <input type="tel" x-model="bookingForm.triage_screening.emergency_contact.phone" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Navigation -->
                        <div class="mt-6 flex justify-between">
                            <button type="button" @click="currentStep--" x-show="currentStep > 0" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition">Back</button>
                            <template x-if="currentStep < triageSteps.length - 1">
                                <button type="button" @click="currentStep++" class="px-6 py-2 text-sm font-semibold bg-cput-blue text-white rounded-lg hover:bg-cput-blue-light transition">Continue</button>
                            </template>
                            <template x-if="currentStep === triageSteps.length - 1">
                                <button type="submit" :disabled="submitting" class="px-6 py-2 text-sm font-semibold bg-cput-blue text-white rounded-lg hover:bg-cput-blue-light transition disabled:opacity-50">
                                    <span x-show="!submitting">Submit & Book Appointment</span>
                                    <span x-show="submitting" class="flex items-center gap-2"><svg class="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>Booking...</span>
                                </button>
                            </template>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div x-show="openBookingModal" @click="openBookingModal = false" class="fixed inset-0 bg-black/50" aria-hidden="true"></div>
    </div>
</div>

@push('scripts')
<script>
    function studentDashboard() {
        return {
            openBookingModal: false,
            submitting: false,
            currentStep: 0,
            triageSteps: ['Mental Health', 'Physical Emergency', 'Emergency Contact'],
            today: new Date().toISOString().split('T')[0],
            bookingForm: {
                service_type: '',
                scheduled_at: '',
                triage_screening: {
                    chief_complaint: '',
                    symptoms: [],
                    severity: 'mild',
                    phq2_1: '',
                    phq2_2: '',
                    gad2_1: '',
                    gad2_2: '',
                    chest_pain: false,
                    shortness_of_breath: false,
                    severe_pain: false,
                    unconsciousness: false,
                    allergic_reaction: false,
                    bleeding: false,
                    emergency_contact: {
                        name: '',
                        relationship: '',
                        phone: ''
                    }
                }
            },

            submitTriage() {
                this.submitting = true;
                
                // Calculate PHQ-2 and GAD-2 scores
                const phq2 = (parseInt(this.bookingForm.triage_screening.phq2_1) || 0) + (parseInt(this.bookingForm.triage_screening.phq2_2) || 0);
                const gad2 = (parseInt(this.bookingForm.triage_screening.gad2_1) || 0) + (parseInt(this.bookingForm.triage_screening.gad2_2) || 0);
                
                // Check for crisis flags
                const crisisFlags = {
                    suicidal_ideation: phq2 >= 3,
                    severe_anxiety: gad2 >= 3,
                    chest_pain: this.bookingForm.triage_screening.chest_pain,
                    shortness_of_breath: this.bookingForm.triage_screening.shortness_of_breath,
                    severe_pain: this.bookingForm.triage_screening.severe_pain,
                };
                
                // Add crisis flags to form
                this.bookingForm.triage_screening.crisis_flags = crisisFlags;
                this.bookingForm.triage_screening.phq2_score = phq2;
                this.bookingForm.triage_screening.gad2_score = gad2;

                // Submit to API
                fetch('/api/v1/appointments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.bookingForm)
                })
                .then(response => response.json())
                .then(data => {
                    this.submitting = false;
                    if (data.success) {
                        this.openBookingModal = false;
                        this.resetForm();
                        window.location.reload(); // Refresh to show new appointment
                    } else if (data.crisis_detected) {
                        // Crisis detected - show emergency modal
                        this.showCrisisModal(data);
                    } else {
                        alert(data.message || 'Booking failed. Please try again.');
                    }
                })
                .catch(err => {
                    this.submitting = false;
                    console.error(err);
                    alert('An error occurred. Please try again.');
                });
            },

            showCrisisModal(data) {
                // Create emergency modal
                const modal = document.createElement('div');
                modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50';
                modal.innerHTML = `
                    <div class="bg-white rounded-2xl max-w-md w-full p-6 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 bg-cput-emergency/10 rounded-full flex items-center justify-center">
                            <svg class="w-8 h-8 text-cput-emergency" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.765 1.36-1.234 2.66-2.722 2.66H5.4c-1.488 0-3.487-1.3-2.722-2.66l5.58-9.92zM10 13a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0v-2.25A.75.75 0 0110 13zM10 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 7z" clip-rule="evenodd"/></svg>
                        </div>
                        <h3 class="text-xl font-bold text-cput-emergency mb-2">CRISIS DETECTED</h3>
                        <p class="text-slate-600 mb-4">Your responses indicate you may need immediate support. Booking has been halted for your safety.</p>
                        <div class="space-y-2 mb-4 text-sm text-left bg-cput-emergency/5 rounded-lg p-4">
                            ${data.emergency_hotlines ? Object.entries(data.emergency_hotlines).map(([k, v]) => `<a href="tel:${v.replace(/\D/g, '')}" class="block text-cput-emergency font-medium hover:underline">${k.replace(/_/g, ' ')}: ${v}</a>`).join('') : ''}
                        </div>
                        <button onclick="this.closest('.fixed').remove()" class="w-full px-4 py-2 bg-cput-emergency text-white rounded-lg font-semibold hover:bg-cput-emergency-dark transition">I Understand - Close</button>
                    </div>
                `;
                document.body.appendChild(modal);
            },

            resetForm() {
                this.bookingForm = {
                    service_type: '',
                    scheduled_at: '',
                    triage_screening: {
                        chief_complaint: '',
                        symptoms: [],
                        severity: 'mild',
                        phq2_1: '',
                        phq2_2: '',
                        gad2_1: '',
                        gad2_2: '',
                        chest_pain: false,
                        shortness_of_breath: false,
                        severe_pain: false,
                        unconsciousness: false,
                        allergic_reaction: false,
                        bleeding: false,
                        emergency_contact: { name: '', relationship: '', phone: '' }
                    }
                };
                this.currentStep = 0;
            }
        }
    }
</script>
@endpush