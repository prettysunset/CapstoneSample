<?php
session_start();
require_once __DIR__ . '/conn.php';

// Helper to send JSON
function json_resp($arr){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

// detect if dtr table has office_id column (optional)
$hasOfficeCol = false;
try {
    $c = $conn->query("SHOW COLUMNS FROM `dtr` LIKE 'office_id'");
    if ($c && $c->num_rows) $hasOfficeCol = true;
} catch(Exception $e){ /* ignore */ }

// Handle AJAX POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $office_id = isset($_POST['office_id']) ? (int)$_POST['office_id'] : 0;

    if ($username === '' || $password === '') {
        json_resp(['success'=>false,'message'=>'Enter username and password']);
    }

    // Find user by username
    $u = $conn->prepare("SELECT user_id, password, role FROM users WHERE username = ? LIMIT 1");
    $u->bind_param('s',$username);
    $u->execute();
    $user = $u->get_result()->fetch_assoc();
    $u->close();
    if (!$user) json_resp(['success'=>false,'message'=>'Invalid username or password']);

    // password check (support hashed or plain)
    $stored = $user['password'] ?? '';
    $ok = false;
    if (password_get_info($stored)['algo'] !== 0) {
        $ok = password_verify($password, $stored);
    } else {
        $ok = hash_equals((string)$stored, (string)$password);
    }
    if (!$ok) json_resp(['success'=>false,'message'=>'Invalid username or password']);

    if (($user['role'] ?? '') !== 'ojt') json_resp(['success'=>false,'message'=>'User is not an OJT']);

    $student_id = (int)$user['user_id'];
    $today = date('Y-m-d');
    $now = date('H:i');

    // fetch existing dtr row for today
    $q = $conn->prepare("SELECT dtr_id, am_in, am_out, pm_in, pm_out FROM dtr WHERE student_id = ? AND log_date = ? LIMIT 1");
    $q->bind_param('is', $student_id, $today);
    $q->execute();
    $dtr = $q->get_result()->fetch_assoc();
    $q->close();

    if ($action === 'time_in') {
        // choose am_in first, then pm_in
        if ($dtr) {
            if (empty($dtr['am_in'])) {
                $field = 'am_in';
            } elseif (empty($dtr['pm_in'])) {
                $field = 'pm_in';
            } else {
                json_resp(['success'=>false,'message'=>'Already timed in for today']);
            }
            $upd = $conn->prepare("UPDATE dtr SET {$field} = ? WHERE dtr_id = ?");
            $upd->bind_param('si', $now, $dtr['dtr_id']);
            $ok = $upd->execute();
            $upd->close();
        } else {
            // insert; include office_id if column exists and value provided
            if ($hasOfficeCol && $office_id) {
                $ins = $conn->prepare("INSERT INTO dtr (student_id, log_date, am_in, office_id) VALUES (?, ?, ?, ?)");
                $ins->bind_param('issi', $student_id, $today, $now, $office_id);
            } else {
                // choose am_in or pm_in based on hour
                $hour = (int)date('H');
                if ($hour < 12) {
                    $ins = $conn->prepare("INSERT INTO dtr (student_id, log_date, am_in) VALUES (?, ?, ?)");
                } else {
                    $ins = $conn->prepare("INSERT INTO dtr (student_id, log_date, pm_in) VALUES (?, ?, ?)");
                }
                $ins->bind_param('iss', $student_id, $today, $now);
            }
            $ok = $ins->execute();
            $ins->close();
        }
        if ($ok) json_resp(['success'=>true,'message'=>'Time in recorded','time'=>$now]);
        json_resp(['success'=>false,'message'=>'DB error on time in']);
    }

    if ($action === 'time_out') {
        // require matching time-in pair: prefer pm_out if pm_in exists and pm_out empty; else am_out if am_in exists and am_out empty
        if (!$dtr) {
            json_resp(['success'=>false,'message'=>'No time-in found for today']);
        }
        $field = null;
        if (!empty($dtr['pm_in']) && empty($dtr['pm_out'])) $field = 'pm_out';
        elseif (!empty($dtr['am_in']) && empty($dtr['am_out'])) $field = 'am_out';
        else json_resp(['success'=>false,'message'=>'Nothing to time out or already timed out']);

        $upd = $conn->prepare("UPDATE dtr SET {$field} = ? WHERE dtr_id = ?");
        $upd->bind_param('si', $now, $dtr['dtr_id']);
        $ok = $upd->execute();
        $upd->close();
        if (!$ok) json_resp(['success'=>false,'message'=>'DB error on time out']);

        // recompute total hours/minutes from complete pairs
        $sel = $conn->prepare("SELECT am_in,am_out,pm_in,pm_out FROM dtr WHERE dtr_id = ? LIMIT 1");
        $sel->bind_param('i', $dtr['dtr_id']);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();

        $totalMin = 0;
        $pairs = [['am_in','am_out'], ['pm_in','pm_out']];
        foreach ($pairs as $p) {
            if (!empty($row[$p[0]]) && !empty($row[$p[1]])) {
                $t1 = DateTime::createFromFormat('H:i', $row[$p[0]]);
                $t2 = DateTime::createFromFormat('H:i', $row[$p[1]]);
                if ($t1 && $t2) {
                    $diff = $t2->getTimestamp() - $t1->getTimestamp();
                    if ($diff > 0) $totalMin += intval($diff / 60);
                }
            }
        }
        $hours = intdiv($totalMin, 60);
        $minutes = $totalMin % 60;

        $up2 = $conn->prepare("UPDATE dtr SET hours = ?, minutes = ? WHERE dtr_id = ?");
        $up2->bind_param('iii', $hours, $minutes, $dtr['dtr_id']);
        $up2->execute();
        $up2->close();

        json_resp(['success'=>true,'message'=>'Time out recorded','time'=>$now,'hours'=>$hours,'minutes'=>$minutes]);
    }

    json_resp(['success'=>false,'message'=>'Unknown action']);
    // end POST handler
}

