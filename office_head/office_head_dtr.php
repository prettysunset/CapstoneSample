<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];
$su = $conn->prepare("SELECT first_name,last_name,office_name FROM users WHERE user_id=? LIMIT 1");
$su->bind_param('i',$user_id); $su->execute(); $u=$su->get_result()->fetch_assoc(); $su->close();
$display_name = trim(($u['first_name']??'').' '.($u['last_name']??'')) ?: 'Office Head';
$office_name = $u['office_name'] ?? '';
$office_display = preg_replace('/\s+Office\s*$/i','',$office_name ?: 'Unknown Office');

// load students for this office (populate select in modal)
$students = [];
$stmt = $conn->prepare("
    SELECT u.user_id, u.first_name, u.last_name, COALESCE(s.student_id,0) AS student_id
    FROM users u
    LEFT JOIN students s ON s.user_id = u.user_id
    WHERE u.role = 'ojt' AND u.office_name LIKE ?
    ORDER BY u.last_name, u.first_name
");
$like = '%' . ($office_name ?: '') . '%';
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $students[] = $r;
$stmt->close();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Office Head — DTR</title>
<link rel="stylesheet" href="../ojts/stylesforojt.css">
<style>
  body{font-family:Poppins, sans-serif;margin:0;background:#f5f6fa}
  .sidebar{width:220px;background:#2f3459;position:fixed;height:100vh;padding-top:30px;color:#fff}
  .main{margin-left:240px;padding:20px}
  .table{background:#fff;padding:12px;border-radius:8px}
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  .btn{padding:10px 14px;border-radius:20px;border:0;background:#4f4aa6;color:#fff;cursor:pointer}
  .late-modal-overlay{position:fixed;inset:0;background:rgba(15,15,20,0.45);display:none;align-items:center;justify-content:center;z-index:2200}
  .late-modal{width:420px;background:#fff;border-radius:12px;padding:16px;box-shadow:0 12px 40px rgba(0,0,0,0.12)}
  .late-modal label{display:block;font-size:13px;margin:6px 0}
  .late-modal input[type="date"], .late-modal input[type="time"], .late-modal select{width:100%;padding:8px;border-radius:8px;border:1px solid #e6e9f2;box-sizing:border-box}
  .late-modal .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:10px}
  .late-modal .error{color:#a00;font-size:13px;margin-top:6px;display:none}
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar_home.php'; ?>

<div class="main">
  <h1>Daily Logs — <?= htmlspecialchars($office_display) ?></h1>

  <div class="table">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div>
        <div style="font-weight:600;margin-bottom:6px">Daily Logs</div>
        <div style="color:#6b6f8b;font-size:13px">View daily attendance records for your office</div>
      </div>

      <div style="display:flex;gap:12px;align-items:center">
        <div style="display:flex;align-items:center;gap:8px">
          <input id="lateDate" type="date" value="<?= date('Y-m-d') ?>" />
        </div>
        <button id="btnCreateLate" class="btn">Create Late DTR</button>
      </div>
    </div>

    <div style="overflow:auto">
      <table style="width:100%;border-collapse:collapse">
        <thead>
          <tr>
            <th style="padding:12px;text-align:left">DATE</th>
            <th style="padding:12px;text-align:left">NAME</th>
            <th style="padding:12px;text-align:left">SCHOOL</th>
            <th style="padding:12px;text-align:left">COURSE</th>
            <th style="padding:12px;text-align:left">A.M. ARRIVAL</th>
            <th style="padding:12px;text-align:left">A.M. DEPARTURE</th>
            <th style="padding:12px;text-align:left">P.M. ARRIVAL</th>
            <th style="padding:12px;text-align:left">P.M. DEPARTURE</th>
            <th style="padding:12px;text-align:left">HOURS</th>
            <th style="padding:12px;text-align:left">STATUS</th>
          </tr>
        </thead>
        <tbody id="dtrBody">
          <tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">Loading…</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create Late DTR modal -->
<div id="lateModalOverlay" class="late-modal-overlay" aria-hidden="true">
  <div class="late-modal" role="dialog" aria-modal="true">
    <h3 style="margin:0 0 8px">Create Late DTR</h3>

    <label for="late_student">Student</label>
    <select id="late_student">
      <option value="">-- select student --</option>
      <?php foreach ($students as $s): ?>
        <?php $sid = (int)($s['student_id'] ?: 0); ?>
        <option value="<?php echo $sid ? $sid : (int)$s['user_id']; ?>"><?php echo htmlspecialchars(trim($s['first_name'].' '.$s['last_name'])); ?></option>
      <?php endforeach; ?>
    </select>

    <label for="late_date">Date</label>
    <input id="late_date" type="date" value="<?= date('Y-m-d') ?>" />

    <label>A.M. Arrival / Departure</label>
    <input id="late_am_in" type="time" />
    <input id="late_am_out" type="time" style="margin-top:6px" />

    <label style="margin-top:8px">P.M. Arrival / Departure</label>
    <input id="late_pm_in" type="time" />
    <input id="late_pm_out" type="time" style="margin-top:6px" />

    <div class="error" id="lateError"></div>

    <div class="actions">
      <button id="lateCancel" type="button" style="padding:8px 12px;border-radius:8px;border:0;background:#f2f2f4">Cancel</button>
      <button id="lateUpload" type="button" class="btn">Save</button>
    </div>
  </div>
</div>

<script>
(function(){
  const dateInput = document.getElementById('lateDate');
  const tbody = document.getElementById('dtrBody');
  const overlay = document.getElementById('lateModalOverlay');
  const btnCreate = document.getElementById('btnCreateLate');
  const btnCancel = document.getElementById('lateCancel');
  const btnSave = document.getElementById('lateUpload');
  const errEl = document.getElementById('lateError');

  function render(rows){
    tbody.innerHTML = '';
    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">No records found.</td></tr>';
      return;
    }
    rows.forEach(r=>{
      const tr = document.createElement('tr');
      tr.innerHTML = '<td>'+ (r.log_date||'') +'</td>'
                   + '<td>'+ (r.first_name?'': '') + (r.first_name||'') + ' ' + (r.last_name||'') +'</td>'
                   + '<td>'+ (r.school||'-') +'</td>'
                   + '<td>'+ (r.course||'-') +'</td>'
                   + '<td style="color:'+ (r.am_in && r.am_in.match(/:?[0-9]+/)? '#e03' : '#111') + '">'+ (r.am_in||'-') +'</td>'
                   + '<td>'+ (r.am_out||'-') +'</td>'
                   + '<td>'+ (r.pm_in||'-') +'</td>'
                   + '<td>'+ (r.pm_out||'-') +'</td>'
                   + '<td>'+ (r.hours||'-') +'</td>'
                   + '<td>'+ (r.status||'-') +'</td>';
      tbody.appendChild(tr);
    });
  }

  async function fetchFor(d){
    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">Loading…</td></tr>';
    try {
      const res = await fetch('office_head_action.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'get_daily_logs', date: d })
      });
      const j = await res.json();
      if (j && j.success) render(j.data || []); else render([]);
    } catch(e){ console.error(e); render([]); }
  }

  dateInput.addEventListener('change', ()=>fetchFor(dateInput.value));
  fetchFor(dateInput.value);

  // modal logic
  function openModal(){ errEl.style.display='none'; errEl.textContent=''; overlay.style.display='flex'; overlay.setAttribute('aria-hidden','false'); document.getElementById('late_date').value = dateInput.value || new Date().toISOString().slice(0,10); }
  function closeModal(){ overlay.style.display='none'; overlay.setAttribute('aria-hidden','true'); ['late_student','late_date','late_am_in','late_am_out','late_pm_in','late_pm_out'].forEach(id=>{const el=document.getElementById(id); if(el) el.value='';}); errEl.style.display='none'; errEl.textContent=''; }

  btnCreate.addEventListener('click', openModal);
  btnCancel.addEventListener('click', closeModal);
  overlay.addEventListener('click', function(e){ if (e.target === overlay) closeModal(); });

  function showError(msg){ errEl.style.display='block'; errEl.textContent=msg; }

  btnSave.addEventListener('click', async function(){
    errEl.style.display='none'; errEl.textContent='';
    const student = document.getElementById('late_student').value;
    const date = document.getElementById('late_date').value.trim();
    const am_in = document.getElementById('late_am_in').value.trim();
    const am_out = document.getElementById('late_am_out').value.trim();
    const pm_in = document.getElementById('late_pm_in').value.trim();
    const pm_out = document.getElementById('late_pm_out').value.trim();

    if (!student) return showError('Select a student.');
    if (!date) return showError('Select a date.');

    const amFilled = am_in !== '' || am_out !== '';
    const pmFilled = pm_in !== '' || pm_out !== '';
    const amComplete = am_in !== '' && am_out !== '';
    const pmComplete = pm_in !== '' && pm_out !== '';

    if (!amComplete && !pmComplete) {
      if (amFilled && (!am_in || !am_out)) return showError('If using AM fields, fill both Arrival and Departure.');
      if (pmFilled && (!pm_in || !pm_out)) return showError('If using PM fields, fill both Arrival and Departure.');
      return showError('Provide both AM times or both PM times.');
    }

    btnSave.disabled = true; btnSave.textContent = 'Saving...';
    try {
      const res = await fetch('office_head_action.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
          action: 'create_late',
          student_id: student,
          late_date: date,
          am_in: amComplete ? am_in : '',
          am_out: amComplete ? am_out : '',
          pm_in: pmComplete ? pm_in : '',
          pm_out: pmComplete ? pm_out : ''
        })
      });
      const j = await res.json();
      if (j && j.success) { closeModal(); fetchFor(dateInput.value); }
      else { showError(j.message || 'Save failed.'); btnSave.disabled=false; btnSave.textContent='Save'; }
    } catch(e){ console.error(e); showError('Request failed.'); btnSave.disabled=false; btnSave.textContent='Save'; }
  });
})();
</script>
</body>
</html>