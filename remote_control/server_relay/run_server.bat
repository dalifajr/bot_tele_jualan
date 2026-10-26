@echo off
setlocal
cd /d "%~dp0"
title Android Remote Control - Relay Gateway Server

echo ===================================================
echo     ANDROID REMOTE CONTROL - RELAY SERVER GATEWAY   
echo ===================================================

set VENV_PYTHON=..\..\.venv\Scripts\python.exe

if exist "%VENV_PYTHON%" (
    echo [INFO] Menggunakan Virtual Environment: %VENV_PYTHON%
    "%VENV_PYTHON%" server.py
) else (
    echo [INFO] Menggunakan Python Sistem...
    python server.py
)

pause
