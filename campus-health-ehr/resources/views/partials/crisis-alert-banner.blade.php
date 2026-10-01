<!-- Crisis Alert Banner - Driven by Laravel Reverb -->
@php
    $alert = session('crisis_alert');
@endphp

@if($alert)
<div id="crisis-alert-banner" class="fixed top-0 left-0 right-0 z-50 bg-cput-emergency text-white shadow-cput-lg transform transition-transform duration-300" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="max-w-7xl mx-auto px-4 py-3">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 mt-0.5">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.765 1.36-1.234 2.66-2.722 2.66H5.4c-1.488 0-3.487-1.3-2.722-2.66l5.58-9.92zM10 13a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0v-2.25A.75.75 0 0110 13zM10 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 7z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-semibold">{{ $alert['title'] ?? 'CRISIS ALERT' }}</h3>
                <p class="text-sm mt-1">{{ $alert['message'] ?? 'A crisis situation has been detected. Please check the triage dashboard immediately.' }}</p>
                @if(isset($alert['actions']))
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($alert['actions'] as $action)
                            <a href="{{ $action['url'] }}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold bg-white text-cput-emergency rounded hover:bg-slate-100 transition-colors">
                                {{ $action['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="flex-shrink-0">
                <button onclick="this.parentElement.parentElement.parentElement.style.display='none'" class="text-white/80 hover:text-white transition-colors" aria-label="Dismiss crisis alert">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Live Reverb Crisis Listener -->
@if(config('broadcasting.default') === 'reverb')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Echo !== 'undefined') {
            // Listen for crisis alerts on all relevant channels
            ['SWS-Triage-Dashboard', 'SWS-Crisis-Team', 'SWS-Security'].forEach(channel => {
                Echo.channel(channel)
                    .listen('crisis.alert', (e) => {
                        showCrisisBanner(e);
                    });
            });
        }
    });

    function showCrisisBanner(data) {
        // Remove existing banner
        const existing = document.getElementById('crisis-alert-banner');
        if (existing) existing.remove();

        const banner = document.createElement('div');
        banner.id = 'crisis-alert-banner';
        banner.className = 'fixed top-0 left-0 right-0 z-50 bg-cput-emergency text-white shadow-cput-lg animate-slide-down';
        banner.setAttribute('role', 'alert');
        banner.setAttribute('aria-live', 'assertive');
        
        const patient = data.patient || {};
        const crisis = data.crisis || {};
        const contacts = data.emergency_contacts || {};
        const actions = data.recommended_actions || [];

        banner.innerHTML = `
            <div class="max-w-7xl mx-auto px-4 py-3">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 mt-0.5">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.765 1.36-1.234 2.66-2.722 2.66H5.4c-1.488 0-3.487-1.3-2.722-2.66l5.58-9.92zM10 13a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0v-2.25A.75.75 0 0110 13zM10 7a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 7z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold">${crisis.reasons ? crisis.reasons.join('; ') : 'Crisis Detected'}</h3>
                        <p class="text-sm mt-1">Patient: ${patient.name || 'Unknown'} (${patient.student_number || 'N/A'}) | Severity: ${crisis.severity || 'Critical'}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            ${contacts.sws_crisis_line ? `<a href="tel:${contacts.sws_crisis_line.replace(/\s/g, '')}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold bg-white text-cput-emergency rounded hover:bg-slate-100">SWS Crisis: ${contacts.sws_crisis_line}</a>` : ''}
                            ${contacts.uct_careline ? `<a href="tel:${contacts.uct_careline.replace(/\s/g, '')}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold bg-white text-cput-emergency rounded hover:bg-slate-100">Careline: ${contacts.uct_careline}</a>` : ''}
                            ${contacts.campus_protection ? `<a href="tel:${contacts.campus_protection.replace(/\s/g, '')}" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold bg-white text-cput-emergency rounded hover:bg-slate-100">Campus Protection: ${contacts.campus_protection}</a>` : ''}
                        </div>
                    </div>
                    <button onclick="this.closest('#crisis-alert-banner').remove()" class="text-white/80 hover:text-white"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
            </div>
        `;
        document.body.prepend(banner);
        
        // Auto-dismiss after 60 seconds for non-critical
        if (crisis.severity !== 'critical') {
            setTimeout(() => banner.remove(), 60000);
        }
    }
</script>
<style>
    @keyframes slide-down {
        from { transform: translateY(-100%); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .animate-slide-down { animation: slide-down 0.3s ease-out; }
</style>
@endif