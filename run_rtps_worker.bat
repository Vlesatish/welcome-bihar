@echo off
title Welcome Bihar - RTPS Continuous Auto-Checker & Attacher
cd /d "%~dp0"

echo ========================================================
echo   WELCOME BIHAR - RTPS CONTINUOUS AUTO-ATTACH WORKER
echo   Mode: Continuous Background Daemon (Interval: 60s)
echo   Auto-attaches certificates for all RTPS applications
echo ========================================================
echo.

php cli/check_rtps_certificates.php --daemon --interval=60 --limit=20
