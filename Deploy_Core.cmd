@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Deploy_Core.ps1" %*
exit /b %ERRORLEVEL%
