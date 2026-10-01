<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'CPUT Campus Health') }} | {{ $title ?? 'Student Wellness Services' }}</title>
    
    <!-- CPUT Brand Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @stack('styles')
</head>
<body class="h-full bg-cput-slate font-sans antialiased">
    <!-- Crisis Alert Banner (Global - Reverb driven) -->
    @include('partials.crisis-alert-banner')

    <div class="flex h-full flex-col">
        <!-- CPUT Navy Header -->
        <header class="bg-cput-navy text-white shadow-cput sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo & Brand -->
                    <div class="flex items-center gap-3">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2" aria-label="CPUT Campus Health Home">
                            <!-- CPUT Logo Placeholder -->
                            <svg class="w-8 h-8 text-cput-cyan" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                            </svg>
                            <div>
                                <span class="text-sm font-semibold tracking-wide">CPUT</span>
                                <span class="text-xs text-cput-cyan/80 block">Student Wellness Services</span>
                            </div>
                        </a>
                    </div>

                    <!-- Navigation (Desktop) -->
                    <nav class="hidden md:flex items-center gap-6" aria-label="Main navigation">
                        @auth
                            @if(auth()->user()->hasRole('student'))
                                <a href="{{ route('student.dashboard') }}" class="text-sm font-medium text-white/90 hover:text-cput-cyan transition-colors">Dashboard</a>
                                <a href="{{ route('student.appointments') }}" class="text-sm font-medium text-white/90 hover:text-cput-cyan transition-colors">Appointments</a>
                                <a href="{{ route('student.medications') }}" class="text-sm font-medium text-white/90 hover:text-cput-cyan transition-colors">Medications</a>
                                <a href="{{ route('student.profile') }}" class="text-sm font-medium text-white/90 hover:text-cput-cyan transition-colors">Profile</a>
                            @elseif(auth()->user()->hasAnyRole(['cnp', 'gp', 'psychologist', 'pharmacist', 'social_worker', 'admin']))
                                <a href="{{ route('clinical.dashboard') }}" class="text-sm font-medium text-white/90 hover:text-cput-cyan transition-colors">Clinical Dashboard</a>
                                <a href="{{ route('clinical.queue') }}" class="text-sm font-medium text-white/90 hover:text-cput-cyan transition-colors">Patient Queue</a>
                                <a href="{{ route('clinical.encounters') }}" class="text-sm font-medium text-white/90 hover:text-cput-cyan transition-colors">Encounters</a>
                                @if(auth()->user()->hasRole(['pharmacist', 'admin']))
                                    <a href="{{ route('pharmacy.queue') }}" class="text-sm font-medium text-white/90 hover:text-cput-cyan transition-colors">Pharmacy</a>
                                @endif
                            @endif
                        @endauth
                    </nav>

                    <!-- User Menu & Actions -->
                    <div class="flex items-center gap-4">
                        @auth
                            <!-- Emergency Quick Dial -->
                            <a href="tel:0800242526" class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 bg-cput-emergency text-white text-xs font-semibold rounded-full hover:bg-cput-emergency-light transition-colors" aria-label="Call UCT Careline 24/7">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <span>Careline</span>
                            </a>

                            <!-- User Dropdown -->
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-cput-navy-light transition-colors" aria-expanded="false" aria-haspopup="true">
                                    <div class="w-8 h-8 rounded-full bg-cput-blue flex items-center justify-center text-white font-medium">
                                        {{ Str::upper(auth()->user()->name[0]) }}
                                    </div>
                                    <span class="hidden sm:block text-sm font-medium">{{ auth()->user()->name }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>

                                <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-cput-lg border border-cput-slate-dark py-1 hidden" role="menu">
                                    <div class="px-4 py-2 border-b border-cput-slate-dark">
                                        <p class="text-xs text-slate-500">{{ auth()->user()->email }}</p>
                                        @if(auth()->user()->patient)
                                            <p class="text-xs text-cput-blue">{{ auth()->user()->patient->student_number }}</p>
                                        @endif
                                    </div>
                                    <a href="{{ route('profile') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-cput-slate" role="menuitem">Profile</a>
                                    <a href="{{ route('settings') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-cput-slate" role="menuitem">Settings</a>
                                    <hr class="my-1 border-cput-slate-dark">
                                    <form action="{{ route('logout') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-cput-emergency hover:bg-cput-slate" role="menuitem">Sign Out</button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <a href="{{ route('login') }}" class="px-4 py-2 text-sm font-medium text-white hover:text-cput-cyan transition-colors">Sign In</a>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-cput-slate-dark py-4">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-slate-500">
                <p>&copy; {{ date('Y') }} Cape Peninsula University of Technology. Student Wellness Services.</p>
                <div class="flex items-center gap-4">
                    <a href="tel:0216501271" class="hover:text-cput-blue transition-colors">SWS Crisis: 021 650 1271</a>
                    <a href="tel:0800242526" class="hover:text-cput-blue transition-colors">UCT Careline: 0800 24 25 26</a>
                    <a href="https://higherhealth.ac.za" target="_blank" rel="noopener" class="hover:text-cput-blue transition-colors">HIGHER HEALTH</a>
                </div>
            </div>
        </footer>
    </div>

    <!-- Alpine.js for dropdowns/modals -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    @stack('scripts')
    
    <!-- Reverb Echo Connection -->
    @if(config('broadcasting.default') === 'reverb')
        <script>
            window.Pusher = require('pusher-js');
            window.Echo = new Echo({
                broadcaster: 'reverb',
                key: '{{ config('broadcasting.connections.reverb.key') }}',
                wsHost: '{{ config('broadcasting.connections.reverb.options.host') }}',
                wsPort: {{ config('broadcasting.connections.reverb.options.port') }},
                wssPort: {{ config('broadcasting.connections.reverb.options.port') }},
                forceTLS: {{ config('broadcasting.connections.reverb.options.scheme') === 'https' ? 'true' : 'false' }},
                enabledTransports: ['ws', 'wss'],
            });
        </script>
    @endif
</body>
</html>