@echo off
REM Production runner for antispoof service (detached). Use only for local LAN testing.
if not exist .venv\Scripts\activate.bat (
  echo Virtual environment not found. Create with: python -m venv .venv
  pause
  exit /b 1
)
call .venv\Scripts\activate.bat
REM Ensure fallback is disabled in production
set ALLOW_FALLBACK=0
start "" "%CD%\.venv\Scripts\python.exe" antispoof_service.py
echo Antispoof service started (detached). Check http://127.0.0.1:5001/ping
