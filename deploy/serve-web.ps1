# Menjalankan pool php-cgi (default 8 proses) + Caddy di http://127.0.0.1:8000.
# Pakai: powershell -File deploy/serve-web.ps1 [-Workers 8]
# Env: CLIPFORGE_CADDY (path caddy.exe, default ~/.clipforge/caddy/caddy.exe),
#      CLIPFORGE_PHP_CGI (default php-cgi.exe di folder php.exe).
param([int]$Workers = 8, [int]$BasePort = 9101)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path -replace '\\', '/'
$caddy = if ($env:CLIPFORGE_CADDY) { $env:CLIPFORGE_CADDY } else { Join-Path $HOME '.clipforge/caddy/caddy.exe' }
$phpCgi = if ($env:CLIPFORGE_PHP_CGI) { $env:CLIPFORGE_PHP_CGI } else { Join-Path (Split-Path (Get-Command php).Source) 'php-cgi.exe' }

foreach ($bin in @($caddy, $phpCgi)) {
    if (-not (Test-Path $bin)) { throw "Tidak ditemukan: $bin" }
}

$env:PHP_FCGI_MAX_REQUESTS = '0'
$procs = @()
$upstreams = @()

try {
    for ($i = 0; $i -lt $Workers; $i++) {
        $port = $BasePort + $i
        $procs += Start-Process -FilePath $phpCgi -WindowStyle Hidden -PassThru -WorkingDirectory "$root/public" -ArgumentList @(
            '-b', "127.0.0.1:$port",
            '-d', 'max_execution_time=600',
            '-d', 'upload_max_filesize=2100M',
            '-d', 'post_max_size=2100M'
        )
        $upstreams += "127.0.0.1:$port"
    }

    $env:CLIPFORGE_ROOT = $root
    $env:CGI_UPSTREAMS = $upstreams -join ' '

    & $caddy run --config "$root/deploy/Caddyfile" --adapter caddyfile
}
finally {
    $procs | ForEach-Object { if (-not $_.HasExited) { Stop-Process -Id $_.Id -Force } }
}
