Antispoof service (MiniFAS ONNX)
================================

What this is
- A small Flask service that runs an ONNX anti-spoof model and exposes:
  - `GET /ping` — health check (reports if model loaded)
  - `POST /antispoof` — JSON { image: base64 } → { live: bool, prob_real: float }

Setup (Windows)
1. Install Python 3.8+ if you don't have it.
2. From project root, create a venv (optional) and install requirements:

```powershell
python -m venv .venv
.\.venv\Scripts\activate
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
```

3. Download the MiniFAS ONNX model (quantized or full) and place it at:

```
models/antispoof_best_model.onnx
```

4. Run the service:

```powershell
python antispoof_service.py
```

The service listens on port 5001 by default. If you changed the port or host, update the fetch URL in `pc_per_office.php`.

Troubleshooting
- If `/ping` returns `model_loaded: false` and `model_error` contains a message, check that the ONNX file exists and is compatible.
- If browser shows "Anti-spoof service unreachable":
  - Ensure the Python service is running and not blocked by Windows Firewall.
  - Open `http://localhost:5001/ping` in the same machine's browser — it should return JSON.

Tuning
- Adjust `LIVE_PROB_THRESHOLD` in `antispoof_service.py` to make the classifier more or less strict.
- Use browser DevTools Console to view EAR logs and anti-spoof responses while testing.

If you want, I can also add a small `.bat` to start the venv and run the service.