// Render minimal page
$office_id = isset($_GET['office_id']) ? (int)$_GET['office_id'] : 0;
$office_name = '';
if ($office_id) {
    $s = $conn->prepare("SELECT office_name FROM offices WHERE office_id = ? LIMIT 1");
    $s->bind_param('i',$office_id);
    $s->execute();
    $of = $s->get_result()->fetch_assoc();
    $s->close();
    $office_name = $of['office_name'] ?? '';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>PC — Time In / Time Out</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{
    --bg1:#e6f2ff;
    --card-bg: rgba(255,255,255,0.95);
    --accent:#3a4163;
    --btn-in:#3d44a8;
    --btn-out:#355e4a;
  }
  /* make page area full viewport and hide scrollbar on desktop */
  html,body{
    height:100%;
    margin:0;
    font-family:'Poppins',sans-serif;
    background:var(--bg1);
    overflow:hidden; /* hides scrollbar — see note below */
  }

  /* background like login */
  .page-bg{
    min-height:100vh;
    background-image:url('123456.png');
    background-size:cover;
    background-position:center;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px;
    box-sizing:border-box;
    width:100%;
  }

  /* larger card, responsive max-width so it never forces a horizontal scrollbar */
  .card{
    width:640px;           /* increased container width */
    max-width:calc(100% - 48px); /* leave breathing room to avoid overflow */
    background:linear-gradient(180deg, rgba(255,255,255,0.95), rgba(255,255,255,0.90));
    border-radius:20px;
    padding:32px;
    box-shadow: 8px 14px 40px rgba(58,65,99,0.12);
    position:relative;
    overflow:visible;
  }

  .logo{font-size:14px;color:var(--accent);text-align:center;font-weight:700;margin-bottom:8px}
  .time-big{font-weight:700;font-size:20px;color:var(--accent);text-align:center}
  .date-sub{color:#6b7280;text-align:center;margin-bottom:16px}
  .sub-desc{color:#5b6477;text-align:center;margin-bottom:18px;font-size:13px}
  .form-row{display:flex;gap:10px}
  .input{
    width:100%;
    background:white;
    border-radius:10px;
    border:1px solid rgba(58,65,99,0.06);
    padding:12px 14px;
    box-sizing:border-box;
    font-size:14px;
    color:#222;
    margin-bottom:10px;
  }
  .password-container{position:relative}
  .password-container button{
    position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:0;cursor:pointer;padding:4px;color:var(--accent)
  }
  .actions{display:flex;gap:12px;justify-content:center;margin-top:6px}
  .btn{
    flex:1;padding:12px;border-radius:12px;border:0;color:white;font-weight:700;cursor:pointer;box-shadow:0 6px 18px rgba(58,65,99,0.08)
  }
  .btn.in{background:var(--btn-in)}
  .btn.out{background:var(--btn-out)}
  .btn:disabled{background:#c7c7c7;cursor:not-allowed;color:#444}
  .msg{display:none;text-align:center;margin-top:12px;padding:10px;border-radius:8px;font-size:14px}
  .office-name{font-size:13px;color:#4b5563;text-align:center;margin-bottom:8px}

  /* hide native scrollbar visuals in WebKit/Firefox (keeps ability to scroll if overflow occurs) */
  ::-webkit-scrollbar { width:0; height:0; }
  html { -ms-overflow-style: none; scrollbar-width: none; }

  /* responsive: allow scrolling on small screens and reduce card size */
  @media (max-width:760px){
    html,body{ overflow:auto; } /* enable scroll on narrow devices */
    .card{ width:94%; padding:20px; border-radius:14px; }
    .time-big{font-size:16px}
  }
</style>
</head>
<body>
  <div class="page-bg">
    <div class="card" role="region" aria-label="PC Time Log">
      <div class="logo">OJT-MS</div>
      <div class="time-big" id="now">--:--:--</div>
      <div class="date-sub" id="date"><?php echo date('F j, Y'); ?></div>
      <?php if ($office_name): ?>
        <div class="office-name"><?php echo htmlspecialchars($office_name); ?></div>
      <?php endif; ?>

      <form id="pcForm" onsubmit="return false;" style="margin-top:6px">
        <input type="hidden" id="office_id" value="<?php echo (int)$office_id; ?>">
        <input id="username" class="input" type="text" placeholder="Username" autocomplete="username">
        <div class="password-container">
          <input id="password" class="input" type="password" placeholder="Password" autocomplete="current-password">
          <button type="button" id="togglePassword" aria-label="Show password">
            <svg id="eyeOpen" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3a4163" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3a4163" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
              <path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a18.65 18.65 0 0 1 4.11-5.05"></path>
              <path d="M1 1l22 22"></path>
              <path d="M9.88 9.88A3 3 0 0 0 14.12 14.12"></path>
            </svg>
          </button>
        </div>

        <div class="actions">
          <button id="btnIn" class="btn in" type="button">Time In</button>
          <button id="btnOut" class="btn out" type="button">Time Out</button>
        </div>

        <div id="msg" class="msg" role="status" aria-live="polite"></div>
      </form>
    </div>
  </div>

<script>
(function(){
  const nowEl = document.getElementById('now');
  const dateEl = document.getElementById('date');
  function tick(){
    const d = new Date();
    nowEl.textContent = d.toLocaleTimeString('en-US',{hour12:true});
    dateEl.textContent = d.toLocaleDateString(undefined,{month:'long',day:'numeric',year:'numeric'});
  }
  tick();
  setInterval(tick,1000);

  const btnIn = document.getElementById('btnIn');
  const btnOut = document.getElementById('btnOut');
  const username = document.getElementById('username');
  const password = document.getElementById('password');
  const officeId = document.getElementById('office_id').value;
  const msg = document.getElementById('msg');

  function showMsg(text, ok=true){
    msg.style.display = 'block';
    msg.style.background = ok ? '#e6f9ee' : '#fff4f4';
    msg.style.color = ok ? '#0b7a3a' : '#a00';
    msg.textContent = text;
    setTimeout(()=> msg.style.display = 'none', 3000);
  }

  // toggle eye
  (function(){
    var btn = document.getElementById('togglePassword');
    var pwd = document.getElementById('password');
    var openEye = document.getElementById('eyeOpen');
    var closedEye = document.getElementById('eyeClosed');
    btn.addEventListener('click', function(e){
        e.preventDefault();
        if (pwd.type === 'password') {
            pwd.type = 'text';
            openEye.style.display = 'none';
            closedEye.style.display = 'inline';
            btn.setAttribute('aria-label', 'Hide password');
        } else {
            pwd.type = 'password';
            openEye.style.display = 'inline';
            closedEye.style.display = 'none';
            btn.setAttribute('aria-label', 'Show password');
        }
    }, true);
  })();

  async function send(action){
    const u = username.value.trim();
    const p = password.value;
    if (!u || !p) { showMsg('Enter username and password', false); return; }
    btnIn.disabled = true; btnOut.disabled = true;
    try {
      const form = new FormData();
      form.append('action', action);
      form.append('username', u);
      form.append('password', p);
      if (officeId && Number(officeId) !== 0) form.append('office_id', officeId);
      const res = await fetch(window.location.href, { method:'POST', body: form });
      const j = await res.json();
      if (j.success) {
        showMsg(j.message || (action==='time_in'?'Time in recorded':'Time out recorded'), true);
        password.value = '';
      } else {
        showMsg(j.message || 'Action failed', false);
      }
    } catch (e) {
      showMsg('Request failed', false);
    } finally {
      // re-enable after short delay to avoid accidental double clicks
      setTimeout(()=>{ btnIn.disabled = false; btnOut.disabled = false; }, 600);
    }
  }

  btnIn.addEventListener('click', ()=>send('time_in'));
  btnOut.addEventListener('click', ()=>{ if (!confirm('Confirm Time Out?')) return; send('time_out'); });
})();
</script>
</body>
</html>