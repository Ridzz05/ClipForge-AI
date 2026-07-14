<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ClipForge AI' }}</title>
    
    <!-- Theme Flash Prevention -->
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

    <!-- Phosphor Icons CDN -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            /* Fonts */
            --serif: 'Instrument Serif', 'Iowan Old Style', Georgia, serif;
            --sans: 'Inter', -apple-system, system-ui, sans-serif;
            --mono: 'IBM Plex Mono', ui-monospace, monospace;
            
            --accent: #e98425;
            --accent-2: #ff6b3d;
            
            --tile-1: #ffe9bf;
            --tile-2: #ffe1d9;
            --tile-3: #f3e6ff;
            --tile-4: #d2eecb;
            --tile-5: #d6e7ff;
            --tile-6: #ffd6f1;
            
            /* Light theme values (Default) */
            --stage: #f7f5f0;
            --stage-2: #ffffff;
            --paper: #ffffff;
            --ink: #1a1714;
            --muted: #706a64;
            --line: #ebe6dd;
            --text-body: #1a1714;
            --text-title: #1a1714;
            --border-stage: rgba(26,23,20,0.08);
            --radial-glow: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(233,132,37,0.06), transparent 70%);
        }

        [data-theme="dark"] {
            /* Dark theme values */
            --stage: #0e0d0c;
            --stage-2: #1a1714;
            --paper: #1a1714;
            --ink: #f5efe4;
            --muted: #a39c96;
            --line: #2e2a26;
            --text-body: #ebd9c5;
            --text-title: #f5efe4;
            --border-stage: rgba(245,239,228,0.15);
            --radial-glow: radial-gradient(ellipse 80% 50% at 50% -10%, rgba(233,132,37,0.18), transparent 70%);
        }

        * {
            box-sizing: border-box;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                var(--radial-glow),
                radial-gradient(ellipse 70% 50% at 50% 110%, rgba(255,255,255,0.02), transparent 70%),
                var(--stage);
            color: var(--text-body);
            font: 14px/1.5 var(--sans);
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            transition: background 0.3s ease, color 0.3s ease;
        }

        a {
            color: var(--accent);
            text-decoration: none;
            transition: color 0.15s ease;
        }
        a:hover {
            color: var(--accent-2);
        }

        /* Top Bar styled after stage-bar */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 36px;
            font: 11px/1 var(--mono);
            color: var(--muted);
            letter-spacing: 0.2em;
            text-transform: uppercase;
            border-bottom: 1px dashed var(--border-stage);
            position: sticky;
            top: 0;
            background: var(--stage);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 100;
            transition: background 0.3s ease, border 0.3s ease;
        }

        .brand {
            font-family: var(--serif);
            font-style: italic;
            font-size: 26px;
            color: var(--text-title);
            letter-spacing: 0;
            text-transform: none;
            display: flex;
            align-items: center;
            gap: 2px;
            cursor: pointer;
        }
        .brand span {
            color: var(--accent);
        }

        .nav {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .nav a {
            font: 11px/1 var(--mono);
            color: var(--muted);
            letter-spacing: 0.15em;
            text-transform: uppercase;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .nav a:hover {
            color: var(--text-title);
            background: rgba(255,255,255,0.1);
        }
        .nav a.active {
            color: var(--stage);
            background: var(--text-title);
            font-weight: 600;
        }

        /* Container Layout */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 40px 24px 80px;
        }

        .page-title {
            font: italic 700 44px/1.1 var(--serif);
            color: var(--text-title);
            margin: 0 0 8px;
            letter-spacing: -0.01em;
        }

        .page-sub {
            font-size: 14.5px;
            color: var(--muted);
            margin: 0 0 32px;
            max-width: 65ch;
            line-height: 1.5;
        }

        /* Level Gamification Cards (Panels) */
        .panel {
            background: var(--paper);
            color: var(--ink);
            border-radius: 24px;
            padding: 28px 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.06);
            border: 1px solid var(--line);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.3s ease, border 0.3s ease;
        }

        /* Buttons matching the start/badge button */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: 1.5px solid var(--ink);
            background: transparent;
            color: var(--ink);
            padding: 12px 24px;
            border-radius: 999px;
            font: 600 13px/1 var(--sans);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .btn:hover:not(:disabled) {
            background: var(--ink);
            color: var(--paper);
            transform: translateY(-1px);
        }

        .btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .btn-primary {
            background: var(--ink);
            color: var(--paper);
            border-color: var(--ink);
        }
        .btn-primary:hover:not(:disabled) {
            background: transparent;
            color: var(--ink);
        }

        .btn-outline {
            border: 1.5px solid var(--ink);
            background: transparent;
            color: var(--ink);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 11px;
            letter-spacing: 0.04em;
        }

        /* Gamification Pastel Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font: 700 10.5px/1 var(--mono);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #1a1714 !important; /* Pastel badges always use dark text */
            border: 1px solid rgba(0,0,0,0.08);
        }

        .badge-green {
            background: var(--tile-4);
        }
        
        .badge-amber {
            background: var(--tile-1);
        }
        
        .badge-blue {
            background: var(--tile-5);
        }
        
        .badge-red {
            background: var(--tile-2);
        }
        
        .badge-gray {
            background: var(--line);
            color: var(--muted) !important;
        }

        /* Premium Gamified Table Style */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th, td {
            text-align: left;
            padding: 16px 12px;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }

        th {
            font: 700 11px/1 var(--mono);
            color: var(--muted);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            border-bottom: 2px solid var(--ink);
            padding-bottom: 12px;
        }

        tr {
            transition: background-color 0.15s ease;
        }
        tr:hover td {
            background: rgba(0,0,0,0.015);
        }
        tr:last-child td {
            border-bottom: none;
        }

        .muted {
            color: var(--muted);
        }

        .empty {
            text-align: center;
            padding: 50px 20px;
            color: var(--muted);
            font: 13.5px var(--sans);
            background: rgba(0,0,0,0.01);
            border: 1.5px dashed var(--line);
            border-radius: 18px;
            margin: 12px 0;
        }

        /* Notifications & Messages */
        .flash {
            padding: 14px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            background: var(--tile-4);
            border: 1px solid rgba(0,0,0,0.06);
            color: #1a1714;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            animation: slideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .flash-error {
            background: var(--tile-2);
            color: #1a1714;
        }

        @keyframes slideDown {
            from { transform: translateY(-8px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .grid {
            display: grid;
            gap: 20px;
        }

        .row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .between {
            justify-content: space-between;
        }

        /* Input overrides */
        input[type=text], input[type=url] {
            outline: none;
            width: 100%;
            padding: 14px 18px;
            border-radius: 14px;
            background: var(--paper);
            border: 1.5px solid var(--line);
            color: var(--ink);
            font: 13.5px var(--sans);
            transition: all 0.2s ease, border 0.3s ease;
        }

        input[type=text]:focus, input[type=url]:focus {
            border-color: var(--ink);
            box-shadow: 0 0 0 3px rgba(26, 23, 20, 0.08);
        }

        .spin {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: sp 0.8s linear infinite;
        }
        .spin-rotate {
            display: inline-block;
            animation: sp 0.8s linear infinite;
        }
        @keyframes sp {
            to { transform: rotate(360deg); }
        }

        /* Theme button style */
        .theme-btn {
            background: transparent;
            border: 1.5px solid var(--border-stage);
            cursor: pointer;
            color: var(--text-title);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }
        .theme-btn:hover {
            border-color: var(--text-title);
            background: rgba(255,255,255,0.1);
        }
        
        .theme-btn i {
            font-size: 16px;
        }

        /* Livewire activity-feed styles fallback */
        .timeline-item {
            border-left: 1.5px dashed var(--line);
            padding-left: 20px;
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -5.5px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--ink);
        }
    </style>
    @livewireStyles
</head>
<body>
    <!-- Top Bar Styled after stage-bar -->
    <div class="topbar">
        <span>CLIPFORGE AI · COGNITIVE HIGHLIGHTER</span>
        <div class="brand" onclick="window.location.href='/'">clipforge<span>.</span></div>
        <nav class="nav">
            @php $r = request()->path(); @endphp
            <a href="/" class="{{ $r === '/' || $r === '' ? 'active' : '' }}">Dashboard</a>
            <a href="/exports" class="{{ str_starts_with($r, 'exports') ? 'active' : '' }}">Exports</a>
            
            <!-- Theme Toggle Button -->
            <button id="theme-toggle" class="theme-btn" onclick="toggleTheme()" title="Ganti Tema">
                <i class="ph ph-sun" id="theme-icon-light" style="display: none;"></i>
                <i class="ph ph-moon" id="theme-icon-dark" style="display: none;"></i>
            </button>
        </nav>
    </div>

    <!-- Main Container -->
    <main class="container">
        {{ $slot }}
    </main>

    @livewireScripts
    
    <!-- Theme Toggle Controller -->
    <script>
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

        // Apply theme variables immediately
        applyTheme(getTheme());
    </script>
</body>
</html>
