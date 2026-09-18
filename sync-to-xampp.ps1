# ============================================================
# Petal Moments - Sync to XAMPP
#
# Copies all files from this folder (Downloads\petal-moments-frontend)
# into the live site folder used by Apache:
#     C:\xampp\htdocs\petal-moments
#
# HOW TO USE (in VS Code terminal):
#     .\sync-to-xampp.ps1
# ============================================================

$src = Split-Path -Parent $MyInvocation.MyCommand.Path
$dst = "C:\xampp\htdocs\petal-moments"

if (-not (Test-Path -LiteralPath $dst)) {
    Write-Host "ERROR: Destination not found: $dst" -ForegroundColor Red
    Write-Host "Make sure XAMPP is installed at C:\xampp."
    exit 1
}

Write-Host "Copying from: $src" -ForegroundColor Cyan
Write-Host "Copying to  : $dst" -ForegroundColor Cyan
Copy-Item -Path "$src\*" -Destination $dst -Recurse -Force

Write-Host ""
Write-Host "Sync complete. Refresh your browser to see the changes." -ForegroundColor Green
Write-Host "Site: http://localhost/petal-moments/" -ForegroundColor Yellow
Write-Host "Admin: http://localhost/petal-moments/admin/" -ForegroundColor Yellow
