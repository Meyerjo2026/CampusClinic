@extends('layouts.app')

@section('title', 'My Medications')

@push('styles')
<style>
    .gradient-cput { background: linear-gradient(135deg, #002B49 0%, #0072CE 100%); }
</style>
@endpush

@section('content')
<div x-data="medicationsIndex()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-cput-navy">My Medications</h1>
            <p class="text-slate-500 mt-1">Chronic prescriptions, refills, and collection status</p>
        </div>
    </div>

    <!-- Refill Alert Banner -->
    <template x-if="refillsDue.length > 0">
        <div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 mt-0.5">
                    <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.765 1.36-1.234 2.66-2.722 2.66H5.4c-1.488 0-3.487-1.3-2.722-2.66l5.58-9.92zM10 13a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0v-2.25A.75.75 0 0110 13zM10 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 7z" clip-rule="evenodd"/></svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-sm font-semibold text-yellow-800">{{ refillsDue.length }} Prescription(s) Due for Refill</h3>
                    <p class="text-sm text-yellow-700 mt-1">Refills are due within 5 days. Request now to avoid interruption.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <template x-for="rx in refillsDue" :key="rx.id">
                            <a :href="'/student/medications/refill/' + rx.id" class="px-3 py-1.5 text-xs font-medium bg-yellow-600 text-white rounded hover:bg-yellow-700 transition">Refill {{ rx.drug_name }}</a>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- Active Medications -->
    <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-cput-slate-dark">
            <h2 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                <svg class="w-5 h-5 text-cput-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Active Prescriptions ({{ activeMeds.length }})
            </h2>
        </div>
        <div class="divide-y divide-cput-slate-dark">
            <template x-for="med in activeMeds" :key="med.id">
                <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3">
                            <div :class="['w-10 h-10 rounded-xl flex items-center justify-center', med.sapc_schedule >= 5 ? 'bg-cput-emergency/10' : 'bg-cput-cyan/10']">
                                <svg class="w-5 h-5" :class="med.sapc_schedule >= 5 ? 'text-cput-emergency' : 'text-cput-cyan'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944"/></svg>
                            </div>
                            <div>
                                <p class="font-medium text-slate-900">{{ med.drug_name }}</p>
                                <p class="text-sm text-slate-500">{{ med.dosage }} • {{ med.frequency }}</p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-2 py-0.5 text-xs font-medium rounded" :class="med.sapc_schedule >= 5 ? 'bg-cput-emergency/10 text-cput-emergency' : 'bg-slate-100 text-slate-600'">S{{ med.sapc_schedule }}</span>
                                    <span class="px-2 py-0.5 text-xs font-medium bg-emerald-100 text-emerald-800 rounded" x-show="med.is_chronic">Chronic</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                            <span>Prescribed: {{ med.prescribed_at_formatted }}</span>
                            <span>Expires: {{ med.expires_at_formatted }}</span>
                            <span :class="med.repeats_remaining > 0 ? 'text-emerald-700' : 'text-cput-emergency'">{{ med.repeats_remaining }} refills remaining</span>
                        </div>
                        <div x-show="med.isDueForRefill" class="mt-2 text-xs text-yellow-700 font-medium">⚠ Refill due within 5 days</div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button @click="viewDetails(med.id)" class="px-4 py-2 text-sm font-medium text-cput-blue bg-cput-blue/5 rounded-lg hover:bg-cput-blue/10 transition">Details</button>
                        @if(med.repeats_remaining > 0 && med.status === 'active')
                            <a :href="'/student/medications/refill/' + med.id" class="px-4 py-2 text-sm font-medium bg-cput-cyan text-white rounded-lg hover:bg-cput-cyan-dark transition">Request Refill</a>
                        @endif
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- Collection Queue -->
    <template x-if="pendingCollection.length > 0">
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-cput-slate-dark bg-cput-cyan/5">
                <h2 class="text-lg font-semibold text-cput-navy flex items-center gap-2">
                    <svg class="w-5 h-5 text-cput-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12m-7 0h14"/></svg>
                    Ready for Collection ({{ pendingCollection.length }})
                </h2>
            </div>
            <div class="divide-y divide-cput-slate-dark">
                <template x-for="dispense in pendingCollection" :key="dispense.id">
                    <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-cput-cyan/10 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-cput-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944"/></svg>
                            </div>
                            <div>
                                <p class="font-medium text-slate-900">{{ dispense.drug_name }}</p>
                                <p class="text-sm text-slate-500">{{ dispense.dosage }} • {{ dispense.quantity_dispensed }} units</p>
                                <p class="text-xs text-slate-400">Batch: {{ dispense.batch_number }} • Exp: {{ dispense.expires_at_formatted }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="showQrModal = dispense" class="px-4 py-2 text-sm font-medium text-cput-blue bg-cput-blue/5 rounded-lg hover:bg-cput-blue/10 transition flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                Show QR Code
                            </button>
                            <span class="px-3 py-1 text-xs font-medium bg-emerald-100 text-emerald-800 rounded-full">Ready</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <!-- QR Code Modal -->
    <div x-show="showQrModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex min-h-full items-center justify-center p-4">
            <div x-show="showQrModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="w-full max-w-md bg-white rounded-2xl shadow-xl">
                <div class="px-6 py-4 border-b border-cput-slate-dark flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-cput-navy">Collection QR Code</h3>
                    <button @click="showQrModal = null" class="text-slate-400 hover:text-slate-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <svg x-ref="qrCode" class="mx-auto" width="200" height="200"></svg>
                    </div>
                    <p class="text-sm text-slate-600 mb-2">Show this QR code at the pharmacy counter</p>
                    <p class="text-xs text-slate-500">{{ showQrModal.drug_name }} • Batch: {{ showQrModal.batch_number }}</p>
                    <div class="mt-4 p-3 bg-slate-50 rounded-lg text-left text-sm">
                        <p class="font-medium text-slate-700 mb-1">Collection Details:</p>
                        <p class="text-slate-600">Pharmacy: SWS Pharmacy, Ivan Toms Building</p>
                        <p class="text-slate-600">Hours: Mon-Fri 08:30-16:00</p>
                        <p class="text-slate-600">Bring: Student Card + This QR Code</p>
                    </div>
                </div>
            </div>
        </div>
        <div x-show="showQrModal" @click="showQrModal = null" class="fixed inset-0 bg-black/50"></div>
    </div>
</div>

@push('scripts')
<script>
    function medicationsIndex() {
        return {
            activeMeds: @json($activeMeds ?? []),
            pendingCollection: @json($pendingCollection ?? []),
            refillsDue: @json($refillsDue ?? []),
            showQrModal: null,

            viewDetails(id) {
                window.location.href = '/student/medications/' + id;
            }
        }
    }

    // QR Code generation for modal
    document.addEventListener('alpine:init', () => {
        Alpine.store('qrGenerator', {
            generate(element, data) {
                if (window.QRCode && element) {
                    new QRCode(element, {
                        text: JSON.stringify(data),
                        width: 200,
                        height: 200,
                        colorDark: '#002B49',
                        colorLight: '#ffffff',
                        correctLevel: QRCode.CorrectLevel.H
                    });
                }
            }
        });
    });
</script>
@endpush