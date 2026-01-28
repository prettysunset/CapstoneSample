@echo off
REM Activate venv and run antispoof service (Windows)
if not exist .venv\Scripts\activate.bat (
  echo Virtual environment not found. Create with: python -m venv .venv
  pause
  exit /b 1
)
call .venv\Scripts\activate.bat
rem For local development allow fallback heuristic by setting ALLOW_FALLBACK=1
set ALLOW_FALLBACK=1
start "" "%CD%\.venv\Scripts\python.exe" antispoof_service.py
