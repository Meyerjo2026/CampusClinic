@extends('layouts.app')

@section('title', 'My Appointments')

@push('styles')
<style>
    .gradient-cput { background: linear-gradient(135deg, #002B49 0%, #0072CE 100%); }
</style>
@endpush

@section('content')
<div x-data="appointmentsIndex()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-cput-navy">My Appointments</h1>
            <p class="text-slate-500 mt-1">Manage and view your SWS appointments</p>
        </div>
        <button @click="openBookingModal = true" class="px-5 py-2.5 bg-cput-blue text-white rounded-lg font-semibold hover:bg-cput-blue-light transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Book New Appointment
        </button>
    </div>

    <!-- Filter Tabs -->
    <div class="flex flex-wrap gap-2 mb-6 bg-white rounded-xl p-1 shadow-cput border border-cput-slate-dark" role="tablist">
        <template x-for="filter in filters" :key="filter.value">
            <button @click="activeFilter = filter.value" :aria-selected="activeFilter === filter.value" role="tab" :class="['px-4 py-2 text-sm font-medium rounded-lg transition', activeFilter === filter.value ? 'bg-cput-blue text-white' : 'text-slate-600 hover:bg-cput-slate']">
                {{ filter.label }} <span class="ml-1 px-2 py-0.5 text-xs rounded-full" :class="activeFilter === filter.value ? 'bg-white/20' : 'bg-slate-100 text-slate-500'" x-text="filter.count"></span>
            </button>
        </template>
    </div>

    <!-- Appointments List -->
    <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
        <template x-if="filteredAppointments.length > 0">
            <div class="divide-y divide-cput-slate-dark">
                <template x-for="appt in filteredAppointments" :key="appt.id">
                    <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 hover:bg-cput-slate/50 transition">
                        <div class="flex items-center gap-4 min-w-0">
                            <div :class="['w-12 h-12 rounded-xl flex items-center justify-center', appt.triage_level === 'emergency' ? 'bg-cput-emergency/10' : (appt.triage_level === 'urgent' ? 'bg-yellow-100' : 'bg-cput-blue/10')]">
                                <svg class="w-6 h-6" :class="appt.triage_level === 'emergency' ? 'text-cput-emergency' : (appt.triage_level === 'urgent' ? 'text-yellow-700' : 'text-cput-blue')" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-3">
                                    <p class="font-medium text-slate-900">{{ ucfirst(appt.service_type.replace('_', ' ')) }}</p>
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="
                                        appt.status === 'booked' ? 'bg-blue-100 text-blue-800' :
                                        (appt.status === 'arrived' ? 'bg-cput-cyan/10 text-cput-cyan' :
                                        (appt.status === 'in-consultation' ? 'bg-yellow-100 text-yellow-800' :
                                        (appt.status === 'fulfilled' ? 'bg-emerald-100 text-emerald-800' :
                                        (appt.status === 'noshow' ? 'bg-cput-emergency/10 text-cput-emergency' : 'bg-slate-100 text-slate-600'))))">
                                        {{ appt.status.replace('-', ' ') }}
                                    </span>
                                    @if(appt.is_high_risk_mental_health)
                                        <span class="px-2 py-0.5 text-xs font-semibold bg-cput-emergency/10 text-cput-emergency rounded">High Risk MH</span>
                                    @endif
                                </div>
                                <p class="text-sm text-slate-500 mt-1">{{ appt.scheduled_at_formatted }} • {{ appt.duration_minutes }} min</p>
                                @if(appt.practitioner_name)
                                    <p class="text-sm text-slate-500">with {{ appt.practitioner_name }}</p>
                                @endif
                                @if(appt.location)
                                    <p class="text-sm text-slate-500 flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> {{ appt.location }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a :href="'/student/appointments/' + appt.id" class="px-4 py-2 text-sm font-medium text-cput-blue bg-cput-blue/5 rounded-lg hover:bg-cput-blue/10 transition">View Details</a>
                            @if(appt.status === 'booked' || appt.status === 'arrived')
                                <button @click="cancelAppointment(appt.id)" class="px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition">Cancel</button>
                            @endif
                        </div>
                    </div>
                </template>
            </div>
        </template>
        <template x-if="filteredAppointments.length === 0">
            <div class="px-6 py-12 text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <h3 class="text-lg font-medium text-slate-900 mb-1">No appointments found</h3>
                <p class="text-slate-500 mb-4">{{ emptyMessage }}</p>
                <button @click="openBookingModal = true" class="px-5 py-2.5 bg-cput-blue text-white rounded-lg font-semibold hover:bg-cput-blue-light transition">Book Your First Appointment</button>
            </div>
        </template>
    </div>

    <!-- Pagination -->
    <div x-show="pagination.total > pagination.per_page" class="mt-6 flex items-center justify-center gap-2">
        <button @click="changePage(pagination.current_page - 1)" :disabled="pagination.current_page === 1" class="px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 disabled:opacity-50 disabled:cursor-not-allowed">Previous</button>
        <span class="px-4 py-2 text-sm text-slate-600" x-text="'Page ' + pagination.current_page + ' of ' + pagination.last_page"></span>
        <button @click="changePage(pagination.current_page + 1)" :disabled="pagination.current_page === pagination.last_page" class="px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 disabled:opacity-50 disabled:cursor-not-allowed">Next</button>
    </div>
</div>

<!-- Reuse Booking Modal from Dashboard -->
@include('student.partials.booking-modal')

@push('scripts')
<script>
    function appointmentsIndex() {
        return {
            activeFilter: 'upcoming',
            filters: [
                { value: 'upcoming', label: 'Upcoming', count: 0 },
                { value: 'past', label: 'Past', count: 0 },
                { value: 'cancelled', label: 'Cancelled', count: 0 },
            ],
            appointments: @json($appointments ?? []),
            pagination: @json($appointments ?? ['current_page' => 1, 'last_page' => 1, 'per_page' => 10, 'total' => 0]),
            openBookingModal: false,

            get filteredAppointments() {
                const now = new Date();
                return this.appointments.filter(appt => {
                    const apptDate = new Date(appt.scheduled_at);
                    switch (this.activeFilter) {
                        case 'upcoming': return apptDate >= now && !['cancelled', 'noshow'].includes(appt.status);
                        case 'past': return apptDate < now || ['fulfilled'].includes(appt.status);
                        case 'cancelled': return ['cancelled', 'noshow'].includes(appt.status);
                        default: return true;
                    }
                });
            },

            get emptyMessage() {
                switch (this.activeFilter) {
                    case 'upcoming': return 'No upcoming appointments scheduled.';
                    case 'past': return 'No past appointments yet.';
                    case 'cancelled': return 'No cancelled appointments.';
                    default: return 'No appointments found.';
                }
            },

            async cancelAppointment(id) {
                if (!confirm('Are you sure you want to cancel this appointment?')) return;
                try {
                    const res = await fetch('/api/v1/appointments/' + id + '/status/cancelled', {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                    });
                    const data = await res.json();
                    if (data.success) {
                        const appt = this.appointments.find(a => a.id === id);
                        if (appt) appt.status = 'cancelled';
                    } else {
                        alert(data.message || 'Failed to cancel');
                    }
                } catch (e) {
                    alert('Error cancelling appointment');
                }
            },

            changePage(page) {
                // In production: fetch paginated results
                console.log('Page:', page);
            }
        }
    }
</script>
@endpush