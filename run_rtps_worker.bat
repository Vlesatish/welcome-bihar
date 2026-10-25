@echo off
title Welcome Bihar - RTPS Certificate Check Worker (5-Hour Cycle)
cd /d "%~dp0"

echo ========================================================
echo   WELCOME BIHAR - RTPS CERTIFICATE CHECK WORKER
echo   Interval: Every 5 Hours ^& 7:00 AM Asia/Kolkata
echo ========================================================
echo.

php cli/check_rtps_certificates.php --limit=50

echo.
echo Check completed at %time%
timeout /t 5
