<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'ClipForge AI' }}</title>
    
    <!-- Modern Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Sleek Obsidian Dark Theme Palettes */
            --bg: #07080b;
            --panel: rgba(15, 17, 26, 0.7);
            --panel-solid: #0f111a;
            --panel-2: rgba(26, 29, 41, 0.5);
            --panel-2-solid: #1a1d29;
            --border: rgba(255, 255, 255, 0.06);
            --border-hover: rgba(129, 140, 248, 0.25);
            --text: #f3f4f6;
            --muted: #9ca3af;
            
            /* Curated Rich Gradients */
            --accent-start: #818cf8;
            --accent-end: #c084fc;
            --accent-gradient: linear-gradient(135deg, var(--accent-start) 0%, var(--accent-end) 100%);
            
            /* Action colors */
            --green: #10b981;
            --green-glow: rgba(16, 185, 129, 0.15);
            --amber: #f59e0b;
            --amber-glow: rgba(245, 158, 11, 0.15);
            --red: #ef4444;
            --red-glow: rgba(239, 68, 68, 0.15);
            --blue: #3b82f6;
            --blue-glow: rgba(59, 130, 246, 0.15);
            --gray: #6b7280;
            --gray-glow: rgba(107, 114, 128, 0.15);
            
            --radius: 16px;
            --radius-sm: 10px;
        }

        * {
            box-sizing: border-box;
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient Glow Blobs */
        body::before {
            content: "";
            position: absolute;
            width: 400px;
            height: 400px;
            top: -150px;
            left: -150px;
            background: radial-gradient(circle, rgba(129, 140, 248, 0.15) 0%, rgba(0,0,0,0) 70%);
            filter: blur(50px);
            z-index: -1;
            pointer-events: none;
        }

        body::after {
            content: "";
            position: absolute;
            width: 450px;
            height: 450px;
            top: 20%;
            right: -200px;
            background: radial-gradient(circle, rgba(192, 132, 252, 0.12) 0%, rgba(0,0,0,0) 70%);
            filter: blur(60px);
            z-index: -1;
            pointer-events: none;
        }

        a {
            color: var(--accent-start);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        a:hover {
            color: var(--accent-end);
        }

        /* Modern Glass Navigation */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 40px;
            background: rgba(15, 17, 26, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand {
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 20px;
            letter-spacing: -0.5px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .brand span {
            background: linear-gradient(135deg, #ffffff 30%, #a78bfa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav {
            display: flex;
            gap: 8px;
            background: rgba(255, 255, 255, 0.03);
            padding: 4px;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        
        .nav a {
            padding: 8px 18px;
            border-radius: 10px;
            color: var(--muted);
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .nav a:hover {
            color: var(--text);
            background: rgba(255, 255, 255, 0.05);
            text-decoration: none;
        }
        .nav a.active {
            background: var(--accent-gradient);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(129, 140, 248, 0.3);
        }

        /* Container & Grid layout */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 24px 60px;
        }
        
        .page-title {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 6px;
            letter-spacing: -0.5px;
            background: linear-gradient(to right, #ffffff, #d1d5db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .page-sub {
            color: var(--muted);
            font-size: 14px;
            margin: 0 0 32px;
        }

        /* Glassmorphic Panel Cards */
        .panel {
            background: var(--panel);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .panel:hover {
            border-color: var(--border-hover);
            box-shadow: 0 12px 35px rgba(129, 140, 248, 0.05);
        }
        
        /* Buttons with micro-animations */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--panel-2-solid);
            color: var(--text);
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        
        .btn:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-primary {
            background: var(--accent-gradient);
            border: none;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(129, 140, 248, 0.2);
        }
        .btn-primary:hover:not(:disabled) {
            box-shadow: 0 6px 20px rgba(129, 140, 248, 0.35);
            filter: brightness(1.1);
        }
        
        .btn-green {
            background: var(--green);
            border-color: var(--green);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        .btn-green:hover:not(:disabled) {
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
            filter: brightness(1.1);
        }
        
        .btn-red {
            background: rgba(239, 68, 68, 0.06);
            border-color: rgba(239, 68, 68, 0.2);
            color: var(--red);
        }
        .btn-red:hover:not(:disabled) {
            background: var(--red);
            border-color: var(--red);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }
        
        .btn-sm {
            padding: 6px 14px;
            font-size: 12px;
            border-radius: 8px;
        }

        /* Beautiful Glowing Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            border: 1px solid transparent;
        }
        
        .badge::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            box-shadow: 0 0 8px currentColor;
        }
        
        .badge-green {
            background: rgba(16, 185, 129, 0.08);
            border-color: rgba(16, 185, 129, 0.2);
            color: var(--green);
        }
        
        .badge-amber {
            background: rgba(245, 158, 11, 0.08);
            border-color: rgba(245, 158, 11, 0.2);
            color: var(--amber);
        }
        
        .badge-blue {
            background: rgba(59, 130, 246, 0.08);
            border-color: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }
        
        .badge-red {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.2);
            color: var(--red);
        }
        
        .badge-gray {
            background: rgba(156, 163, 175, 0.08);
            border-color: rgba(156, 163, 175, 0.2);
            color: var(--muted);
        }

        /* Sleek Table Design */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 8px;
        }
        
        th, td {
            text-align: left;
            padding: 16px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        
        th {
            font-family: 'Outfit', sans-serif;
            color: var(--muted);
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 2px solid var(--border);
            padding-bottom: 12px;
        }
        
        tr {
            transition: background-color 0.2s ease;
        }
        
        tr:hover td {
            background: rgba(255, 255, 255, 0.015);
        }
        
        tr:last-child td {
            border-bottom: none;
        }

        .muted {
            color: var(--muted);
        }
        
        .empty {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
            font-size: 14px;
            background: rgba(255, 255, 255, 0.01);
            border: 1px dashed var(--border);
            border-radius: 12px;
        }

        /* Beautiful Flash Notifications */
        .flash {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #34d399;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.05);
            animation: slideDown 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        .flash-error {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.2);
            color: #f87171;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.05);
        }
        
        @keyframes slideDown {
            from { transform: translateY(-10px); opacity: 0; }
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

        /* Inputs overrides */
        input[type=text], input[type=url] {
            outline: none;
            transition: all 0.2s ease;
        }
        input[type=text]:focus, input[type=url]:focus {
            border-color: var(--accent-start) !important;
            box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.15) !important;
            background: var(--panel-solid) !important;
        }

        /* Loading Spinner */
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
    </style>
    @livewireStyles
</head>
<body>
    <!-- Top Glass Header Bar -->
    <div class="topbar">
        <div class="brand">ClipForge <span>AI</span></div>
        <nav class="nav">
            @php $r = request()->path(); @endphp
            <a href="/" class="{{ $r === '/' ? 'active' : '' }}">Dashboard</a>
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
