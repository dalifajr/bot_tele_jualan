@echo off
setlocal
cd /d "%~dp0"
title CyberControl - Android Flutter Controller App

echo ===================================================
echo     CYBERCONTROL :: FLUTTER MOBILE CONTROLLER APP   
echo ===================================================

echo [1/3] Memeriksa Flutter SDK...
where flutter >nul 2>&1
if %errorlevel% equ 0 (
    echo [INFO] Menjalankan flutter pub get...
    flutter pub get
    echo [INFO] Menjalankan aplikasi di emulator atau HP terhubung...
    flutter run
) else (
    echo [WARNING] Flutter CLI belum terdaftar dalam PATH sistem.
    echo Pastikan Flutter SDK telah diunduh dan ditambahkan ke PATH.
    echo Proyek Dart/Flutter ini siap dijalankan melalui VS Code / Android Studio.
)

pause
