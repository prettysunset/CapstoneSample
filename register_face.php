<?php
session_start();
// Use local DB connection so registration checks and saved templates are local
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  require_once __DIR__ . '/conn.php';
  if (!isset($conn) || !$conn || $conn->connect_errno) {
    throw new Exception('Local DB connection not available');
  }
  $conn->set_charset('utf8mb4');
  error_log('register_face: connected to local DB via conn.php');
} catch (Exception $ex) {
  error_log('register_face: local DB connect failed: ' . $ex->getMessage());
  echo '<!doctype html><html><body><h1>Database connection error</h1><p>Cannot connect to local DB.</p></body></html>';
  exit;
}
// AJAX: check endorsement printed status (called by client before starting camera)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_endorsement') {
  $uname = trim($_POST['username'] ?? '');
  $pwd = trim($_POST['password'] ?? '');
  header('Content-Type: application/json; charset=utf-8');
  if ($uname === '') { echo json_encode(['ok'=>false,'message'=>'missing username']); exit; }
    try {
    // fetch stored password, role and endorsement flag
    $st = $conn->prepare('SELECT user_id, password, role, COALESCE(endorsement_printed,0) AS endorsement_printed FROM users WHERE username = ? LIMIT 1');
    if ($st) {
      $st->bind_param('s', $uname);
      $st->execute();
      $r = $st->get_result()->fetch_assoc();
      $st->close();
      if (!$r) { echo json_encode(['ok'=>true,'user_exists'=>false,'password_ok'=>false,'printed'=>0,'has_face'=>false]); exit; }
      $stored = (string)($r['password'] ?? '');
      $role = (string)($r['role'] ?? '');
      $password_ok = ($pwd !== '' && $pwd === $stored) ? true : false;
      $role_ok = ($role === 'ojt') ? true : false;
      // check if user already has an entry in face_templates
      $has_face = false;
      $user_id = (int)($r['user_id'] ?? 0);
      if ($user_id) {
        $st2 = $conn->prepare('SELECT 1 FROM face_templates WHERE user_id = ? LIMIT 1');
        if ($st2) {
          $st2->bind_param('i', $user_id);
          $st2->execute();
          $res2 = $st2->get_result();
          if ($res2 && $res2->num_rows > 0) { $has_face = true; }
          $st2->close();
        }
      }
      echo json_encode(['ok'=>true,'user_exists'=>true,'password_ok'=>$password_ok,'printed'=>(int)($r['endorsement_printed'] ?? 0),'role'=>$role,'role_ok'=>$role_ok,'has_face'=>$has_face]);
      exit;
    }
    echo json_encode(['ok'=>true,'user_exists'=>false,'password_ok'=>false,'printed'=>0,'role'=>'','role_ok'=>false,'has_face'=>false]);
    exit;
  } catch (Exception $e) { echo json_encode(['ok'=>false,'message'=>'error']); exit; }
}
// AJAX: check whether a submitted descriptor matches any existing face_templates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_descriptor') {
  header('Content-Type: application/json; charset=utf-8');
  $descRaw = $_POST['descriptor'] ?? '';
  $username = trim($_POST['username'] ?? '');
  if ($descRaw === '') { echo json_encode(['ok'=>false,'message'=>'missing descriptor']); exit; }
  try {
    $descArr = json_decode($descRaw, true);
    if (!is_array($descArr) || count($descArr) === 0) { echo json_encode(['ok'=>false,'message'=>'invalid descriptor']); exit; }
    // load all stored descriptors and find best (minimum L2) distance — same logic as pc_per_office.php
    $q = $conn->prepare('SELECT ft.user_id, ft.descriptor, u.username FROM face_templates ft LEFT JOIN users u ON ft.user_id = u.user_id WHERE ft.descriptor IS NOT NULL');
    $best = ['dist' => INF, 'user_id' => null, 'username' => null];
    $templatesScanned = 0;
    if ($q) {
      $q->execute();
      $res = $q->get_result();
      while ($row = $res->fetch_assoc()) {
        $storedJson = $row['descriptor'] ?? null;
        if (!$storedJson) continue;
        $storedArr = json_decode($storedJson, true);
        if (!is_array($storedArr) || count($storedArr) !== count($descArr)) continue;
        $templatesScanned++;
        $sum = 0.0;
        $n = count($descArr);
        for ($i=0; $i<$n; $i++) {
          $a = floatval($descArr[$i]);
          $b = floatval($storedArr[$i]);
          $d = $a - $b;
          $sum += $d * $d;
        }
        $dist = sqrt($sum);
        if ($dist < $best['dist']) {
          $best['dist'] = $dist;
          $best['user_id'] = (int)$row['user_id'];
          $best['username'] = $row['username'] ?? null;
        }
      }
      $q->close();
    }
    // apply a stricter threshold to avoid false positives (stricter than pc_per_office)
    $threshold = 0.40;
    if ($best['user_id'] !== null && $best['dist'] <= $threshold) {
      echo json_encode(['ok'=>true,'match'=>true,'match'=>['user_id'=>$best['user_id'],'username'=>$best['username'],'distance'=>$best['dist'],'templates_scanned'=>$templatesScanned]]);
      exit;
    }
    echo json_encode(['ok'=>true,'match'=>false,'best_distance'=>$best['dist'],'templates_scanned'=>$templatesScanned]);
    exit;
  } catch (Exception $e) { echo json_encode(['ok'=>false,'message'=>'error']); exit; }
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
      <div id="status" class="msg" role="status" aria-live="polite">Enter username and password to start camera.</div>
    </div>
    
  </div>

  <script>
  (async function(){
    // load face-api from CDN and wait for it
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
    script.defer = true;
    document.head.appendChild(script);
    await new Promise(resolve => { script.onload = resolve; setTimeout(resolve, 1500); });

    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const status = document.getElementById('status');

    let stream = null;
    let modelsLoaded = false;
    let detecting = false;

    function show(text, ok=true){
      status.textContent = text;
      status.style.color = ok ? '#0b7a3a' : '#a00';
      status.style.display = 'block';
    }

    async function ensureModels(){
      if (modelsLoaded) return;
      show('Loading models... (place model files in /models)', true);
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

    async function startCamera(){
      if (stream) return;
      try{
        stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user'}, audio:false});
        video.srcObject = stream;
        await video.play();
      }catch(e){
        show('Cannot access camera: ' + (e.message || e), false);
        throw e;
      }
    }

    function stopCamera(){
      if (!stream) return;
      try{
        stream.getTracks().forEach(t => t.stop());
      }catch(e){}
      stream = null;
      try{ video.srcObject = null; }catch(e){}
    }

    // start camera + detection once both creds are entered -- but only after HR printed endorsement
    let startTimer = null;
    function maybeStart(){
      if (username.value.trim() && password.value) {
        if (startTimer) return;
        startTimer = setTimeout(async () => {
          startTimer = null;
          try{
            // ask server whether username/password exist and endorsement has been printed
            const fd = new FormData(); fd.append('action','check_endorsement'); fd.append('username', username.value.trim()); fd.append('password', password.value);
            const res = await fetch(window.location.href, { method: 'POST', body: fd });
            const j = await res.json().catch(()=>null);
            if (!j || !j.ok) { show('Error checking endorsement', false); return; }
            if (!j.user_exists) { show('Username not found', false); return; }
            if (!j.password_ok) { show('Invalid password', false); return; }
            if (!j.role_ok) { show('Only OJT accounts may register a face', false); return; }
            if (!j.printed) { show('The endorsement has not yet been printed by the HR Head.', false); return; }
            if (j.has_face) { show('User already has a registered face; camera will not open.', false); return; }
            await ensureModels();
            await startCamera();
            startDetectionLoop();
          }catch(e){ console.error(e); }
        }, 200);
      }
    }
    username.addEventListener('input', maybeStart);
    password.addEventListener('input', maybeStart);

    async function startDetectionLoop(){
      if (detecting) return;
      detecting = true;
      show('Looking for a face...', true);
      while(detecting){
        try{
          const result = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
          if (result && result.descriptor){
            // capture preview
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video,0,0,canvas.width,canvas.height);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
            // pause detection and show confirmation
            detecting = false;
            show('Face detected — confirm to upload', true);
            showConfirmOverlay(dataUrl, result.descriptor);
            break;
          }
        }catch(e){ console.error('detection error', e); }
        await new Promise(r => setTimeout(r, 250));
      }
    }

    function showConfirmOverlay(dataUrl, descriptor){
      let overlay = document.getElementById('confirmOverlay');
      if (!overlay){
        overlay = document.createElement('div');
        overlay.id = 'confirmOverlay';
        Object.assign(overlay.style, {position:'fixed',left:0,top:0,right:0,bottom:0,background:'rgba(0,0,0,0.6)',display:'flex',alignItems:'center',justifyContent:'center',zIndex:9999});
        const card = document.createElement('div');
        Object.assign(card.style, {background:'#fff',padding:'14px',borderRadius:'8px',maxWidth:'460px',width:'90%',textAlign:'center'});
        const img = document.createElement('img'); img.id = 'confirmImg'; img.style.maxWidth = '100%'; img.style.borderRadius = '6px'; img.alt = 'Preview';
        const p = document.createElement('p'); p.textContent = 'Is this you? Confirm to upload your face descriptor.';
        const row = document.createElement('div'); row.style.display = 'flex'; row.style.justifyContent = 'center'; row.style.gap = '10px'; row.style.marginTop = '12px';
        const yes = document.createElement('button'); yes.textContent = 'Confirm'; yes.style.background = '#3d44a8'; yes.style.color = '#fff'; yes.style.border = '0'; yes.style.padding = '10px 14px'; yes.style.borderRadius = '8px';
        const no = document.createElement('button'); no.textContent = 'Cancel'; no.style.background = '#6b7280'; no.style.color = '#fff'; no.style.border = '0'; no.style.padding = '10px 14px'; no.style.borderRadius = '8px';
        row.appendChild(yes); row.appendChild(no);
        card.appendChild(img); card.appendChild(p); card.appendChild(row); overlay.appendChild(card); document.body.appendChild(overlay);

        yes.addEventListener('click', ()=>{ overlay.style.display='none'; checkAndUpload(descriptor, dataUrl); });
        no.addEventListener('click', ()=>{ overlay.style.display='none'; if (!detecting) { detecting = false; startDetectionLoop(); } });
      }
      document.getElementById('confirmImg').src = dataUrl;
      overlay.style.display = 'flex';
    }

    const RETURN_TO = decodeURIComponent((new URLSearchParams(window.location.search)).get('return') || '');

    async function uploadDescriptor(descriptor, dataUrl){
      show('Uploading descriptor...', true);
      try{
        const user = username.value.trim();
        const pass = password.value;
        if (!user || !pass){ show('Missing username or password', false); return; }
        const fd = new FormData();
        fd.append('username', user);
        fd.append('password', pass);
        fd.append('descriptor', JSON.stringify(Array.from(descriptor)));
        fd.append('image', dataUrl);
        const res = await fetch('save_face.php', { method: 'POST', body: fd });
        const j = await res.json();
        if (j && j.success){ 
          show('Face registered successfully', true);
          stopCamera();
          // Redirect back to caller if provided, else use referrer or pc_per_office.php
          const target = RETURN_TO || document.referrer || './pc_per_office.php';
          setTimeout(()=>{ window.location.href = target; }, 900);
        }
        else { show('Error: ' + (j && j.message ? j.message : 'failed'), false); }
      }catch(e){ show('Upload failed: ' + (e.message || e), false); }
    }

    async function checkAndUpload(descriptor, dataUrl){
      show('Checking for existing face...', true);
      try{
        const fd = new FormData();
        fd.append('action','check_descriptor');
        fd.append('descriptor', JSON.stringify(Array.from(descriptor)));
        fd.append('username', username.value.trim());
        const res = await fetch(window.location.href, { method: 'POST', body: fd });
        const j = await res.json().catch(()=>null);
        if (!j || !j.ok) { show('Error checking descriptor', false); return; }
        if (j.match) {
          show('A matching face is already registered', false);
          try{ stopCamera(); }catch(e){}
          setTimeout(()=>{ window.location.reload(); }, 5000);
          return;
        }
        // no match — proceed to upload
        await uploadDescriptor(descriptor, dataUrl);
      }catch(e){ show('Descriptor check failed: ' + (e.message||e), false); }
    }

  })();
  </script>
</body>
</html>