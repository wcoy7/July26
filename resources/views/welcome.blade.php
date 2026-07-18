<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <!-- Leaflet Map CSS and JS -->
        <link rel="stylesheet" href="{{ asset('css/leaflet.css') }}" />
        <script src="{{ asset('js/leaflet.js') }}"></script>

        <style>
            .leaflet-container {
                font-family: inherit;
            }
        </style>
    </head>
    <body class="min-h-screen bg-slate-50 dark:bg-zinc-950 flex flex-col justify-center items-center p-4 nativephp-safe-area">
        <!-- Floating Login Link in corner -->
        <div class="absolute top-4 right-4 z-50">
            @if (Route::has('login'))
                <nav class="flex items-center gap-4">
                    @auth
                        <flux:button href="{{ route('dashboard') }}" variant="filled">Dashboard</flux:button>
                    @else
                        <flux:button href="{{ route('login') }}" variant="filled">Log in</flux:button>
                        @if (Route::has('register'))
                            <flux:button href="{{ route('register') }}" variant="filled">Register</flux:button>
                        @endif
                    @endauth
                </nav>
            @endif
        </div>

        <livewire:time-clock />

        @fluxScripts
    </body>
</html>
