Deployment checklist — make antispoof available to LAN/Internet

Overview
- This guide prepares and documents the steps so the antispoof service works both locally and when hosted behind your webserver.

Assumptions
- Windows host running XAMPP (Apache + PHP) where `pc_per_office.php` is served.
- antispoof Python service lives in this project root: `antispoof_service.py` and virtualenv `.venv`.
- ONNX model placed at `models/antispoof_best_model.onnx` for production.

High-level steps
1) Verify the service runs locally
   - Use the provided `run_antispoof.bat` to start detached (dev). For production use a Windows service (NSSM) or system service.
   - Verify locally: `curl http://127.0.0.1:5001/ping` returns JSON with `model_loaded:true`.

2) Reverse-proxy through Apache (recommended)
   - Add the provided `apache_antispoof.conf` snippet into your Apache vhost (e.g. httpd-vhosts.conf) or merge it into the vhost for your site.
   - Enable proxy modules if not already enabled: `mod_proxy` and `mod_proxy_http`.
   - Restart Apache. The proxy exposes the antispoof endpoints as `https://yourdomain/antispoof/*`.
   - Set `ANTISPOOF_URL` in the vhost (snippet includes `SetEnv`) so `pc_per_office.php` emits the proxied URL.

3) Firewall (only if you must expose the service directly)
   - Prefer NOT to open port 5001 to the Internet. If you need LAN access and will not use a proxy, open TCP/5001 only for the LAN subnet.
   - PowerShell (run as Administrator):
     New-NetFirewallRule -DisplayName "Antispoof 5001 (LAN)" -Direction Inbound -LocalPort 5001 -Protocol TCP -Action Allow -RemoteAddress 192.168.1.0/24

4) Run the service persistently
   - Recommended: install as a Windows service with NSSM so it restarts on boot and runs as background service.
   - Example NSSM commands are provided in `install_nssm_commands.txt`.
   - Make sure environment variable `ALLOW_FALLBACK` is NOT set (or set to 0) in production.

5) Production hardening
   - Ensure `ANTISPOOF_MODEL_PATH` points to the real ONNX model and that the model is present.
   - Disable fallback: unset `ALLOW_FALLBACK` or set to `0`.
   - Restrict access to the proxy path using IP allow-listing or basic auth as needed.
   - Monitor `/ping` and logs.

Verification steps (from a remote machine)
- Browser call: `https://yourdomain/antispoof/ping` should return JSON OK (use same scheme as your site).
- From the site, open developer console and ensure `Anti-spoof service ready.` message appears when starting camera.

If you want, run these steps and paste any error output back here; I can help debug.
