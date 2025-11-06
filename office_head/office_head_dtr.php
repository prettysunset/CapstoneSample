<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$user_id = (int)$_SESSION['user_id'];

$su = $conn->prepare("SELECT first_name,last_name,office_name FROM users WHERE user_id=? LIMIT 1");
$su->bind_param('i',$user_id);
$su->execute();
$u=$su->get_result()->fetch_assoc();
$su->close();

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
  .main{margin-left:240px;padding:20px}
  .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(0,0,0,0.05)}
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  .btn{padding:10px 14px;border-radius:20px;border:0;background:#4f4aa6;color:#fff;cursor:pointer}
  .late-modal-overlay{position:fixed;inset:0;background:rgba(15,15,20,0.45);display:none;align-items:center;justify-content:center;z-index:2200}
  .late-modal{width:420px;background:#fff;border-radius:12px;padding:16px;box-shadow:0 12px 40px rgba(0,0,0,0.12)}
  .late-modal label{display:block;font-size:13px;margin:6px 0}
  .late-modal input[type="date"], .late-modal input[type="time"], .late-modal select{width:100%;padding:8px;border-radius:8px;border:1px solid #e6e9f2;box-sizing:border-box}
  .late-modal .actions{display:flex;gap:10px;justify-content:flex-end;margin-top:10px}
  .late-modal .error{color:#a00;font-size:13px;margin-top:6px;display:none}
  .tabs{display:flex;gap:24px;border-bottom:2px solid #eee;padding-bottom:12px;margin-bottom:16px;position:relative}
  .tabs .tab{background:transparent;border:none;padding:10px 12px;border-radius:6px;cursor:pointer;font-weight:600;color:#2f3850}
  .tabs .tab.active{color:#2f3850}
  .tab-underline{position:absolute;bottom:0;height:3px;background:#2f3850;border-radius:3px;transition:left .18s ease,width .18s ease;left:0;width:0}
  table{width:100%;border-collapse:collapse}
  th,td{padding:12px;text-align:left;border-bottom:1px solid #eef1f6;font-size:14px}
  thead th{background:#f5f7fb;color:#2f3459}
  @media(max-width:900px){ .sidebar{display:none} .main{padding:12px} }
  
</style>
</head>
<body>
<?php
// ensure we have a display name available
$user_name = $display_name ?? 'Office Head';
?>
<div class="sidebar">
  <div style="text-align:center;padding:18px 12px 8px;">
    <div style="width:64px;height:64px;border-radius:50%;background:#fff;color:#2f3459;display:inline-flex;align-items:center;justify-content:center;font-weight:700;margin:6px auto;font-size:20px;">
      <?= htmlspecialchars(mb_strtoupper(substr(trim($user_name),0,1) ?: 'O')) ?>
    </div>
    <h3 style="margin:8px 0 4px;font-size:16px;"><?= htmlspecialchars($user_name) ?></h3>
    <p style="margin:0;font-size:13px;opacity:0.9">Office Head — <?= htmlspecialchars($office_display) ?></p>
  </div>

  <nav class="nav" style="margin-top:14px;display:flex;flex-direction:column;gap:8px;padding:0 12px;">
    <a href="office_head_home.php" title="Home" style="display:flex;align-items:center;gap:8px;color:#fff;background:transparent;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 11.5L12 4l9 7.5"></path>
        <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
      </svg>
      <span>Home</span>
    </a>

    <a href="office_head_ojts.php" title="OJTs" style="display:flex;align-items:center;gap:8px;color:#fff;background:transparent;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="8" r="3"></circle>
        <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
      </svg>
      <span>OJTs</span>
    </a>

    <a href="office_head_dtr.php" class="active" title="DTR" style="display:flex;align-items:center;gap:8px;color:#2f3859;background:#fff;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="4" width="18" height="18" rx="2"></rect>
      <line x1="16" y1="2" x2="16" y2="6"></line>
      <line x1="8" y1="2" x2="8" y2="6"></line>
      <line x1="3" y1="10" x2="21" y2="10"></line>
      </svg>
      <span>DTR</span>
    </a>

    <a href="office_head_reports.php" title="Reports" style="display:flex;align-items:center;gap:8px;color:#fff;background:transparent;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="10" width="4" height="10"></rect>
        <rect x="10" y="6" width="4" height="14"></rect>
        <rect x="17" y="2" width="4" height="18"></rect>
      </svg>
      <span>Reports</span>
    </a>

  </nav>
</div>

<div class="main">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      
      <div style="display:flex;gap:12px;align-items:center">
        <input id="datePicker" type="date" value="<?= date('Y-m-d') ?>" />
        <button id="btnCreateLate" class="btn">Create Late DTR</button>
      </div>
    </div>

    <div class="tabs" role="tablist" aria-label="DTR Tabs">
      <button class="tab active" data-tab="daily" aria-selected="true">Daily Logs</button>
      <button class="tab" data-tab="late" aria-selected="false">Late DTR Submissions</button>
      <div class="tab-underline" aria-hidden="true"></div>
    </div>

    <div id="panel-daily" class="panel" style="display:block">
      <div class="controls" style="margin-bottom:8px">
        <input id="searchDaily" type="text" placeholder="Search name / school / course" style="flex:1;padding:10px;border-radius:8px;border:1px solid #ddd" />
      </div>
      <div style="overflow:auto">
        <table id="dailyTable">
          <thead>
            <tr>
              <th>DATE</th>
              <th>NAME</th>
              <th>SCHOOL</th>
              <th>COURSE</th>
              <th>A.M. ARRIVAL</th>
              <th>A.M. DEPARTURE</th>
              <th>P.M. ARRIVAL</th>
              <th>P.M. DEPARTURE</th>
              <th>HOURS</th>
              <th>STATUS</th>
            </tr>
          </thead>
          <tbody id="dtrBody">
            <tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <div id="panel-late" class="panel" style="display:none">
      <div class="controls" style="margin-bottom:8px">
        <input id="lateSearch" type="text" placeholder="Search late submissions" style="flex:1;padding:10px;border-radius:8px;border:1px solid #ddd" />
      </div>
      <div style="overflow:auto">
        <table id="lateTable">
          <thead>
            <tr>
              <th>Date Filed</th>
              <th>Name</th>
              <th>School</th>
              <th>Course</th>
              <th>AM In</th>
              <th>AM Out</th>
              <th>PM In</th>
              <th>PM Out</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="lateBody">
            <tr><td colspan="9" style="text-align:center;color:#8a8f9d;padding:18px">Loading…</td></tr>
          </tbody>
        </table>
      </div>
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
  // tabs underline and switching
  const tabs = Array.from(document.querySelectorAll('.tabs .tab'));
  const underline = document.querySelector('.tab-underline');
  function updateUnderline(){
    const active = document.querySelector('.tabs .tab.active') || tabs[0];
    if(!active || !underline) return;
    const parentRect = document.querySelector('.tabs').getBoundingClientRect();
    const rect = active.getBoundingClientRect();
    underline.style.left = (rect.left - parentRect.left) + 'px';
    underline.style.width = rect.width + 'px';
  }
  tabs.forEach(t=>{
    t.addEventListener('click', ()=>{
      tabs.forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      const tab = t.getAttribute('data-tab');
      document.querySelectorAll('.panel').forEach(p=>p.style.display = p.id === 'panel-'+tab ? 'block' : 'none');
      updateUnderline();
    });
  });
  window.addEventListener('load', updateUnderline);
  window.addEventListener('resize', updateUnderline);

  // elements
  const datePicker = document.getElementById('datePicker');
  const dtrBody = document.getElementById('dtrBody');
  const lateBody = document.getElementById('lateBody');
  const searchDaily = document.getElementById('searchDaily');
  const lateSearch = document.getElementById('lateSearch');

  const overlay = document.getElementById('lateModalOverlay');
  const btnCreate = document.getElementById('btnCreateLate');
  const btnCancel = document.getElementById('lateCancel');
  const btnSave = document.getElementById('lateUpload');
  const errEl = document.getElementById('lateError');

  function renderDaily(rows){
    dtrBody.innerHTML = '';
    if (!rows || rows.length === 0) {
      dtrBody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">No records found.</td></tr>';
      return;
    }
    rows.forEach(r=>{
      const tr = document.createElement('tr');
      tr.setAttribute('data-search', ((r.first_name||'')+' '+(r.last_name||'')+' '+(r.school||'')+' '+(r.course||'')).toLowerCase());
      tr.innerHTML = '<td>'+ (r.log_date||'') +'</td>'
                   + '<td>'+ (r.first_name||'') + ' ' + (r.last_name||'') +'</td>'
                   + '<td>'+ (r.school||'-') +'</td>'
                   + '<td>'+ (r.course||'-') +'</td>'
                   + '<td>'+ (r.am_in||'-') +'</td>'
                   + '<td>'+ (r.am_out||'-') +'</td>'
                   + '<td>'+ (r.pm_in||'-') +'</td>'
                   + '<td>'+ (r.pm_out||'-') +'</td>'
                   + '<td>'+ (r.hours||'-') +'</td>'
                   + '<td>'+ (r.status||'-') +'</td>';
      dtrBody.appendChild(tr);
    });
  }

  function renderLate(rows){
    lateBody.innerHTML = '';
    if (!rows || rows.length === 0) {
      lateBody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#8a8f9d;padding:18px">No records found.</td></tr>';
      return;
    }
    rows.forEach(r=>{
      const tr = document.createElement('tr');
      tr.setAttribute('data-search', ((r.first_name||'')+' '+(r.last_name||'')+' '+(r.school||'')+' '+(r.course||'')).toLowerCase());
      tr.innerHTML = '<td>'+ (r.date_filed||'') +'</td>'
                   + '<td>'+ (r.first_name||'') + ' ' + (r.last_name||'') +'</td>'
                   + '<td>'+ (r.school||'-') +'</td>'
                   + '<td>'+ (r.course||'-') +'</td>'
                   + '<td>'+ (r.am_in||'-') +'</td>'
                   + '<td>'+ (r.am_out||'-') +'</td>'
                   + '<td>'+ (r.pm_in||'-') +'</td>'
                   + '<td>'+ (r.pm_out||'-') +'</td>'
                   + '<td>'+ (r.status||'-') +'</td>';
      lateBody.appendChild(tr);
    });
  }

  async function fetchDaily(date){
    dtrBody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:#8a8f9d;padding:18px">Loading…</td></tr>';
    try {
      const res = await fetch('office_head_action.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'get_daily_logs', date: date })
      });
      const j = await res.json();
      if (j && j.success) renderDaily(j.data || []); else renderDaily([]);
    } catch(e){ console.error(e); renderDaily([]); }
  }

  async function fetchLate(date){
    lateBody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:#8a8f9d;padding:18px">Loading…</td></tr>';
    try {
      const res = await fetch('office_head_action.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action: 'get_late_dtr', date: date })
      });
      const j = await res.json();
      if (j && j.success) renderLate(j.data || []); else renderLate([]);
    } catch(e){ console.error(e); renderLate([]); }
  }

  // search filters
  searchDaily.addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    document.querySelectorAll('#dtrBody tr').forEach(r=> r.style.display = (r.getAttribute('data-search')||'').indexOf(q)===-1 ? 'none' : '');
  });
  lateSearch.addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    document.querySelectorAll('#lateBody tr').forEach(r=> r.style.display = (r.getAttribute('data-search')||'').indexOf(q)===-1 ? 'none' : '');
  });

  // init fetch on date change
  datePicker.addEventListener('change', ()=>{ fetchDaily(datePicker.value); fetchLate(datePicker.value); });
  // initial
  fetchDaily(datePicker.value);
  fetchLate(datePicker.value);

  // modal logic (same as before)
  function openModal(){ errEl.style.display='none'; errEl.textContent=''; overlay.style.display='flex'; overlay.setAttribute('aria-hidden','false'); document.getElementById('late_date').value = datePicker.value || new Date().toISOString().slice(0,10); }
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
      if (j && j.success) { closeModal(); fetchDaily(datePicker.value); fetchLate(datePicker.value); }
      else { showError(j.message || 'Save failed.'); btnSave.disabled=false; btnSave.textContent='Save'; }
    } catch(e){ console.error(e); showError('Request failed.'); btnSave.disabled=false; btnSave.textContent='Save'; }
  });

})();
</script>
</body>
</html>