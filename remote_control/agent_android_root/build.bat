@echo off
REM ==============================================================================
REM Build Script Otomatis: Remote Agent Android Root
REM Mengompilasi Java Source -> Bytecode .class -> Dalvik .dex -> remote_agent.jar
REM ==============================================================================

setlocal enabledelayedexpansion
set "ROOT_DIR=%~dp0"
cd /d "%ROOT_DIR%"

set "SRC_DIR=src\main\java\com\remote\agent"
set "OUT_DIR=build\classes"
set "JAR_OUT=build\remote_agent.jar"

echo =======================================================
echo     BUILD OTOMATIS REMOTE AGENT ANDROID ROOT
echo =======================================================
echo.

echo [1/4] Menyiapkan folder output...
if exist "build" rmdir /s /q "build"
mkdir "%OUT_DIR%"

echo [2/4] Memeriksa Android SDK di D:\Android...
set "ANDROID_JAR="
if exist "D:\Android\platforms\android-13\android.jar" (
    set "ANDROID_JAR=D:\Android\platforms\android-13\android.jar"
) else (
    for /d %%D in (D:\Android\platforms\*) do (
        if exist "%%D\android.jar" set "ANDROID_JAR=%%D\android.jar"
    )
)

if "%ANDROID_JAR%"=="" (
    echo [ERROR] android.jar tidak ditemukan di D:\Android\platforms!
    echo Pastikan platform Android SDK terpasang di D:\Android.
    pause
    exit /b 1
)
echo [INFO] android.jar: %ANDROID_JAR%

set "D8_BAT="
if exist "D:\Android\build-tools\android-13\d8.bat" (
    set "D8_BAT=D:\Android\build-tools\android-13\d8.bat"
) else (
    for /d %%D in (D:\Android\build-tools\*) do (
        if exist "%%D\d8.bat" set "D8_BAT=%%D\d8.bat"
    )
)

if "%D8_BAT%"=="" (
    echo [ERROR] d8.bat tidak ditemukan di D:\Android\build-tools!
    echo Pastikan build-tools Android SDK terpasang di D:\Android.
    pause
    exit /b 1
)
echo [INFO] D8 compiler: %D8_BAT%

echo [3/4] Mengompilasi Java source code (javac)...
javac -source 1.8 -target 1.8 -cp "%ANDROID_JAR%" -d "%OUT_DIR%" %SRC_DIR%\*.java 2> build\compile_error.log
if errorlevel 1 (
    echo [ERROR] Kompilasi javac gagal. Detail error:
    type build\compile_error.log
    pause
    exit /b 1
)
echo [INFO] Kompilasi javac berhasil tanpa error!

echo [4/4] Mengonversi .class ke Dalvik Executable (classes.dex) via D8...
set "CLASS_FILES="
for %%F in ("%OUT_DIR%\com\remote\agent\*.class") do (
    set "CLASS_FILES=!CLASS_FILES! "%%F""
)
call "%D8_BAT%" --output "%JAR_OUT%" !CLASS_FILES!


if exist "%JAR_OUT%" (
    echo.
    echo =======================================================
    echo   BUILD BERHASIL: %JAR_OUT%
    echo =======================================================
    echo File binary remote_agent.jar siap dipush ke HP Android target!
    echo Ukuran file:
    dir "%JAR_OUT%" | findstr "remote_agent.jar"
    echo.
) else (
    echo [ERROR] Gagal menghasilkan remote_agent.jar melalui D8.
    pause
    exit /b 1
)

pause
