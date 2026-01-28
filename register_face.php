<?php
session_start();
// Connect directly to Hostinger DB for this page (no global conn.php).
$h_host = 'auth-db2090.hstgr.io';
$h_user = 'u389936701_user';
$h_pass = 'CapstoneDefended1';
$h_db   = 'u389936701_capstone';
$h_port = 3306;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $conn = @new mysqli($h_host, $h_user, $h_pass, $h_db, $h_port);
  if ($conn && !$conn->connect_errno) {
    $conn->set_charset('utf8mb4');
    error_log('register_face: connected to Hostinger DB ' . $h_host);
  } else {
    error_log('register_face: Hostinger DB connect failed: ' . ($conn ? $conn->connect_error : 'unknown'));
    echo '<!doctype html><html><body><h1>Database connection error</h1><p>Cannot connect to Hostinger DB.</p></body></html>';
    exit;
  }
} catch (Exception $ex) {
  error_log('register_face: exception connecting Hostinger DB: ' . $ex->getMessage());
  echo '<!doctype html><html><body><h1>Database connection exception</h1><p>Check server logs.</p></body></html>';
  exit;
}
// Minimal face registration page: user enters username/password, takes photo, posts to save_face.php
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Register Face</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial,Helvetica,sans-serif;background:#f6f8fb;padding:24px}
    .card{max-width:520px;margin:24px auto;background:#fff;padding:20px;border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,0.08)}
    .input{width:100%;padding:10px;margin:8px 0;border-radius:6px;border:1px solid #ddd}
    video{width:100%;border-radius:8px;background:#000}
    canvas{display:none}
    .row{display:flex;gap:8px}
    button{padding:10px 14px;border-radius:8px;border:0;background:#3d44a8;color:#fff;cursor:pointer}
    button.secondary{background:#6b7280}
    .msg{margin-top:10px}
  </style>
</head>
<body>
  <div class="card">
    <h2>Register Face</h2>
    <p>Enter your username and password, then take a photo to register your face.</p>
    <input id="username" class="input" placeholder="Username" autocomplete="username">
    <input id="password" type="password" class="input" placeholder="Password" autocomplete="current-password">

    <div>
      <video id="video" autoplay playsinline></video>
      <canvas id="canvas"></canvas>
    </div>

    <div class="row" style="margin-top:10px">
      <button id="startBtn">Start Camera</button>
      <button id="captureBtn" class="secondary">Capture</button>
      <button id="uploadBtn">Upload</button>
    </div>

    <div id="msg" class="msg" role="status" aria-live="polite"></div>
    <p style="margin-top:12px;font-size:13px;color:#555">Note: this computes a face descriptor locally (no raw image needs to be uploaded). Models must be downloaded to <code>/models</code> (see README or face-api.js docs).</p>
  </div>

<script>
(async function(){
  // load face-api from CDN
  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
  script.defer = true;
  document.head.appendChild(script);

  const startBtn = document.getElementById('startBtn');
  const captureBtn = document.getElementById('captureBtn');
  const uploadBtn = document.getElementById('uploadBtn');
  const video = document.getElementById('video');
  const canvas = document.getElementById('canvas');
  const msg = document.getElementById('msg');
  let stream = null;
  let modelsLoaded = false;

  function show(text, ok=true){ msg.textContent = text; msg.style.color = ok ? '#0b7a3a' : '#a00'; }

  async function ensureModels(){
    if (modelsLoaded) return;
    show('Loading models... (place model files in /models)', true);
    // models should be available at /models (face-api format)
    try{
      await faceapi.nets.tinyFaceDetector.load('models/');
      await faceapi.nets.faceLandmark68Net.load('models/');
      await faceapi.nets.faceRecognitionNet.load('models/');
      modelsLoaded = true;
      show('Models loaded', true);
    }catch(e){
      show('Failed to load models: ensure /models contains face-api models', false);
      throw e;
    }
  }

  startBtn.addEventListener('click', async ()=>{
    try{
      await ensureModels();
      stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'}, audio:false});
      video.srcObject = stream;
      show('Camera started');
    }catch(e){ show('Cannot access camera or load models: '+e.message,false); }
  });

  captureBtn.addEventListener('click', async ()=>{
    if(!video.srcObject){ show('Start camera first', false); return; }
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video,0,0,canvas.width,canvas.height);
    show('Captured image. Click Upload to compute descriptor.');
  });

  uploadBtn.addEventListener('click', async ()=>{
    const user = document.getElementById('username').value.trim();
    const pass = document.getElementById('password').value;
    if(!user || !pass){ show('Enter username and password', false); return; }
    if(!canvas.width){ show('Capture an image first', false); return; }
    show('Computing face descriptor...', true);
    try{
      // run detection on the canvas image
      const detections = await faceapi.detectSingleFace(canvas, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
      if(!detections || !detections.descriptor){ show('No face detected. Try again.', false); return; }
      const descriptor = Array.from(detections.descriptor); // numeric array
      // optionally send image as well for manual review
      const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
      const fd = new FormData();
      fd.append('username', user);
      fd.append('password', pass);
      fd.append('descriptor', JSON.stringify(descriptor));
      fd.append('image', dataUrl);
      show('Uploading descriptor...', true);
      const res = await fetch('save_face.php', { method:'POST', body: fd });
      const j = await res.json();
      if(j.success){ show('Face registered successfully', true); }
      else show('Error: '+(j.message||'failed'), false);
    }catch(e){ show('Failed: '+e.message, false); }
  });
})();
</script>
</body>
</html>