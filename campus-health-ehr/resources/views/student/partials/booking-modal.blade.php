<!-- Booking/Triage Modal - Reusable Component -->
<div x-show="openBookingModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="booking-modal-title" x-ref="bookingModal">
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="openBookingModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="w-full max-w-2xl bg-white rounded-2xl shadow-xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-cput-slate-dark">
                <h3 id="booking-modal-title" class="text-lg font-semibold text-cput-navy">Triage Screening Questionnaire</h3>
                <button @click="closeBookingModal" class="text-slate-400 hover:text-slate-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 max-h-[75vh] overflow-y-auto">
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

                    <!-- Pre-filled from parent if available -->
                    <input type="hidden" name="service_type" x-model="bookingForm.service_type">
                    <input type="hidden" name="scheduled_at" x-model="bookingForm.scheduled_at">

                    <!-- Step 1: Mental Health Screening (PHQ-2 / GAD-2) -->
                    <div x-show="currentStep === 0" x-transition>
                        <h4 class="text-sm font-semibold text-cput-navy mb-4">Mental Health Screening <span class="text-cput-blue text-xs font-normal">(PHQ-2 / GAD-2)</span></h4>
                        <p class="text-sm text-slate-500 mb-4">Over the last 2 weeks, how often have you been bothered by the following problems?</p>
                        <div class="space-y-5">
                            @foreach([
                                'phq2_1' => ['Little interest or pleasure in doing things', 'phq'],
                                'phq2_2' => ['Feeling down, depressed, or hopeless', 'phq'],
                                'gad2_1' => ['Feeling nervous, anxious, or on edge', 'gad'],
                                'gad2_2' => ['Not being able to stop or control worrying', 'gad'],
                            ] as $key => $item)
                                <div>
                                    <p class="text-sm text-slate-700 mb-2">{{ $item[0] }}</p>
                                    <div class="flex gap-3" role="radiogroup" :aria-label="$item[0]">
                                        @foreach(['Not at all' => 0, 'Several days' => 1, 'More than half the days' => 2, 'Nearly every day' => 3] as $label => $value)
                                            <label class="flex-1 cursor-pointer">
                                                <input type="radio" :name="$key" :value="$value" x-model="bookingForm.triage_screening[$key]" class="sr-only peer" required>
                                                <div class="px-4 py-2.5 text-center text-sm border-2 rounded-lg peer-checked:border-cput-blue peer-checked:bg-cput-blue/10 peer-checked:text-cput-blue border-slate-200 hover:border-cput-blue/50 transition">{{ $label }}</div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 p-3 bg-slate-50 rounded-lg">
                            <p class="text-xs text-slate-600"><strong>Scoring:</strong> PHQ-2 ≥ 3 or GAD-2 ≥ 3 indicates need for further assessment. You'll be connected with appropriate support.</p>
                        </div>
                    </div>

                    <!-- Step 2: Physical Emergency Screening -->
                    <div x-show="currentStep === 1" x-transition>
                        <h4 class="text-sm font-semibold text-cput-navy mb-4">Physical Emergency Screening</h4>
                        <p class="text-sm text-slate-500 mb-4">Are you experiencing any of the following RIGHT NOW?</p>
                        <div class="space-y-2">
                            @foreach([
                                'chest_pain' => 'Chest pain, pressure, or discomfort',
                                'shortness_of_breath' => 'Difficulty breathing / shortness of breath',
                                'severe_pain' => 'Severe pain (8-10/10)',
                                'unconsciousness' => 'Fainting, dizziness, or near loss of consciousness',
                                'allergic_reaction' => 'Signs of severe allergic reaction (swelling, hives, throat tightness)',
                                'bleeding' => 'Uncontrolled bleeding',
                                'head_injury' => 'Recent head injury with confusion/vomiting',
                                'seizure' => 'Active seizure or post-seizure confusion',
                            ] as $key => $question)
                                <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-cput-emergency/5 transition">
                                    <input type="checkbox" :value="true" x-model="bookingForm.triage_screening[$key]" class="w-4 h-4 text-cput-emergency border-slate-300 rounded focus:ring-cput-emergency">
                                    <span class="text-sm text-slate-700">{{ $question }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="mt-4 p-3 bg-cput-emergency/5 border border-cput-emergency/20 rounded-lg">
                            <p class="text-sm text-cput-emergency"><strong>⚠ If YES to any above:</strong> This may be a medical emergency. Consider calling 10177 (Ambulance) or going directly to Groote Schuur Hospital Emergency Centre.</p>
                        </div>
                    </div>

                    <!-- Step 3: Emergency Contact Verification -->
                    <div x-show="currentStep === 2" x-transition>
                        <h4 class="text-sm font-semibold text-cput-navy mb-4">Emergency Contact Verification</h4>
                        <p class="text-sm text-slate-500 mb-4">Please confirm your emergency contact is up to date (required for crisis situations).</p>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Contact Name</label>
                                <input type="text" x-model="bookingForm.triage_screening.emergency_contact.name" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Relationship</label>
                                <input type="text" x-model="bookingForm.triage_screening.emergency_contact.relationship" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="e.g., Parent, Guardian, Partner" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                                <input type="tel" x-model="bookingForm.triage_screening.emergency_contact.phone" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="0XX XXX XXXX" required>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <div class="mt-6 flex justify-between">
                        <button type="button" @click="prevStep" x-show="currentStep > 0" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition">Back</button>
                        <template x-if="currentStep < triageSteps.length - 1">
                            <button type="button" @click="nextStep" class="px-6 py-2 text-sm font-semibold bg-cput-blue text-white rounded-lg hover:bg-cput-blue-light transition">Continue</button>
                        </template>
                        <template x-if="currentStep === triageSteps.length - 1">
                            <button type="submit" :disabled="submitting" class="px-6 py-2 text-sm font-semibold bg-cput-blue text-white rounded-lg hover:bg-cput-blue-light transition disabled:opacity-50 flex items-center justify-center gap-2">
                                <span x-show="!submitting">Submit & Book Appointment</span>
                                <span x-show="submitting" class="flex items-center gap-2"><svg class="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>Booking...</span>
                            </button>
                        </template>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div x-show="openBookingModal" @click="closeBookingModal" class="fixed inset-0 bg-black/50" aria-hidden="true"></div>
</div>

<script>
    // This component expects the parent to have:
    // - openBookingModal (boolean)
    // - bookingForm (object with service_type, scheduled_at, triage_screening)
    // - currentStep, triageSteps, submitting
    // - submitTriage(), closeBookingModal(), nextStep(), prevStep()
</script>