<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ClipForge AI' }}</title>
    
    <!-- Fonts from gamification template -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Level Gamification Design Tokens */
            --stage: #0e0d0c;
            --stage-2: #1a1714;
            --paper: #ffffff;
            --ink: #1a1714;
            --muted: #6c6660;
            --line: #ebe6dd;
            
            --accent: #e98425;
            --accent-2: #ff6b3d;
            
            --tile-1: #ffe9bf;
            --tile-2: #ffe1d9;
            --tile-3: #f3e6ff;
            --tile-4: #d2eecb;
            --tile-5: #d6e7ff;
            --tile-6: #ffd6f1;
            
            --serif: 'Instrument Serif', 'Iowan Old Style', Georgia, serif;
            --sans: 'Inter', -apple-system, system-ui, sans-serif;
            --mono: 'IBM Plex Mono', ui-monospace, monospace;
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
                radial-gradient(ellipse 80% 50% at 50% -10%, rgba(233,132,37,0.18), transparent 70%),
                radial-gradient(ellipse 70% 50% at 50% 110%, rgba(255,255,255,0.04), transparent 70%),
                var(--stage);
            color: #f5efe4;
            font: 14px/1.5 var(--sans);
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
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
            color: rgba(245,239,228,0.5);
            letter-spacing: 0.2em;
            text-transform: uppercase;
            border-bottom: 1px dashed rgba(245,239,228,0.15);
            position: sticky;
            top: 0;
            background: rgba(14,13,12,0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            z-index: 100;
        }

        .brand {
            font-family: var(--serif);
            font-style: italic;
            font-size: 26px;
            color: #f5efe4;
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
            color: rgba(245,239,228,0.5);
            letter-spacing: 0.15em;
            text-transform: uppercase;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        .nav a:hover {
            color: #f5efe4;
            background: rgba(255,255,255,0.05);
        }
        .nav a.active {
            color: var(--stage);
            background: #f5efe4;
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
            color: #f5efe4;
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
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            border: 1px solid var(--line);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
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
            color: #f5efe4;
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
            color: #f5efe4;
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
            color: var(--ink);
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
            color: var(--muted);
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
            color: var(--ink);
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            animation: slideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .flash-error {
            background: var(--tile-2);
            color: var(--ink);
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
            background: #ffffff;
            border: 1.5px solid var(--line);
            color: var(--ink);
            font: 13.5px var(--sans);
            transition: all 0.2s ease;
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
        @keyframes sp {
            to { transform: rotate(360deg); }
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
        </nav>
    </div>

    <!-- Main Container -->
    <main class="container">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
