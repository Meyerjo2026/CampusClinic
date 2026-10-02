@extends('layouts.app')

@section('title', 'Pharmacy Dispensing')

@push('styles')
<style>
    .gradient-cput { background: linear-gradient(135deg, #002B49 0%, #0072CE 100%); }
</style>
@endpush

@section('content')
<div x-data="pharmacyQueue()">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-cput-navy">Pharmacy Queue</h1>
            <p class="text-slate-500 mt-1">Manage medication dispensing and collection</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('pharmacy.chronic-refills') }}" class="px-4 py-2 text-sm font-medium bg-emerald-500 text-white rounded-lg hover:bg-emerald-600 transition flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944"/></svg>
                Chronic Refills Due
            </a>
            <button @click="refreshQueue" :disabled="loading" class="px-4 py-2 text-sm font-medium text-cput-blue bg-cput-blue/5 rounded-lg hover:bg-cput-blue/10 transition disabled:opacity-50 flex items-center gap-1">
                <svg class="w-4 h-4" :class="loading ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark p-4">
            <p class="text-sm text-slate-500">Pending</p>
            <p class="text-2xl font-bold text-cput-blue mt-1" x-text="stats.pending"></p>
        </div>
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark p-4">
            <p class="text-sm text-slate-500">Dispensed Today</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1" x-text="stats.dispensed"></p>
        </div>
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark p-4">
            <p class="text-sm text-slate-500">Collected</p>
            <p class="text-2xl font-bold text-cput-cyan mt-1" x-text="stats.collected"></p>
        </div>
        <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark p-4">
            <p class="text-sm text-slate-500">Schedule 5/6</p>
            <p class="text-2xl font-bold text-cput-emergency mt-1" x-text="stats.controlled"></p>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark p-4 mb-6 flex flex-wrap gap-4">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
            <input type="text" x-model="searchQuery" @input.debounce.300ms="filterQueue" placeholder="Patient name, student number, drug..." class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
        </div>
        <div class="flex gap-2">
            <template x-for="filter in statusFilters" :key="filter.value">
                <button @click="statusFilter = filter.value" :class="['px-3 py-1.5 text-sm font-medium rounded-lg transition', statusFilter === filter.value ? 'bg-cput-blue text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']">
                    {{ filter.label }} ({{ filter.count }})
                </button>
            </template>
        </div>
    </div>

    <!-- Queue Table -->
    <div class="bg-white rounded-2xl shadow-cput border border-cput-slate-dark overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-cput-slate">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Patient</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Medication</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Schedule</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Qty</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Prepared</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-cput-slate-dark">
                    <template x-for="item in filteredQueue" :key="item.id">
                        <tr :class="item.sapc_schedule >= 5 ? 'bg-cput-emergency/5' : ''">
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-medium text-slate-900">{{ item.patient_name }}</p>
                                    <p class="text-sm text-slate-500">{{ item.student_number }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-medium text-slate-900">{{ item.drug_name }}</p>
                                <p class="text-sm text-slate-500">{{ item.dosage }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded" :class="item.sapc_schedule >= 5 ? 'bg-cput-emergency/10 text-cput-emergency' : 'bg-slate-100 text-slate-600'">
                                    S{{ item.sapc_schedule }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-700">{{ item.quantity_dispensed }}</td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 text-xs font-medium rounded-full" :class="
                                    item.collection_method === 'locker' ? 'bg-cput-cyan/10 text-cput-cyan' :
                                    (item.collection_method === 'delivery' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600')">
                                    {{ item.collection_method }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500">{{ item.prepared_at }}</td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 text-xs font-semibold rounded-full" :class="
                                    item.status === 'prepared' ? 'bg-blue-100 text-blue-800' :
                                    (item.status === 'dispensed' ? 'bg-cput-cyan/10 text-cput-cyan' :
                                    (item.status === 'collected' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'))">
                                    {{ item.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <template x-if="item.status === 'prepared'">
                                        <button @click="openDispenseModal(item)" class="px-3 py-1.5 text-xs font-medium bg-cput-blue text-white rounded hover:bg-cput-blue-light transition">Dispense</button>
                                    </template>
                                    <template x-if="item.status === 'prepared' || item.status === 'dispensed'">
                                        <button @click="showQrCode(item)" class="px-3 py-1.5 text-xs font-medium text-cput-blue bg-cput-blue/5 rounded hover:bg-cput-blue/10 transition flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            QR
                                        </button>
                                    </template>
                                    <template x-if="item.status === 'collected'">
                                        <span class="px-3 py-1.5 text-xs font-medium bg-emerald-100 text-emerald-800 rounded">Collected</span>
                                    </template>
                                    <a :href="'/clinical/encounters?patient=' + item.patient_id" class="px-3 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 rounded hover:bg-slate-200 transition">Notes</a>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        
        <template x-if="filteredQueue.length === 0">
            <div class="px-6 py-12 text-center">
                <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944"/></svg>
                <p class="text-slate-500">No items in queue</p>
            </div>
        </template>
    </div>
</div>

<!-- Dispense Modal -->
<div x-show="dispenseModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="dispenseModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="w-full max-w-lg bg-white rounded-2xl shadow-xl">
            <div class="px-6 py-4 border-b border-cput-slate-dark flex items-center justify-between">
                <h3 class="text-lg font-semibold text-cput-navy">Dispense Medication</h3>
                <button @click="dispenseModal = null" class="text-slate-400 hover:text-slate-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form @submit.prevent="submitDispense" class="p-6 space-y-4">
                <input type="hidden" :name="'medication_request_id'" :value="dispenseModal.id">
                <input type="hidden" :name="'drug_name'" :value="dispenseModal.drug_name">
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Batch Number *</label>
                    <input type="text" name="batch_number" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Manufacturer batch number">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Expiry Date *</label>
                    <input type="date" name="expires_at" :min="today" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Quantity to Dispense *</label>
                    <input type="number" name="quantity_dispensed" min="1" :max="dispenseModal.quantity" required class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" x-model.number="dispenseForm.quantity">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Collection Method</label>
                    <select name="collection_method" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" x-model="dispenseForm.collection_method">
                        <option value="counter">Counter Pickup</option>
                        <option value="locker">Smart Locker</option>
                        <option value="delivery">Delivery (if eligible)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Pharmacist Notes</label>
                    <textarea name="pharmacist_notes" rows="2" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent" placeholder="Counselling provided, special instructions..."></textarea>
                </div>
                <div x-show="dispenseModal.sapc_schedule >= 5" class="p-3 bg-cput-emergency/5 border border-cput-emergency/20 rounded-lg">
                    <p class="text-sm text-cput-emergency"><strong>Schedule {{ dispenseModal.sapc_schedule }} Controlled Substance</strong></p>
                    <p class="text-xs text-slate-600 mt-1">Controlled substance register entry required.</p>
                    <input type="text" name="controlled_substance_register_entry" class="mt-2 w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-cput-blue focus:border-transparent text-sm" placeholder="Register entry number">
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-cput-slate-dark">
                    <button type="button" @click="dispenseModal = null" class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200 transition">Cancel</button>
                    <button type="submit" :disabled="dispensing" class="px-4 py-2 text-sm font-semibold bg-cput-blue text-white rounded-lg hover:bg-cput-blue-light transition disabled:opacity-50 flex items-center gap-2">
                        <span x-show="!dispensing">Dispense Medication</span>
                        <span x-show="dispensing" class="flex items-center gap-2"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>Dispensing...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div x-show="dispenseModal" @click="dispenseModal = null" class="fixed inset-0 bg-black/50"></div>
</div>

<!-- QR Code Modal -->
<div x-show="qrModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="qrModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="w-full max-w-md bg-white rounded-2xl shadow-xl">
            <div class="px-6 py-4 border-b border-cput-slate-dark flex items-center justify-between">
                <h3 class="text-lg font-semibold text-cput-navy">Collection QR Code</h3>
                <button @click="qrModal = null" class="text-slate-400 hover:text-slate-600"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <div class="p-6 text-center">
                <div class="mb-4" x-ref="qrCanvas"></div>
                <p class="text-sm text-slate-600 mb-2">Patient: <span class="font-medium" x-text="qrModal.patient_name"></span></p>
                <p class="text-xs text-slate-500"><span x-text="qrModal.drug_name"></span> • Batch: <span x-text="qrModal.batch_number"></span></p>
                <div class="mt-4 p-3 bg-slate-50 rounded-lg text-left text-sm">
                    <p class="font-medium text-slate-700 mb-1">Collection Instructions:</p>
                    <ul class="text-slate-600 space-y-1 list-disc list-inside">
                        <li>Present this QR code at SWS Pharmacy counter</li>
                        <li>Bring student card for verification</li>
                        <li>Hours: Mon-Fri 08:30-16:00</li>
                        <li>QR expires in 7 days</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <div x-show="qrModal" @click="qrModal = null" class="fixed inset-0 bg-black/50"></div>
</div>

@push('scripts')
<script>
    function pharmacyQueue() {
        return {
            loading: false,
            searchQuery: '',
            statusFilter: 'all',
            queue: @json($queue ?? []),
            stats: { pending: 0, dispensed: 0, collected: 0, controlled: 0 },
            statusFilters: [
                { value: 'all', label: 'All', count: 0 },
                { value: 'prepared', label: 'Prepared', count: 0 },
                { value: 'dispensed', label: 'Dispensed', count: 0 },
                { value: 'collected', label: 'Collected', count: 0 },
            ],
            dispenseModal: null,
            qrModal: null,
            dispensing: false,
            dispenseForm: {
                quantity: 1,
                collection_method: 'counter',
            },
            today: new Date().toISOString().split('T')[0],

            get filteredQueue() {
                let result = this.queue;
                
                if (this.statusFilter !== 'all') {
                    result = result.filter(item => item.status === this.statusFilter);
                }
                
                if (this.searchQuery) {
                    const q = this.searchQuery.toLowerCase();
                    result = result.filter(item => 
                        item.patient_name.toLowerCase().includes(q) ||
                        item.student_number.toLowerCase().includes(q) ||
                        item.drug_name.toLowerCase().includes(q)
                    );
                }
                
                return result;
            },

            async refreshQueue() {
                this.loading = true;
                try {
                    const res = await fetch('/api/v1/pharmacy/queue');
                    const data = await res.json();
                    if (data.success) {
                        this.queue = data.data.data;
                        this.updateStats();
                    }
                } catch (e) {
                    console.error(e);
                } finally {
                    this.loading = false;
                }
            },

            updateStats() {
                this.stats.pending = this.queue.filter(i => i.status === 'prepared').length;
                this.stats.dispensed = this.queue.filter(i => i.status === 'dispensed').length;
                this.stats.collected = this.queue.filter(i => i.status === 'collected').length;
                this.stats.controlled = this.queue.filter(i => i.sapc_schedule >= 5).length;
                
                this.statusFilters.forEach(f => {
                    if (f.value !== 'all') {
                        f.count = this.queue.filter(i => i.status === f.value).length;
                    } else {
                        f.count = this.queue.length;
                    }
                });
            },

            openDispenseModal(item) {
                this.dispenseModal = item;
                this.dispenseForm.quantity = Math.min(item.quantity, item.quantity_dispensed || 1);
            },

            async submitDispense() {
                this.dispensing = true;
                try {
                    const formData = new FormData();
                    formData.append('medication_request_id', this.dispenseModal.id);
                    formData.append('batch_number', document.querySelector('input[name="batch_number"]').value);
                    formData.append('expires_at', document.querySelector('input[name="expires_at"]').value);
                    formData.append('quantity_dispensed', this.dispenseForm.quantity);
                    formData.append('collection_method', this.dispenseForm.collection_method);
                    formData.append('pharmacist_notes', document.querySelector('textarea[name="pharmacist_notes"]').value);
                    if (this.dispenseModal.sapc_schedule >= 5) {
                        formData.append('controlled_substance_register_entry', document.querySelector('input[name="controlled_substance_register_entry"]')?.value || '');
                    }

                    const res = await fetch('/api/v1/medication-requests/' + this.dispenseModal.medication_request_id + '/dispense', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                        body: formData
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.dispenseModal = null;
                        this.refreshQueue();
                    } else {
                        alert(data.message || 'Dispensing failed');
                    }
                } catch (e) {
                    alert('Error dispensing medication');
                } finally {
                    this.dispensing = false;
                }
            },

            showQrCode(item) {
                this.qrModal = item;
                this.$nextTick(() => {
                    if (window.QRCode && this.$refs.qrCanvas) {
                        this.$refs.qrCanvas.innerHTML = '';
                        new QRCode(this.$refs.qrCanvas, {
                            text: JSON.stringify({ token: item.qr_code_token, type: 'medication_collection' }),
                            width: 200,
                            height: 200,
                            colorDark: '#002B49',
                            colorLight: '#ffffff',
                            correctLevel: QRCode.CorrectLevel.H
                        });
                    }
                });
            }
        }
    }

    // Initialize queue on load
    document.addEventListener('alpine:init', () => {
        Alpine.store('pharmacyQueue', {
            init() {}
        });
    });
</script>
@endpush