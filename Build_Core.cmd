@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0BuildDocker_Core.ps1" %*
exit /b %ERRORLEVEL%
