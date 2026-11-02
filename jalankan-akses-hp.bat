@echo off
title Server LAN / Wi-Fi - Sistem Administrasi Guru
cd /d "%~dp0"

echo ================================================================
echo   SISTEM ADMINISTRASI GURU - SERVER AKSES LAPTOP ^& HP (WI-FI)
echo ================================================================
echo.
echo Pastikan XAMPP MySQL sudah aktif (Running).
echo Daftar alamat IP jaringan komputer Anda saat ini:
echo.
powershell -NoProfile -Command "Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } | ForEach-Object { Write-Host ('  -> Buka di HP (' + $_.InterfaceAlias + '): http://' + $_.IPAddress + ':8080') -ForegroundColor Green }"
echo.
echo   -^> Buka di Laptop ini       : http://localhost:8080
echo ================================================================
echo Tekan Ctrl+C untuk menghentikan server.
echo.

php artisan serve --host=0.0.0.0 --port=8080
pause
