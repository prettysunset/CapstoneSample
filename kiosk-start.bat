@echo off
REM Kiosk launcher for Chrome (edit KIOSK_URL)
SETLOCAL ENABLEDELAYEDEXPANSION

:: Set path to Chrome executable - adjust if installed elsewhere
set "CHROME_PATH=C:\Program Files\Google\Chrome\Application\chrome.exe"
if not exist "%CHROME_PATH%" set "CHROME_PATH=C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"

:: Change this to your hosted URL (use HTTPS)
set "KIOSK_URL=https://ojtms.com/pc_per_office.php"

:: Dedicated profile directory (prevents reusing existing Chrome)
set "PROFILE_DIR=%~dp0\.kiosk_profile"
if not exist "%PROFILE_DIR%" mkdir "%PROFILE_DIR%"

:: Launch Chrome in kiosk using isolated profile. --no-first-run reduces popups.
start "Kiosk" "%CHROME_PATH%" --kiosk "%KIOSK_URL%" --user-data-dir="%PROFILE_DIR%" --no-first-run --disable-infobars --disable-translate --new-window
ENDLOCAL
