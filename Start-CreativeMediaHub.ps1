# Creative Media Hub - PowerShell Launcher
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $ScriptDir

Write-Host "===================================================" -ForegroundColor Cyan
Write-Host "          CREATIVE MEDIA HUB - PORTABLE            " -ForegroundColor White
Write-Host "===================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Locate PHP
$PhpBin = "php"
if (Test-Path "$ScriptDir\php\php.exe") {
    $PhpBin = "$ScriptDir\php\php.exe"
    Write-Host "[*] Using bundled portable PHP: $PhpBin" -ForegroundColor Green
} elseif (Get-Command php -ErrorAction SilentlyContinue) {
    Write-Host "[*] Using system PHP from PATH" -ForegroundColor Green
} else {
    Write-Host "[!] PHP executable not found! Place portable PHP in $ScriptDir\php or install PHP." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

# 2. Ensure .env
if (-not (Test-Path "$ScriptDir\.env")) {
    Write-Host "[*] Creating .env from .env.example..." -ForegroundColor Yellow
    Copy-Item "$ScriptDir\.env.example" "$ScriptDir\.env"
    & $PhpBin artisan key:generate --force
}

# 3. Ensure SQLite Database
if (-not (Test-Path "$ScriptDir\database\database.sqlite")) {
    Write-Host "[*] Initializing SQLite database..." -ForegroundColor Yellow
    New-Item "$ScriptDir\database\database.sqlite" -ItemType File -Force | Out-Null
    & $PhpBin artisan migrate --force
}

$Port = 8088
$HostIp = "127.0.0.1"
$Url = "http://$HostIp`:$Port"

Write-Host "[*] Launching browser to $Url..." -ForegroundColor Cyan
Start-Process $Url

Write-Host "[*] Starting PHP development server on $Url (Press Ctrl+C to stop)..." -ForegroundColor White
& $PhpBin artisan serve --host=$HostIp --port=$Port
