@extends('layouts.app')

@section('title', 'Profile & Consent')

@push('styles')
<style>
    .gradient-cput { background: linear-gradient(135deg, #002B49 0%, #0072CE 100%); }
</style>
@endpush

@section('content')
<div x-data="patientProfile()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-cput-navy">Profile & Consent</h1>
            <p class="text-slate-500 mt-1">Manage your personal information and privacy settings</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Personal Info -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark">
                    <h2 class="text-lg font-semibold text-cput-navy">Personal Information</h2>
                </div>
                <div class="p-6 space-y-4">
                    <form @submit.prevent="updateProfile" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                                <input type="text" x-model="profile.name" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                                <input type="email" x-model="profile.email" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Student Number</label>
                                <input type="text" :value="profile.student_number" readonly class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-slate-50 text-slate-500 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Date of Birth</label>
                                <input type="date" x-model="profile.date_of_birth" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Gender</label>
                                <select x-model="profile.gender" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                                    <option value="">Select...</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                    <option value="unknown">Prefer not to say</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number</label>
                                <input type="tel" x-model="profile.phone" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="0XX XXX XXXX">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Home Address</label>
                            <textarea x-model="profile.address" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Street address, city, postal code"></textarea>
                        </div>
                        <div class="pt-4 border-t border-cput-slate-dark">
                            <button type="submit" :disabled="savingProfile" class="px-6 py-2.5 text-sm font-semibold bg-cput-blue text-white rounded-lg hover:bg-cput-blue-light transition disabled:opacity-50 flex items-center gap-2">
                                <span x-show="!savingProfile">Save Changes</span>
                                <span x-show="savingProfile" class="flex items-center gap-2"><svg class="animate-spin h-5 w-5" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark">
                    <h2 class="text-lg font-semibold text-cput-navy">Emergency Contact</h2>
                </div>
                <div class="p-6 space-y-4">
                    <form @submit.prevent="updateEmergencyContact" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Contact Name *</label>
                                <input type="text" x-model="emergencyContact.name" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Relationship *</label>
                                <input type="text" x-model="emergencyContact.relationship" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="e.g., Mother, Father, Partner" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number *</label>
                                <input type="tel" x-model="emergencyContact.phone" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="0XX XXX XXXX" required>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-cput-slate-dark">
                            <button type="submit" :disabled="savingEmergency" class="px-6 py-2.5 text-sm font-semibold bg-cput-cyan text-white rounded-lg hover:bg-cput-cyan-dark transition disabled:opacity-50">Update Emergency Contact</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Medical Info -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark">
                    <h2 class="text-lg font-semibold text-cput-navy">Medical Information (Self-Reported)</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Known Allergies</label>
                        <textarea x-model="medicalInfo.allergies" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Drug allergies, food allergies, reactions..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Current Medications</label>
                        <textarea x-model="medicalInfo.current_medications" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="List all current medications with dosages"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Medical Conditions</label>
                        <textarea x-model="medicalInfo.conditions" rows="3" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Chronic conditions, past surgeries, etc."></textarea>
                    </div>
                    <button @click="saveMedicalInfo" :disabled="savingMedical" class="px-6 py-2.5 text-sm font-semibold bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition disabled:opacity-50">Save Medical Info</button>
                </div>
            </div>
        </div>

        <!-- Sidebar: POPIA Consent & Privacy -->
        <div class="space-y-6">
            <!-- POPIA Consent Card -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden" :class="consentGiven ? 'border-emerald-300' : 'border-yellow-300'">
                <div class="px-6 py-4 border-b border-cput-slate-dark" :class="consentGiven ? 'bg-emerald-50' : 'bg-yellow-50'">
                    <h2 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                        <svg class="w-5 h-5" :class="consentGiven ? 'text-emerald-600' : 'text-yellow-600'" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        POPIA Consent Status
                    </h2>
                </div>
                <div class="p-6">
                    @if(!$consentGiven)
                        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 mt-0.5">
                                    <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.765 1.36-1.234 2.66-2.722 2.66H5.4c-1.488 0-3.487-1.3-2.722-2.66l5.58-9.92zM10 13a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0v-2.25A.75.75 0 0110 13zM10 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 7z" clip-rule="evenodd"/></svg>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-sm font-semibold text-yellow-800">Consent Required</h3>
                                    <p class="text-sm text-yellow-700 mt-1">You must provide consent to access health services per POPIA Section 26 & 32 (Special Personal Information).</p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                            <div class="flex items-center gap-3">
                                <div class="flex-shrink-0">
                                    <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-emerald-800">Consent Active</h3>
                                    <p class="text-sm text-emerald-700 mt-1">Given on {{ $consentDate ?? 'recently' }}. You may withdraw consent at any time.</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-3">
                        <h4 class="text-sm font-semibold text-slate-700">What you're consenting to:</h4>
                        <ul class="text-sm text-slate-600 space-y-1 list-disc list-inside">
                            <li>Processing of health data for clinical care</li>
                            <li>Sharing within SWS multidisciplinary team</li>
                            <li>Electronic health record maintenance</li>
                            <li>SMS/Email appointment & refill reminders</li>
                            <li>Anonymized data for service improvement</li>
                        </ul>
                        
                        <div class="pt-4 border-t border-cput-slate-dark">
                            @if(!$consentGiven)
                                <button @click="giveConsent" :disabled="givingConsent" class="w-full px-4 py-2.5 text-sm font-semibold bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition disabled:opacity-50 flex items-center justify-center gap-2">
                                    <span x-show="!givingConsent">I Consent - Enable My Health Services</span>
                                    <span x-show="givingConsent" class="flex items-center gap-2"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>Processing...</span>
                                </button>
                            @else
                                <button @click="withdrawConsent" :disabled="withdrawingConsent" class="w-full px-4 py-2.5 text-sm font-semibold bg-cput-emergency text-white rounded-lg hover:bg-cput-emergency-dark transition disabled:opacity-50 flex items-center justify-center gap-2">
                                    <span x-show="!withdrawingConsent">Withdraw Consent</span>
                                    <span x-show="withdrawingConsent" class="flex items-center gap-2"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>Processing...</span>
                                </button>
                                <p class="text-xs text-slate-500 text-center mt-2">Withdrawing consent will restrict access to health services.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Access Log -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark">
                    <h2 class="text-lg font-semibold text-cput-navy">Recent Data Access</h2>
                </div>
                <div class="divide-y divide-cput-slate-dark">
                    <template x-for="log in accessLogs" :key="log.id">
                        <div class="px-6 py-3 flex items-center justify-between hover:bg-cput-slate/50">
                            <div class="flex items-center gap-3">
                                <div :class="['w-8 h-8 rounded-full flex items-center justify-center', log.action === 'READ' ? 'bg-blue-100 text-blue-700' : (log.action === 'WRITE' ? 'bg-emerald-100 text-emerald-700' : 'bg-yellow-100 text-yellow-700')]">
                                    <span class="text-xs font-bold">{{ log.action[0] }}</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-slate-900">{{ log.resource_type }}</p>
                                    <p class="text-xs text-slate-500">{{ log.timestamp_formatted }}</p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 text-xs font-medium rounded" :class="log.consent_status === 'valid' ? 'bg-emerald-100 text-emerald-800' : 'bg-yellow-100 text-yellow-800'" x-text="log.consent_status"></span>
                        </div>
                    </template>
                    <template x-if="accessLogs.length === 0">
                        <div class="px-6 py-6 text-center text-slate-500">
                            <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p>No access logs yet</p>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Account Actions -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark">
                    <h2 class="text-lg font-semibold text-cput-navy">Account</h2>
                </div>
                <div class="p-4 space-y-3">
                    <a href="{{ route('password.change') }}" class="block p-3 text-left bg-slate-50 rounded-lg hover:bg-slate-100 transition flex items-center gap-3">
                        <div class="w-8 h-8 bg-cput-blue/10 rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-cput-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg></div>
                        <div>
                            <p class="font-medium text-slate-900">Change Password</p>
                            <p class="text-sm text-slate-500">Update your login credentials</p>
                        </div>
                    </a>
                    <a href="{{ route('security.two-factor') }}" class="block p-3 text-left bg-slate-50 rounded-lg hover:bg-slate-100 transition flex items-center gap-3">
                        <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944"/></svg></div>
                        <div>
                            <p class="font-medium text-slate-900">Two-Factor Authentication</p>
                            <p class="text-sm text-slate-500">Add extra security to your account</p>
                        </div>
                    </a>
                    <button @click="exportData" class="block w-full p-3 text-left bg-slate-50 rounded-lg hover:bg-slate-100 transition flex items-center gap-3">
                        <div class="w-8 h-8 bg-cput-cyan/10 rounded-lg flex items-center justify-center"><svg class="w-4 h-4 text-cput-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg></div>
                        <div>
                            <p class="font-medium text-slate-900">Export My Data (POPIA)</p>
                            <p class="text-sm text-slate-500">Download your health records</p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function patientProfile() {
        return {
            consentGiven: {{ $patient->popia_consent_given ?? false }},
            consentDate: "{{ $patient->popia_consent_at?->format('F j, Y') }}",
            savingProfile: false,
            savingEmergency: false,
            savingMedical: false,
            givingConsent: false,
            withdrawingConsent: false,
            
            profile: {
                name: "{{ auth()->user()->name }}",
                email: "{{ auth()->user()->email }}",
                student_number: "{{ $patient->student_number }}",
                date_of_birth: "{{ $patient->date_of_birth?->format('Y-m-d') ?? '' }}",
                gender: "{{ $patient->gender ?? '' }}",
                phone: "{{ $patient->contact_details[0].value ?? '' }}",
                address: "{{ $patient->address[0].line[0] ?? '' }}",
            },
            
            emergencyContact: {
                name: "{{ $patient->emergency_contact?.name ?? '' }}",
                relationship: "{{ $patient->emergency_contact?.relationship ?? '' }}",
                phone: "{{ $patient->emergency_contact?.phone ?? '' }}",
            },
            
            medicalInfo: {
                allergies: "{{ $patient->allergies_display ?? '' }}",
                current_medications: "",
                conditions: "",
            },

            accessLogs: @json($accessLogs ?? []),

            async updateProfile() {
                this.savingProfile = true;
                try {
                    const res = await fetch('/api/v1/patient/profile', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify(this.profile)
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Profile updated successfully');
                    } else {
                        alert(data.message || 'Failed to update');
                    }
                } catch (e) {
                    alert('Error updating profile');
                } finally {
                    this.savingProfile = false;
                }
            },

            async updateEmergencyContact() {
                this.savingEmergency = true;
                try {
                    const res = await fetch('/api/v1/patient/emergency-contact', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify(this.emergencyContact)
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Emergency contact updated');
                    } else {
                        alert(data.message || 'Failed to update');
                    }
                } catch (e) {
                    alert('Error updating emergency contact');
                } finally {
                    this.savingEmergency = false;
                }
            },

            async saveMedicalInfo() {
                this.savingMedical = true;
                try {
                    const res = await fetch('/api/v1/patient/medical-info', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify(this.medicalInfo)
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.showToast('Medical info saved');
                    } else {
                        alert(data.message || 'Failed to save');
                    }
                } catch (e) {
                    alert('Error saving medical info');
                } finally {
                    this.savingMedical = false;
                }
            },

            async giveConsent() {
                this.givingConsent = true;
                try {
                    const res = await fetch('/api/v1/patient/consent', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.consentGiven = true;
                        this.showToast('Consent given - health services enabled');
                    } else {
                        alert(data.message || 'Failed to give consent');
                    }
                } catch (e) {
                    alert('Error giving consent');
                } finally {
                    this.givingConsent = false;
                }
            },

            async withdrawConsent() {
                if (!confirm('Are you sure? This will restrict access to health services.')) return;
                this.withdrawingConsent = true;
                try {
                    const res = await fetch('/api/v1/patient/consent', {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.consentGiven = false;
                        this.showToast('Consent withdrawn');
                    } else {
                        alert(data.message || 'Failed to withdraw');
                    }
                } catch (e) {
                    alert('Error withdrawing consent');
                } finally {
                    this.withdrawingConsent = false;
                }
            },

            async exportData() {
                // TODO: Implement data export
                alert('Data export feature coming soon');
            },

            showToast(message) {
                // Simple toast implementation
                const toast = document.createElement('div');
                toast.className = 'fixed bottom-4 right-4 bg-cput-navy text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-slide-up';
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }
        }
    }
</script>
<style>
    @keyframes slide-up { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .animate-slide-up { animation: slide-up 0.3s ease-out; }
</style>
@endpush