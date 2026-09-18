@echo off
REM ============================================================
REM Petal Moments - Sync to XAMPP (double-click or run in terminal)
REM Copies this folder into the live site used by Apache.
REM ============================================================

powershell -ExecutionPolicy Bypass -File "%~dp0sync-to-xampp.ps1"
pause
