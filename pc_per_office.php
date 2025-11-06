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
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>PC — Time In / Time Out</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;background:#f7f9ff;margin:0;padding:28px;color:#222}
  .card{max-width:420px;margin:40px auto;padding:18px;background:#fff;border-radius:10px;box-shadow:0 8px 30px rgba(45,57,120,0.06)}
  h2{margin:0 0 8px;font-size:20px}
  .sub{color:#666;margin-bottom:12px}
  input[type=text], input[type=password]{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:10px;box-sizing:border-box}
  .row{display:flex;gap:8px}
  button{flex:1;padding:12px;border-radius:8px;border:0;cursor:pointer;font-weight:700}
  .in{background:#4f4aa6;color:#fff}
  .out{background:#2f9a66;color:#fff}
  button:disabled{background:#ccc;cursor:not-allowed;color:#333}
  .now{font-weight:700;font-size:18px;margin-bottom:8px}
  .msg{display:none;padding:10px;border-radius:6px;margin-top:10px}
</style>
</head>
<body>
  <div class="card">
    <h2>PC Time Log</h2>
    <?php if ($office_name): ?>
      <div class="sub"><?php echo htmlspecialchars($office_name); ?> — <?php echo date('F j, Y'); ?></div>
    <?php else: ?>
      <div class="sub">PC Mode — <?php echo date('F j, Y'); ?></div>
    <?php endif; ?>

    <div class="now" id="now"><?php echo date('F j, Y') . ' — ' . date('h:i:s A'); ?></div>

    <form id="pcForm" onsubmit="return false;">
      <input type="hidden" id="office_id" value="<?php echo (int)$office_id; ?>">
      <input id="username" type="text" placeholder="OJT username">
      <input id="password" type="password" placeholder="Password">
      <div class="row" style="margin-top:6px">
        <button id="btnIn" class="in" type="button">TIME IN</button>
        <button id="btnOut" class="out" type="button">TIME OUT</button>
      </div>
      <div id="msg" class="msg"></div>
    </form>
  </div>

<script>
(function(){
  const nowEl = document.getElementById('now');
  function tick(){
    const d = new Date();
    // show local date and 12-hour time with seconds
    nowEl.textContent = d.toLocaleDateString() + ' — ' + d.toLocaleTimeString('en-US',{hour12:true});
  }
  setInterval(tick,1000);

  const btnIn = document.getElementById('btnIn');
  const btnOut = document.getElementById('btnOut');
  const username = document.getElementById('username');
  const password = document.getElementById('password');
  const officeId = document.getElementById('office_id').value;
  const msg = document.getElementById('msg');

  function showAlert(text, ok=true){
    // simple alert popup required by user
    alert(text);
    // also show small inline message briefly
    msg.style.display = 'block';
    msg.style.background = ok ? '#e6f9ee' : '#fff4f4';
    msg.style.color = ok ? '#0b7a3a' : '#a00';
    msg.textContent = text;
    setTimeout(()=> msg.style.display = 'none', 2500);
  }

  async function send(action){
    const u = username.value.trim();
    const p = password.value;
    if (!u || !p) { showAlert('Enter username and password', false); return; }
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
        showAlert(j.message || (action==='time_in'?'Time in recorded':'Time out recorded'), true);
        password.value = '';
      } else {
        showAlert(j.message || 'Action failed', false);
      }
    } catch (e) {
      showAlert('Request failed', false);
    } finally {
      setTimeout(()=>{ btnIn.disabled = false; btnOut.disabled = false; }, 700);
    }
  }

  btnIn.addEventListener('click', ()=>send('time_in'));
  btnOut.addEventListener('click', ()=>{ if (!confirm('Confirm Time Out?')) return; send('time_out'); });
})();
</script>
</body>
</html>