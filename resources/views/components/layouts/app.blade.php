<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Auto-Clip AI' }}</title>
    <style>
        :root {
            --bg: #0f1115; --panel: #171a21; --panel-2: #1e222b;
            --border: #2a2f3a; --text: #e6e8ec; --muted: #9aa2b1;
            --accent: #6d8bff; --accent-2: #4f6ef2;
            --green: #3ecf8e; --amber: #f5b544; --red: #f2555a; --gray: #6b7280;
            --radius: 12px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 14px; line-height: 1.5;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .topbar {
            display: flex; align-items: center; gap: 24px;
            padding: 14px 24px; background: var(--panel);
            border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 10;
        }
        .brand { font-weight: 700; font-size: 16px; letter-spacing: .3px; }
        .brand span { color: var(--accent); }
        .nav { display: flex; gap: 4px; }
        .nav a {
            padding: 7px 14px; border-radius: 8px; color: var(--muted); font-weight: 500;
        }
        .nav a:hover { background: var(--panel-2); color: var(--text); text-decoration: none; }
        .nav a.active { background: var(--accent-2); color: #fff; }
        .container { max-width: 1100px; margin: 0 auto; padding: 28px 24px; }
        .page-title { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
        .page-sub { color: var(--muted); margin: 0 0 24px; }
        .panel {
            background: var(--panel); border: 1px solid var(--border);
            border-radius: var(--radius); padding: 20px;
        }
        .panel + .panel { margin-top: 18px; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px; cursor: pointer;
            border: 1px solid var(--border); background: var(--panel-2); color: var(--text);
            padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 600;
            transition: filter .12s; font-family: inherit;
        }
        .btn:hover { filter: brightness(1.15); }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn-primary { background: var(--accent-2); border-color: var(--accent-2); color: #fff; }
        .btn-green { background: var(--green); border-color: var(--green); color: #06281c; }
        .btn-red { background: transparent; border-color: var(--red); color: var(--red); }
        .btn-sm { padding: 5px 11px; font-size: 13px; }
        .badge {
            display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px;
            border-radius: 999px; font-size: 12px; font-weight: 600; text-transform: capitalize;
        }
        .badge::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .badge-green { background: rgba(62,207,142,.14); color: var(--green); }
        .badge-amber { background: rgba(245,181,68,.14); color: var(--amber); }
        .badge-blue  { background: rgba(109,139,255,.16); color: var(--accent); }
        .badge-red   { background: rgba(242,85,90,.14); color: var(--red); }
        .badge-gray  { background: rgba(107,114,128,.18); color: var(--muted); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px 10px; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .4px; }
        tr:last-child td { border-bottom: none; }
        .muted { color: var(--muted); }
        .empty { text-align: center; padding: 48px 20px; color: var(--muted); }
        .flash {
            padding: 12px 16px; border-radius: 8px; margin-bottom: 18px;
            background: rgba(62,207,142,.12); border: 1px solid rgba(62,207,142,.3); color: var(--green);
        }
        .flash-error { background: rgba(242,85,90,.1); border-color: rgba(242,85,90,.3); color: var(--red); }
        .grid { display: grid; gap: 16px; }
        .row { display: flex; align-items: center; gap: 12px; }
        .between { justify-content: space-between; }
        input[type=file] { color: var(--muted); }
        .spin { display: inline-block; width: 13px; height: 13px; border: 2px solid var(--muted);
            border-top-color: transparent; border-radius: 50%; animation: sp .7s linear infinite; }
        @keyframes sp { to { transform: rotate(360deg); } }
    </style>
    @livewireStyles
</head>
<body>
    <div class="topbar">
        <div class="brand">Auto&#8209;Clip <span>AI</span></div>
        <nav class="nav">
            @php $r = request()->path(); @endphp
            <a href="/" class="{{ $r === '/' ? 'active' : '' }}">Dashboard</a>
            <a href="/exports" class="{{ str_starts_with($r, 'exports') ? 'active' : '' }}">Exports</a>
        </nav>
    </div>

    <main class="container">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
