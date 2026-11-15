<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? 'ClipForge' }}</title>

    <link rel="stylesheet" href="{{ asset('css/clipforge.css') }}?v={{ filemtime(public_path('css/clipforge.css')) }}">
    @livewireStyles
</head>
<body>
    @php
        $path = request()->path();
        $groups = [
            'Kerja' => [
                ['href' => '/', 'label' => 'Dashboard', 'icon' => 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10', 'on' => $path === '/' || str_starts_with($path, 'videos/') || str_starts_with($path, 'candidates/')],
                ['href' => '/projects', 'label' => 'Proyek', 'icon' => 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z', 'on' => str_starts_with($path, 'projects')],
                ['href' => '/exports', 'label' => 'Exports', 'icon' => 'M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2', 'on' => str_starts_with($path, 'exports')],
            ],
            'Publikasi' => [
                ['href' => '/calendar', 'label' => 'Kalender', 'icon' => 'M4 6a2 2 0 012-2h12a2 2 0 012 2v13a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM4 10h16M8 2v4m8-4v4', 'on' => str_starts_with($path, 'calendar')],
            ],
            'Pustaka' => [
                ['href' => '/brand', 'label' => 'Brand kit', 'icon' => 'M12 3a9 9 0 100 18c1.2 0 2-.8 2-1.8 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-1 .8-1.8 1.8-1.8H17a4 4 0 004-4c0-4.4-4-8-9-8zM7.5 11h.01M10 7.5h.01M14 7.5h.01', 'on' => str_starts_with($path, 'brand')],
                ['href' => '/assets', 'label' => 'Aset', 'icon' => 'M4 5a2 2 0 012-2h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5zM4 16l4-4 3 3 4-5 5 6M9 8h.01', 'on' => str_starts_with($path, 'assets')],
            ],
        ];
    @endphp

    <a href="#main" class="skip-link">Lewati ke konten</a>

    <div class="shell">
        <aside id="sidebar" class="sidebar" aria-label="Menu utama">
            <a href="/" wire:navigate class="wordmark">
                <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 6.5c6-2.2 17-2 24 0.4 .8 7.3 .6 13.6-.5 19.2-7.2 2-15.6 1.8-23-.3C3.4 19.8 3.3 13 4 6.5z"/>
                    <path d="M13 11.5l8 4.7-8.2 4.6z" fill="currentColor"/>
                </svg>
                <span>ClipForge</span>
            </a>

            <a href="/" wire:navigate class="btn btn-block side-cta">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Video baru
            </a>

            <nav class="side-nav">
                @foreach($groups as $label => $items)
                    <div class="side-group">
                        <div class="side-label">{{ $label }}</div>
                        @foreach($items as $item)
                            <a href="{{ $item['href'] }}" wire:navigate class="side-link" @if($item['on']) aria-current="page" @endif>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $item['icon'] }}"/></svg>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
        </aside>

        <div class="shell-body">
            <header class="mobilebar">
                <button type="button" class="btn btn-secondary btn-sm" data-nav-toggle aria-controls="sidebar" aria-expanded="false" aria-label="Buka menu">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    Menu
                </button>
                <a href="/" wire:navigate class="wordmark"><span>ClipForge</span></a>
            </header>

            <main id="main" class="main" tabindex="-1">
                {{ $slot }}
            </main>
        </div>
    </div>
    <div class="side-scrim" data-nav-close></div>

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

        // Sidebar drawer (<= 900px). Delegated on document so it survives wire:navigate.
        if (!window.sidebarListenerRegistered) {
            var setNav = function (open) {
                document.body.classList.toggle('nav-open', open);
                var btn = document.querySelector('[data-nav-toggle]');
                if (btn) {
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    btn.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
                }
            };
            document.addEventListener('click', function (event) {
                if (event.target.closest('[data-nav-toggle]')) {
                    setNav(!document.body.classList.contains('nav-open'));
                } else if (event.target.closest('[data-nav-close]') || event.target.closest('.side-link, .side-cta')) {
                    setNav(false);
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && document.body.classList.contains('nav-open')) {
                    setNav(false);
                    var btn = document.querySelector('[data-nav-toggle]');
                    if (btn) btn.focus();
                }
            });
            document.addEventListener('livewire:navigated', function () { setNav(false); });
            window.sidebarListenerRegistered = true;
        }

        @if(session()->has('flash') || session()->has('success'))
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { message: @json(session('flash') ?? session('success')), type: 'success' }
            }));
        @endif
    </script>
</body>
</html>
