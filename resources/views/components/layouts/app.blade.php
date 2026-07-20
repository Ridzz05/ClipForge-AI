<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ClipForge AI - Purple Admin Edition' }}</title>
    
    <!-- Theme Flash Prevention -->
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Outfit:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Phosphor Icons CDN -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            /* Exact Purple Admin Design Tokens */
            --font-sans: 'Plus Jakarta Sans', -apple-system, system-ui, sans-serif;
            --font-title: 'Outfit', 'Plus Jakarta Sans', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            
            --purple-primary: #9a55ff;
            --purple-light: #da8cff;
            --purple-gradient: linear-gradient(to right, #da8cff, #9a55ff);
            --coral-gradient: linear-gradient(to right, #ffbf96, #fe7096);
            --blue-gradient: linear-gradient(to right, #90caf9, #047edf);
            --teal-gradient: linear-gradient(to right, #84d9d2, #07cdae);
            
            /* Light theme values (Default Reference: Purple Admin) */
            --bg-canvas: #f2f4f9;
            --bg-surface: #ffffff;
            --bg-surface-subtle: #f8fafc;
            --text-main: #343a40;
            --text-muted: #9c9fa6;
            --text-title: #2a2b36;
            --border-color: #ebedf2;
            --border-purple: rgba(154, 85, 255, 0.2);
            --card-shadow: 0 4px 18px rgba(0, 0, 0, 0.05);
            --header-bg: #ffffff;
            --sidebar-bg: #ffffff;
            --sidebar-active-bg: linear-gradient(to right, #da8cff, #9a55ff);
            --sidebar-active-color: #ffffff;
        }

        [data-theme="dark"] {
            /* Dark theme values */
            --bg-canvas: #0f0a1c;
            --bg-surface: #181128;
            --bg-surface-subtle: #211738;
            --text-main: #e2d9f3;
            --text-muted: #9f94b8;
            --text-title: #ffffff;
            --border-color: #2b1f44;
            --border-purple: rgba(218, 140, 255, 0.25);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            --header-bg: #181128;
            --sidebar-bg: #181128;
            --sidebar-active-bg: linear-gradient(to right, #da8cff, #9a55ff);
            --sidebar-active-color: #ffffff;
        }

        * {
            box-sizing: border-box;
            scrollbar-width: thin;
            scrollbar-color: rgba(154, 85, 255, 0.3) transparent;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg-canvas);
            color: var(--text-main);
            font: 400 14px/1.6 var(--font-sans);
            -webkit-font-smoothing: antialiased;
            transition: background 0.3s ease, color 0.3s ease;
        }

        a {
            color: var(--purple-primary);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        a:hover {
            color: var(--purple-light);
        }

        /* Full App Frame Container */
        .purple-app-frame {
            width: 100%;
            min-height: 100vh;
            background: var(--bg-canvas);
            display: flex;
            flex-direction: column;
        }

        /* Purple Admin Header Bar (Top Navbar) */
        .header-bar {
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            background: var(--header-bg);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .brand-logo {
            font-family: var(--font-title);
            font-size: 22px;
            font-weight: 800;
            color: var(--text-title);
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.01em;
            width: 200px;
            flex-shrink: 0;
        }

        .brand-logo i {
            font-size: 26px;
            background: var(--purple-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Search Bar Component Fix (Specificity & Alignment) */
        .header-search {
            position: relative;
            width: 320px;
            display: flex;
            align-items: center;
        }

        .header-search i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
            pointer-events: none;
            z-index: 5;
        }

        .header-search input[type="text"],
        .header-search input {
            width: 100% !important;
            height: 38px !important;
            padding: 8px 16px 8px 40px !important;
            border-radius: 99px !important;
            background: var(--bg-surface-subtle) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-main) !important;
            font-size: 13px !important;
            font-family: var(--font-sans) !important;
            transition: all 0.2s ease !important;
            box-shadow: none !important;
        }

        .header-search input[type="text"]:focus,
        .header-search input:focus {
            outline: none !important;
            background: var(--bg-surface) !important;
            border-color: var(--purple-primary) !important;
            box-shadow: 0 0 0 3px rgba(154, 85, 255, 0.15) !important;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Nav Action Icons */
        .header-icons {
            display: flex;
            align-items: center;
            gap: 14px;
            color: var(--text-muted);
        }

        .header-icon-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 18px;
            cursor: pointer;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .header-icon-btn:hover {
            color: var(--purple-primary);
            background: rgba(154, 85, 255, 0.08);
        }

        .header-icon-btn .notification-dot {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #fe7096;
            border: 1.5px solid var(--header-bg);
        }

        /* User Profile in Header */
        .header-user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 4px 10px;
            border-radius: 99px;
            background: var(--bg-surface-subtle);
            border: 1px solid var(--border-color);
            transition: background 0.2s ease;
        }

        .header-user-profile:hover {
            border-color: var(--purple-primary);
        }

        .user-avatar-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--purple-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(154, 85, 255, 0.3);
        }

        .user-info-text {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .user-name-text {
            font-weight: 700;
            font-size: 12.5px;
            color: var(--text-title);
            line-height: 1.2;
        }

        .user-role-text {
            font-size: 10.5px;
            color: var(--text-muted);
        }

        /* App Layout Wrapper */
        .app-body {
            display: flex;
            flex-grow: 1;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 240px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            flex-shrink: 0;
        }

        .sidebar-section-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 0 12px;
            margin-bottom: 8px;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 4px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-item a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 16px;
            border-radius: 10px;
            color: var(--text-main);
            font-weight: 600;
            font-size: 13.5px;
            transition: all 0.2s ease;
        }

        .sidebar-item a i {
            font-size: 18px;
            color: var(--text-muted);
            transition: color 0.2s ease;
        }

        .sidebar-item a:hover {
            background: rgba(154, 85, 255, 0.08);
            color: var(--purple-primary);
        }

        .sidebar-item a:hover i {
            color: var(--purple-primary);
        }

        .sidebar-item a.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-color) !important;
            box-shadow: 0 4px 14px rgba(154, 85, 255, 0.3);
        }

        .sidebar-item a.active i {
            color: #ffffff !important;
        }

        /* Main Workspace Area */
        .main-content {
            flex-grow: 1;
            padding: 32px 36px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* Titles & Headers */
        .page-title {
            font-family: var(--font-title);
            font-size: 26px;
            font-weight: 800;
            color: var(--text-title);
            margin: 0;
            letter-spacing: -0.02em;
        }

        .page-sub {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Panels & Cards */
        .panel {
            background: var(--bg-surface);
            border-radius: 18px;
            padding: 24px;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        /* Stat Cards with Gradient Fills (Purple Admin Exact Match) */
        .stat-card {
            border-radius: 16px;
            padding: 24px 26px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 140px;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            right: -25px;
            bottom: -25px;
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.18);
            pointer-events: none;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            right: 25px;
            bottom: -45px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.12);
            pointer-events: none;
        }

        .stat-card.card-coral { background: var(--coral-gradient); }
        .stat-card.card-blue { background: var(--blue-gradient); }
        .stat-card.card-teal { background: var(--teal-gradient); }

        .stat-card .card-title {
            font-size: 14.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-card .card-title i { font-size: 22px; opacity: 0.9; }

        .stat-card .card-value {
            font-family: var(--font-title);
            font-size: 32px;
            font-weight: 800;
            margin: 10px 0 2px;
            letter-spacing: -0.02em;
        }

        .stat-card .card-subtitle {
            font-size: 12px;
            opacity: 0.9;
            font-weight: 500;
        }

        /* Purple Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            background: var(--purple-gradient);
            color: #ffffff;
            padding: 10px 22px;
            border-radius: 8px;
            font: 700 13px/1 var(--font-sans);
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(154, 85, 255, 0.3);
        }

        .btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(154, 85, 255, 0.4);
            color: #ffffff;
        }

        .btn-outline {
            background: transparent;
            border: 1.5px solid var(--border-color);
            color: var(--text-title);
            box-shadow: none;
        }

        .btn-outline:hover:not(:disabled) {
            background: rgba(154, 85, 255, 0.08);
            border-color: var(--purple-primary);
            color: var(--purple-primary);
        }

        .btn-sm {
            padding: 7px 14px;
            font-size: 12px;
            border-radius: 6px;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 6px;
            font: 700 11px/1 var(--font-mono);
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .badge-purple { background: rgba(154, 85, 255, 0.12); color: var(--purple-primary); }
        .badge-green { background: rgba(16, 185, 129, 0.12); color: #10b981; }
        .badge-amber { background: rgba(245, 158, 11, 0.12); color: #f59e0b; }
        .badge-red { background: rgba(239, 68, 68, 0.12); color: #ef4444; }

        /* General Inputs */
        input[type=text], input[type=url] {
            outline: none;
            width: 100%;
            padding: 11px 16px;
            border-radius: 10px;
            background: var(--bg-surface-subtle);
            border: 1.5px solid var(--border-color);
            color: var(--text-main);
            font: 500 13.5px var(--font-sans);
            transition: all 0.2s ease;
        }

        input[type=text]:focus, input[type=url]:focus {
            border-color: var(--purple-primary);
            box-shadow: 0 0 0 3px rgba(154, 85, 255, 0.15);
        }

        /* Tables */
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 14px 12px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { font: 700 11px/1 var(--font-mono); color: var(--text-muted); text-transform: uppercase; border-bottom: 2px solid var(--border-color); }
        tr:hover td { background: rgba(154, 85, 255, 0.03); }

        .grid { display: grid; gap: 24px; }
        .row { display: flex; align-items: center; gap: 12px; }
        .between { justify-content: space-between; }

        .spin-rotate { display: inline-block; animation: spin 0.9s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Mobile Bottom Nav */
        .bottom-nav { display: none; }

        @media (max-width: 968px) {
            .sidebar { display: none; }
            .main-content { padding: 20px 16px 100px; }
            .header-search { display: none; }
            .header-bar { padding: 0 16px; }

            .bottom-nav {
                display: flex;
                justify-content: space-around;
                align-items: center;
                position: fixed;
                bottom: 12px;
                left: 12px;
                right: 12px;
                height: 60px;
                background: var(--bg-surface);
                border: 1px solid var(--border-purple);
                border-radius: 16px;
                backdrop-filter: blur(16px);
                z-index: 999;
                box-shadow: 0 10px 30px rgba(154, 85, 255, 0.2);
            }
            .bottom-nav .nav-item { display: flex; flex-direction: column; align-items: center; gap: 2px; color: var(--text-muted); font-size: 10px; font-weight: 700; }
            .bottom-nav .nav-item.active { color: var(--purple-primary); }
        }
    </style>
    @livewireStyles
</head>
<body>
    <!-- Global Toast Container -->
    <div id="toast-container" style="position: fixed; top: 24px; right: 24px; z-index: 99999; display: flex; flex-direction: column; gap: 12px; pointer-events: none;"></div>

    <!-- App Main Frame -->
    <div class="purple-app-frame">
        <!-- Top Navbar Header (Purple Admin Spec) -->
        <header class="header-bar">
            <div class="header-left">
                <a href="/" wire:navigate class="brand-logo">
                    <i class="ph ph-intersect"></i>
                    ClipForge <span style="color: var(--purple-primary);">AI</span>
                </a>

                <!-- Search Component (Proportional & Padded) -->
                <div class="header-search">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" placeholder="Cari video atau klip..." readonly>
                </div>
            </div>

            <div class="header-right">
                <div class="header-icons">
                    <!-- Theme Switcher -->
                    <button id="theme-toggle" class="header-icon-btn" onclick="toggleTheme()" title="Ganti Mode Tema (Terang / Gelap)">
                        <i class="ph ph-sun" id="theme-icon-light" style="display: none;"></i>
                        <i class="ph ph-moon" id="theme-icon-dark" style="display: none;"></i>
                    </button>

                    <button class="header-icon-btn" title="Grid View">
                        <i class="ph ph-squares-four"></i>
                    </button>

                    <button class="header-icon-btn" title="Notifications">
                        <i class="ph ph-bell"></i>
                        <span class="notification-dot"></span>
                    </button>
                </div>

                <!-- User Profile Badge -->
                <div class="header-user-profile">
                    <div class="user-avatar-img">CF</div>
                    <div class="user-info-text">
                        <span class="user-name-text">ClipForge Admin</span>
                        <span class="user-role-text">Self-Hosted Operator</span>
                    </div>
                    <i class="ph ph-caret-down" style="font-size: 12px; color: var(--text-muted); margin-left: 2px;"></i>
                </div>
            </div>
        </header>

        <!-- App Layout with Sidebar -->
        <div class="app-body">
            <!-- Left Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-section-title">MAIN NAVIGATION</div>
                <ul class="sidebar-menu">
                    @php $r = request()->path(); @endphp
                    <li class="sidebar-item">
                        <a href="/" wire:navigate class="{{ $r === '/' || $r === '' ? 'active' : '' }}">
                            <i class="ph ph-house"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="sidebar-item">
                        <a href="/exports" wire:navigate class="{{ str_starts_with($r, 'exports') ? 'active' : '' }}">
                            <i class="ph ph-film-strip"></i>
                            <span>Exports & Klip</span>
                        </a>
                    </li>
                </ul>

                <div style="margin-top: auto; padding: 14px; background: var(--bg-surface-subtle); border-radius: 12px; border: 1px solid var(--border-color); font-size: 12px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px;">
                        <span style="color: var(--text-muted); font-weight: 600;">Engine:</span>
                        <span class="badge badge-purple" style="padding: 2px 6px; font-size: 10px;">Laravel 13</span>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="color: var(--text-muted); font-weight: 600;">AI Pipeline:</span>
                        <span style="color: #10b981; font-weight: 700;">● Active</span>
                    </div>
                </div>
            </aside>

            <!-- Main Content Area -->
            <main class="main-content">
                {{ $slot }}
            </main>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="bottom-nav">
        @php $r = request()->path(); @endphp
        <a href="/" wire:navigate class="nav-item {{ $r === '/' || $r === '' ? 'active' : '' }}">
            <i class="ph ph-house"></i>
            <span>Dashboard</span>
        </a>
        <a href="/exports" wire:navigate class="nav-item {{ str_starts_with($r, 'exports') ? 'active' : '' }}">
            <i class="ph ph-film-strip"></i>
            <span>Exports</span>
        </a>
    </div>



    @livewireScripts

    <!-- Theme Controller Script -->
    <script>
        function scrollToUploadSection(e) {
            if (e) e.preventDefault();
            if (window.location.pathname !== '/') {
                window.location.href = '/#upload-section';
            } else {
                const el = document.getElementById('upload-section');
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }

        function getTheme() {

            return localStorage.getItem('theme') || 'light';
        }

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            const lightIcon = document.getElementById('theme-icon-light');
            const darkIcon = document.getElementById('theme-icon-dark');
            if (theme === 'dark') {
                if (lightIcon) lightIcon.style.display = 'inline-block';
                if (darkIcon) darkIcon.style.display = 'none';
            } else {
                if (lightIcon) lightIcon.style.display = 'none';
                if (darkIcon) darkIcon.style.display = 'inline-block';
            }
        }

        function toggleTheme() {
            const current = getTheme();
            const next = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', next);
            applyTheme(next);
        }

        applyTheme(getTheme());

        // Toast System
        if (!window.toastListenerRegistered) {
            window.addEventListener('toast', (event) => {
                const container = document.getElementById('toast-container');
                if (!container) return;

                const toast = document.createElement('div');
                toast.style.pointerEvents = 'auto';
                toast.style.background = 'var(--bg-surface)';
                toast.style.border = '1px solid var(--border-purple)';
                toast.style.borderRadius = '14px';
                toast.style.padding = '12px 18px';
                toast.style.boxShadow = '0 10px 30px rgba(154, 85, 255, 0.2)';
                toast.style.color = 'var(--text-main)';
                toast.style.font = '600 13px/1.4 var(--font-sans)';
                toast.style.display = 'flex';
                toast.style.alignItems = 'center';
                toast.style.gap = '10px';
                toast.style.maxWidth = '360px';

                const type = event.detail.type || 'info';
                let icon = 'ph-info';
                let color = 'var(--purple-primary)';
                if (type === 'success') { icon = 'ph-check-circle'; color = '#10b981'; }
                else if (type === 'error') { icon = 'ph-warning-octagon'; color = '#ef4444'; }

                toast.innerHTML = `
                    <i class="ph ${icon}" style="font-size: 20px; color: ${color}; flex-shrink: 0;"></i>
                    <div style="flex-grow: 1;">${event.detail.message}</div>
                `;

                container.appendChild(toast);
                setTimeout(() => { toast.remove(); }, 4000);
            });
            window.toastListenerRegistered = true;
        }

        @if(session()->has('flash') || session()->has('success'))
            window.dispatchEvent(new CustomEvent('toast', {
                detail: { message: "{{ session('flash') ?? session('success') }}", type: 'success' }
            }));
        @endif
    </script>
</body>
</html>
