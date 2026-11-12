<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? 'ClipForge' }}</title>

    <link rel="preload" href="{{ asset('fonts/PatrickHand-Regular.ttf') }}" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/clipforge.css') }}?v={{ filemtime(public_path('css/clipforge.css')) }}">
    @livewireStyles
</head>
<body>
    @php
        $path = request()->path();
        $nav = [
            ['href' => '/', 'label' => 'Dashboard', 'on' => $path === '/' || str_starts_with($path, 'videos/') || str_starts_with($path, 'candidates/')],
            ['href' => '/exports', 'label' => 'Exports', 'on' => str_starts_with($path, 'exports')],
            ['href' => '/calendar', 'label' => 'Kalender', 'on' => str_starts_with($path, 'calendar')],
            ['href' => '/brand', 'label' => 'Brand kit', 'on' => str_starts_with($path, 'brand')],
            ['href' => '/assets', 'label' => 'Aset', 'on' => str_starts_with($path, 'assets')],
        ];
    @endphp

    <a href="#main" class="skip-link">Lewati ke konten</a>

    <header class="topbar">
        <div class="topbar-start">
            <a href="/" wire:navigate class="wordmark">
                {{-- Hand-drawn scissors-and-play mark --}}
                <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 6.5c6-2.2 17-2 24 0.4 .8 7.3 .6 13.6-.5 19.2-7.2 2-15.6 1.8-23-.3C3.4 19.8 3.3 13 4 6.5z"/>
                    <path d="M13 11.5l8 4.7-8.2 4.6z" fill="currentColor"/>
                </svg>
                <span>ClipForge</span>
            </a>
            <nav class="nav" aria-label="Utama">
                @foreach($nav as $item)
                    <a href="{{ $item['href'] }}" wire:navigate @if($item['on']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main id="main" class="main" tabindex="-1">
        {{ $slot }}
    </main>

    <div id="toasts" class="toasts" role="status" aria-live="polite"></div>

    @livewireScripts

    <script>
        // Toast: textContent only, messages can carry exception text.
        if (!window.toastListenerRegistered) {
            window.addEventListener('toast', function (event) {
                var box = document.getElementById('toasts');
                if (!box) return;
                var el = document.createElement('div');
                el.className = 'toast';
                el.dataset.type = (event.detail && event.detail.type) || 'info';
                el.textContent = (event.detail && event.detail.message) || '';
                box.appendChild(el);
                setTimeout(function () { el.remove(); }, 5000);
            });
            window.toastListenerRegistered = true;
        }

        @if(session()->has('flash') || session()->has('success'))
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { message: @json(session('flash') ?? session('success')), type: 'success' }
            }));
        @endif
    </script>
</body>
</html>
