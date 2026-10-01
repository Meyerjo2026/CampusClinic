@extends('layouts.app')

@section('title', 'Clinical Dashboard')

@push('styles')
<style>
    .gradient-cput { background: linear-gradient(135deg, #002B49 0%, #0072CE 100%); }
    .crisis-pulse { animation: pulse 1.5s ease-in-out infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
</style>
@endpush

@section('content')
<div x-data="clinicalDashboard()" x-init="initReverb()">
    <!-- Crisis Alert Banner Area -->
    <div id="clinical-crisis-banner" class="hidden"></div>

    <!-- Header Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 uppercase tracking-wide">Today's Appointments</p>
                    <p class="text-3xl font-bold text-cput-navy mt-1">{{ $todayAppointmentsCount ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-cput-blue/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-cput-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 uppercase tracking-wide">Waiting Room</p>
                    <p class="text-3xl font-bold text-cput-navy mt-1">{{ $waitingCount ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-cput-cyan/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-cput-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 uppercase tracking-wide">High-Risk Patients</p>
                    <p class="text-3xl font-bold text-cput-emergency mt-1">{{ $highRiskCount ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-cput-emergency/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-cput-emergency" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 uppercase tracking-wide">Refills Due (5 days)</p>
                    <p class="text-3xl font-bold text-cput-navy mt-1">{{ $refillsDueCount ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Patient Queue -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Active Crisis Alerts Panel -->
            <div x-show="activeCrises.length > 0" x-transition class="bg-cput-emergency/5 border-2 border-cput-emergency rounded-2xl overflow-hidden crisis-pulse">
                <div class="bg-cput-emergency text-white px-6 py-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold flex items-center gap-2">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.765 1.36-1.234 2.66-2.722 2.66H5.4c-1.488 0-3.487-1.3-2.722-2.66l5.58-9.92zM10 13a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0v-2.25A.75.75 0 0110 13zM10 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 7z" clip-rule="evenodd"/></svg>
                        ACTIVE CRISIS ALERTS ({{ activeCrises.length }})
                    </h2>
                    <button @click="acknowledgeAllCrises" class="px-3 py-1.5 text-sm font-medium bg-white text-cput-emergency rounded-lg hover:bg-slate-100 transition">Acknowledge All</button>
                </div>
                <div class="p-4 divide-y divide-cput-emergency/20">
                    <template x-for="crisis in activeCrises" :key="crisis.alert_id">
                        <div class="py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1">
                                    <p class="font-medium text-slate-900">{{ crisis.patient_name }} <span class="text-sm font-normal text-slate-500">({{ crisis.student_number }})</span></p>
                                    <p class="text-sm text-slate-600 mt-1">{{ crisis.crisis_reasons.join(', ') }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <template x-for="action in crisis.recommended_actions" :key="action">
                                            <span class="px-2 py-0.5 text-xs bg-white text-cput-emergency rounded border border-cput-emergency/30">{{ action }}</span>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <a :href="'tel:' + crisis.emergency_contacts.sws_crisis_line.replace(/\D/g, '')" class="px-3 py-1.5 text-xs font-semibold bg-white text-cput-emergency rounded hover:bg-slate-100 transition">Call SWS</a>
                                    <a :href="'tel:' + crisis.emergency_contacts.campus_protection.replace(/\D/g, '')" class="px-3 py-1.5 text-xs font-semibold bg-white text-cput-emergency rounded hover:bg-slate-100 transition">Call Security</a>
                                    <button @click="acknowledgeCrisis(crisis.alert_id)" class="px-3 py-1.5 text-xs font-semibold bg-cput-emergency text-white rounded hover:bg-cput-emergency-dark transition">Acknowledge</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Patient Queue -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                        <svg class="w-5 h-5 text-cput-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Today's Patient Queue
                    </h2>
                    <div class="flex items-center gap-2">
                        <select x-model="queueFilter" @change="filterQueue" class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                            <option value="all">All</option>
                            <option value="waiting">Waiting</option>
                            <option value="arrived">Arrived</option>
                            <option value="in-consultation">In Consultation</option>
                        </select>
                    </div>
                </div>
                
                <div class="divide-y divide-cput-slate-dark">
                    <template x-for="patient in filteredQueue" :key="patient.id">
                        <div class="px-6 py-4 flex items-center justify-between hover:bg-cput-slate/50 transition">
                            <div class="flex items-center gap-4 min-w-0 flex-1">
                                <div :class="['w-10 h-10 rounded-xl flex items-center justify-center', patient.triage_level === 'emergency' ? 'bg-cput-emergency/10' : (patient.triage_level === 'urgent' ? 'bg-yellow-100' : 'bg-cput-blue/10')]">
                                    <span class="text-sm font-semibold" :class="patient.triage_level === 'emergency' ? 'text-cput-emergency' : (patient.triage_level === 'urgent' ? 'text-yellow-700' : 'text-cput-blue')">
                                        {{ patient.triage_level === 'emergency' ? 'E' : (patient.triage_level === 'urgent' ? 'U' : 'R') }}
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900 truncate">{{ patient.name }}</p>
                                    <p class="text-sm text-slate-500">{{ patient.student_number }} • {{ patient.service_type }} • {{ patient.scheduled_time }}</p>
                                    @if(patient.is_high_risk_mental_health)
                                        <span class="inline-block mt-1 px-2 py-0.5 text-xs font-semibold bg-cput-emergency/10 text-cput-emergency rounded">High Risk MH</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 ml-4 flex-shrink-0">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full {{ 
                                    patient.status === 'waiting' ? 'bg-slate-100 text-slate-700' : 
                                    (patient.status === 'arrived' ? 'bg-blue-100 text-blue-800' : 
                                    (patient.status === 'in-consultation' ? 'bg-cput-cyan/10 text-cput-cyan' : 'bg-emerald-100 text-emerald-800')) }}">
                                    {{ patient.status.replace('-', ' ') }}
                                </span>
                                <div class="relative" x-data="{ open: false }">
                                    <button @click="open = !open" class="p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-100 rounded-lg transition"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg></button>
                                    <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-1 w-40 bg-white rounded-lg shadow-cput-lg border border-slate-200 py-1 hidden" role="menu">
                                        <a :href="'/clinical/encounters/create?patient=' + patient.id" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">Start Encounter</a>
                                        <a :href="'/student/appointments/' + patient.appointment_id" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">View Details</a>
                                        @if(patient.status === 'waiting')
                                            <button @click="updateStatus(patient.id, 'arrived')" class="block w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">Mark Arrived</button>
                                        @endif
                                        @if(patient.status === 'arrived')
                                            <button @click="updateStatus(patient.id, 'in-consultation')" class="block w-full text-left px-4 py-2 text-sm text-cput-blue hover:bg-cput-blue/5" role="menuitem">Start Consultation</button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- Right: Quick Actions & High-Risk Panel -->
        <div class="space-y-6">
            <!-- High Risk Patients Panel -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                        <svg class="w-5 h-5 text-cput-emergency" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        High-Risk Mental Health
                    </h2>
                </div>
                <div class="divide-y divide-cput-slate-dark">
                    @forelse($highRiskPatients as $patient)
                        <div class="px-6 py-4 hover:bg-cput-emergency/5 transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-slate-900">{{ $patient->user->name }}</p>
                                    <p class="text-sm text-slate-500">{{ $patient->student_number }}</p>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach($patient->recentDiagnoses as $dx)
                                            <span class="px-2 py-0.5 text-xs bg-cput-emergency/10 text-cput-emergency rounded">{{ $dx }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <button @click="outreachPatient({{ $patient->id }})" class="px-3 py-1.5 text-xs font-semibold bg-cput-emergency text-white rounded hover:bg-cput-emergency-dark transition">Outreach</button>
                                    <a href="{{ route('clinical.encounters.patient', $patient) }}" class="px-3 py-1.5 text-xs font-semibold bg-slate-100 text-slate-700 rounded hover:bg-slate-200 transition">History</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-8 text-center text-slate-500">
                            <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p>No high-risk patients flagged</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
                <div class="px-6 py-4 border-b border-cput-slate-dark">
                    <h2 class="text-lg font-semibold text-cput-navy">Quick Actions</h2>
                </div>
                <div class="p-4 space-y-3">
                    <a href="{{ route('clinical.encounters.create') }}" class="block p-4 bg-cput-blue/5 rounded-xl border border-cput-blue/20 hover:bg-cput-blue/10 transition flex items-center gap-3 group">
                        <div class="w-10 h-10 bg-cput-blue rounded-lg flex items-center justify-center group-hover:bg-cput-blue-dark transition">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-cput-navy">New Clinical Encounter</p>
                            <p class="text-sm text-slate-500">Document SOAP notes</p>
                        </div>
                    </a>
                    <a href="{{ route('clinical.queue') }}" class="block p-4 bg-cput-cyan/5 rounded-xl border border-cput-cyan/20 hover:bg-cput-cyan/10 transition flex items-center gap-3 group">
                        <div class="w-10 h-10 bg-cput-cyan rounded-lg flex items-center justify-center group-hover:bg-cput-cyan-dark transition">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-cput-navy">Manage Patient Queue</p>
                            <p class="text-sm text-slate-500">View & update statuses</p>
                        </div>
                    </a>
                    @if(auth()->user()->hasRole(['pharmacist', 'admin']))
                        <a href="{{ route('pharmacy.queue') }}" class="block p-4 bg-emerald-50 rounded-xl border border-emerald-200 hover:bg-emerald-100 transition flex items-center gap-3 group">
                            <div class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center group-hover:bg-emerald-600 transition">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944"/></svg>
                            </div>
                            <div>
                                <p class="font-medium text-cput-navy">Pharmacy Queue</p>
                                <p class="text-sm text-slate-500">Dispense medications</p>
                            </div>
                        </a>
                    @endif
                    <a href="{{ route('reports.utilization') }}" class="block p-4 bg-slate-50 rounded-xl border border-slate-200 hover:bg-slate-100 transition flex items-center gap-3 group">
                        <div class="w-10 h-10 bg-slate-500 rounded-lg flex items-center justify-center group-hover:bg-slate-600 transition">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-cput-navy">Utilization Reports</p>
                            <p class="text-sm text-slate-500">Daily/Weekly analytics</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Emergency Contacts Reference -->
            <div class="bg-cput-navy rounded-2xl text-white p-4">
                <h3 class="font-semibold mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5 text-cput-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    Emergency Numbers
                </h3>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <a href="tel:0216501271" class="hover:text-cput-cyan transition">SWS Crisis: 021 650 1271</a>
                    <a href="tel:0800242526" class="hover:text-cput-cyan transition">UCT Careline: 0800 24 25 26</a>
                    <a href="tel:0806502222" class="hover:text-cput-cyan transition">Campus Protection: 080 650 2222</a>
                    <a href="tel:0800363636" class="hover:text-cput-cyan transition">HIGHER HEALTH: 0800 36 36 36</a>
                    <a href="tel:0800567567" class="hover:text-cput-cyan transition">SADAG Suicide: 0800 567 567</a>
                    <a href="tel:10111" class="hover:text-cput-cyan transition">Police: 10111</a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function clinicalDashboard() {
        return {
            activeCrises: [],
            filteredQueue: [],
            queueFilter: 'all',
            queueData: @json($patientQueue ?? []),

            initReverb() {
                this.filteredQueue = this.queueData;
                
                if (typeof Echo !== 'undefined') {
                    // Listen to crisis channels
                    ['SWS-Triage-Dashboard', 'SWS-Crisis-Team', 'SWS-Security'].forEach(channel => {
                        Echo.channel(channel)
                            .listen('crisis.alert', (e) => {
                                this.handleCrisisAlert(e);
                            });
                    });

                    // Listen for appointment updates
                    Echo.channel('clinical-updates')
                        .listen('AppointmentStatusUpdated', (e) => {
                            this.updateLocalQueue(e);
                        });
                }
            },

            handleCrisisAlert(data) {
                // Check if already exists
                const exists = this.activeCrises.some(c => c.alert_id === data.alert_id);
                if (!exists) {
                    this.activeCrises.unshift(data);
                    
                    // Play alert sound
                    this.playAlertSound();
                    
                    // Show browser notification if permission granted
                    if (Notification.permission === 'granted') {
                        new Notification('CRISIS ALERT: ' + data.patient_name, {
                            body: data.crisis_reasons.join(', '),
                            icon: '/favicon.ico',
                            tag: data.alert_id
                        });
                    }
                }
            },

            acknowledgeCrisis(alertId) {
                const index = this.activeCrises.findIndex(c => c.alert_id === alertId);
                if (index !== -1) {
                    this.activeCrises.splice(index, 1);
                }
                // TODO: API call to mark as acknowledged
                fetch('/api/v1/crisis/' + alertId + '/acknowledge', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                });
            },

            acknowledgeAllCrises() {
                this.activeCrises.forEach(c => this.acknowledgeCrisis(c.alert_id));
                this.activeCrises = [];
            },

            filterQueue() {
                if (this.queueFilter === 'all') {
                    this.filteredQueue = this.queueData;
                } else {
                    this.filteredQueue = this.queueData.filter(p => p.status === this.queueFilter);
                }
            },

            updateStatus(patientId, status) {
                fetch('/api/v1/appointments/' + patientId + '/status/' + status, {
                    method: 'PATCH',
                    headers: { 
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                }).then(() => {
                    // Update local state
                    const patient = this.filteredQueue.find(p => p.id === patientId);
                    if (patient) patient.status = status;
                    
                    // Also update in queueData
                    const inQueue = this.queueData.find(p => p.id === patientId);
                    if (inQueue) inQueue.status = status;
                });
            },

            updateLocalQueue(data) {
                // Update queue from Reverb event
                const patient = this.queueData.find(p => p.id === data.appointment_id);
                if (patient) {
                    patient.status = data.new_status;
                    this.filterQueue();
                }
            },

            outreachPatient(patientId) {
                // Open outreach modal or navigate
                window.location.href = '/clinical/outreach/' + patientId;
            },

            playAlertSound() {
                // Create a simple alert tone using Web Audio API
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.frequency.value = 880; // A5
                gain.gain.value = 0.1;
                osc.start();
                setTimeout(() => { osc.stop(); ctx.close(); }, 500);
            }
        }
    }

    // Request notification permission on load
    if (Notification.permission === 'default') {
        Notification.requestPermission();
    }
</script>
@endpush