<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

// --- MOVE: handle AJAX add MOA before any HTML output ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) && $_POST['action'] === 'add_moa')) {
    // collect and basic sanitize
    $school = trim((string)($_POST['school'] ?? ''));
    $date_signed = trim((string)($_POST['date_signed'] ?? ''));
    $valid_until = trim((string)($_POST['valid_until'] ?? ''));

    // validation: required fields
    $missing = [];
    if ($school === '') $missing[] = 'school';
    if ($date_signed === '') $missing[] = 'date_signed';
    if ($valid_until === '') $missing[] = 'valid_until';
    if (!empty($missing)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing)]);
        exit;
    }

    // validate dates
    try {
        $d1 = new DateTime($date_signed);
        $d2 = new DateTime($valid_until);
    } catch (Exception $e) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
        exit;
    }
    if ($d2 < $d1) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Valid Until must be the same or after Date Signed.']);
        exit;
    }

    // compute months difference (validity_months)
    $interval = $d1->diff($d2);
    $validity_months = (int)($interval->y * 12 + $interval->m + ($interval->d > 0 ? 1 : 0));
    if ($validity_months < 0) $validity_months = 0;

    // handle file upload (required)
    if (!isset($_FILES['moa_file']) || empty($_FILES['moa_file']['name'])) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please choose a file to upload.']);
        exit;
    }
    $moa_file_path = '';
    if ($_FILES['moa_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'File upload error.']);
        exit;
    }
    if (isset($_FILES['moa_file']) && $_FILES['moa_file']['error'] === UPLOAD_ERR_OK) {
         $uploadDir = __DIR__ . '/../uploads/moa/';
         if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
 
         $orig = basename($_FILES['moa_file']['name']);
         $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
         $allowed = ['pdf','jpg','jpeg','png'];
         if (!in_array($ext, $allowed)) {
             http_response_code(400); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Invalid file type. Allowed: pdf,jpg,jpeg,png']); exit;
         }
 
         // limit file size (5MB)
         if ($_FILES['moa_file']['size'] > 5 * 1024 * 1024) {
             http_response_code(400); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'File too large (max 5MB).']); exit;
         }
 
         $safe = preg_replace('/[^a-z0-9_\-]/i','_', pathinfo($orig, PATHINFO_FILENAME));
         $newName = $safe . '_' . time() . '.' . $ext;
         $dest = $uploadDir . $newName;
         if (move_uploaded_file($_FILES['moa_file']['tmp_name'], $dest)) {
             $moa_file_path = 'uploads/moa/' . $newName;
         } else {
             http_response_code(500); header('Content-Type: application/json'); echo json_encode(['success'=>false,'message'=>'Failed to move uploaded file.']); exit;
         }
     }

    // insert into DB
    $stmt = $conn->prepare("INSERT INTO moa (school_name, moa_file, date_uploaded, validity_months) VALUES (?,?,?,?)");
    $stmt->bind_param("sssi", $school, $moa_file_path, $date_signed, $validity_months);
    $ok = $stmt->execute();
    $insertId = $conn->insert_id;
    $err = $stmt->error;
    $stmt->close();

    if ($ok) {
        // compute students count
        $cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM students WHERE (college LIKE ? OR school_address LIKE ?)");
        $like = "%{$school}%";
        $cntStmt->bind_param("ss", $like, $like);
        $cntStmt->execute();
        $cntRow = $cntStmt->get_result()->fetch_assoc();
        $students = (int)($cntRow['cnt'] ?? 0);
        $cntStmt->close();

        $valid_until_calc = $date_signed ? date('Y-m-d', strtotime("+{$validity_months} months", strtotime($date_signed))) : null;
        $status = ($valid_until_calc && strtotime($valid_until_calc) >= strtotime(date('Y-m-d'))) ? 'ACTIVE' : 'EXPIRED';

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'moa' => [
            'moa_id' => (int)$insertId,
            'school_name' => $school,
            'moa_file' => $moa_file_path,
            'date_uploaded' => $date_signed,
            'valid_until' => $valid_until_calc,
            'students' => $students,
            'status' => $status
        ]]);
        exit;
    } else {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'message' => $err ?: 'Insert failed']);
        exit;
    }
}


// user info for sidebar
$uid = (int)($_SESSION['user_id'] ?? 0);
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, role FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i",$uid); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'HR Head';

// fetch MOA rows
$moas = [];
$res = $conn->query("SELECT moa_id, school_name, moa_file, date_uploaded, COALESCE(validity_months,12) AS validity_months FROM moa ORDER BY date_uploaded DESC");
if ($res) {
    $cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM students WHERE (college LIKE ? OR school_address LIKE ?)");
    while ($r = $res->fetch_assoc()) {
        $school = $r['school_name'] ?? '';
        $like = "%{$school}%";
        $cntStmt->bind_param("ss", $like, $like);
        $cntStmt->execute();
        $cntRow = $cntStmt->get_result()->fetch_assoc();
        $count = (int)($cntRow['cnt'] ?? 0);

        $date_uploaded = $r['date_uploaded'];
        $valid_until = $date_uploaded ? date('Y-m-d', strtotime("+{$r['validity_months']} months", strtotime($date_uploaded))) : null;
        $status = ($valid_until && strtotime($valid_until) >= strtotime(date('Y-m-d'))) ? 'ACTIVE' : 'EXPIRED';

        $moas[] = [
            'moa_id' => (int)$r['moa_id'],
            'school_name' => $school,
            'moa_file' => $r['moa_file'] ?? '',
            'date_uploaded' => $date_uploaded,
            'valid_until' => $valid_until,
            'validity_months' => (int)$r['validity_months'],
            'students' => $count,
            'status' => $status
        ];
    }
    $cntStmt->close();
    $res->free();
}

function fmtDate($d){ if (!$d) return '-'; $dt = date_create($d); return $dt ? date_format($dt,'M j, Y') : '-'; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HR - MOA</title>
<style>
  *{box-sizing:border-box;font-family:'Poppins',sans-serif}
  body{margin:0;background:#f7f8fc;display:flex;min-height:100vh}
  .sidebar{width:220px;background:#2f3850;color:#fff;display:flex;flex-direction:column;align-items:center;padding:28px 12px;gap:8px}
  .profile{ text-align:center; margin-top:12px; padding:8px 0; }
  .profile img{ width:90px; height:90px; border-radius:50%; object-fit:cover; display:block; margin:0 auto 10px; background:#cfd3db; }
  .profile h3{ margin:0; font-size:16px; color:#fff; font-weight:700; }
  .profile p{ margin:0; font-size:13px; color:#bfc4d1; }
  .nav{display:flex;flex-direction:column;gap:10px;width:100%;margin-top:18px}
  .nav a{display:block;padding:10px 16px;color:#fff;text-decoration:none;border-radius:22px;margin:0 8px;font-weight:600}
  .nav a.active, .nav a:hover{background:#fff;color:#2f3850}
  .main{flex:1;padding:24px}
  .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(0,0,0,0.05)}
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  input[type=text]{padding:10px;border:1px solid #ddd;border-radius:8px}
  .tbl{width:100%;border-collapse:collapse}
  .tbl th,.tbl td{padding:12px;border:1px solid #eee;text-align:left}
  .tbl thead th{background:#f4f6fb;font-weight:700}
  .badge{display:inline-block;background:#f0f2f6;padding:6px 10px;border-radius:16px;font-size:13px}
  .empty{padding:18px;text-align:center;color:#777}
  .status-active{color:#0b7a3a;font-weight:700}
  .status-expired{color:#a00;font-weight:700}
  /* modal styles for Add MOA */
  .modal-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,0.35); display:none; align-items:center; justify-content:center; z-index:1200; }
  .modal-backdrop.show{ display:flex; pointer-events:auto; }
  .modal{ background:#fff; width:360px; max-width:92%; border-radius:16px; padding:18px; box-shadow:0 12px 40px rgba(0,0,0,0.18); }
  .modal h3{ margin:0 0 12px 0; font-size:18px; }
  .form-row{ margin-bottom:10px; }
  .form-row label{ display:block; font-size:13px; color:#333; margin-bottom:6px; }
  .form-row input[type="text"], .form-row input[type="date"], .form-row input[type="file"] { width:100%; padding:8px 10px; border-radius:8px; border:1px solid #ddd; }
  .modal-actions{ display:flex; justify-content:flex-end; gap:8px; margin-top:10px; }
  .btn-ghost{ background:#fff; border:1px solid #ddd; padding:8px 12px; border-radius:8px; cursor:pointer; }
  .btn-primary{ background:#2f3850; color:#fff; border:none; padding:8px 12px; border-radius:8px; cursor:pointer; }
  @media(max-width:900px){ .sidebar{display:none} .main{padding:12px} .tbl th,.tbl td{padding:8px} }
</style>
</head>
<body>
  <div class="sidebar" role="navigation" aria-label="Sidebar">
    <div class="profile">
      <!-- use local asset or CDN; keep size controlled by .profile img -->
      <img src="<?= htmlspecialchars('../assets/img/avatar.png') ?>" alt="Profile">
      <h3><?= htmlspecialchars($full_name ?: 'HR Head') ?></h3>
      <p><?= htmlspecialchars($role_label) ?></p>
    </div>
    <nav class="nav" aria-label="Main navigation">
      <a href="hr_head_home.php">🏠 Home</a>
      <a href="hr_head_ojts.php">👥 OJTs</a>
      <a href="hr_head_dtr.php">🕒 DTR</a>
      <a href="hr_head_moa.php" class="active">📄 MOA</a>
      <a href="hr_head_accounts.php">👤 Accounts</a>
      <a href="hr_head_reports.php">📊 Reports</a>
    </nav>
    <div style="margin-top:auto;font-weight:700">OJT-MS</div>
  </div>

  <main class="main" role="main">
    <div class="card" role="region" aria-label="MOA">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h2 style="margin:0;color:#2f3850">MOA</h2>
        <div style="display:flex;gap:12px;align-items:center">
          <input type="text" id="search" placeholder="Search school" style="width:320px">
          <button id="btnExport" style="padding:8px 12px;border-radius:8px;border:1px solid #ddd;background:#fff;cursor:pointer">Export</button>
          <button id="btnAdd" style="padding:8px 12px;border-radius:8px;border:1px solid #2f3850;background:#2f3850;color:#fff;cursor:pointer">+ Add</button>
        </div>
      </div>

      <div style="overflow-x:auto">
        <table class="tbl" id="tblMoa">
          <thead>
            <tr>
              <th style="text-align:center">Students</th>
              <th>School Name</th>
              <th>MOA Status</th>
              <th>Date Signed</th>
              <th>Valid Until</th>
              <th>Uploaded Copy</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($moas)): ?>
              <tr><td colspan="6" class="empty">No MOA records.</td></tr>
            <?php else: foreach ($moas as $m): ?>
              <tr data-search="<?= htmlspecialchars(strtolower($m['school_name'])) ?>">
                <td style="text-align:center"><?= (int)$m['students'] ?></td>
                <td><?= htmlspecialchars($m['school_name'] ?: '—') ?></td>
                <td><?= $m['status'] === 'ACTIVE' ? "<span class=\"status-active\">ACTIVE</span>" : "<span class=\"status-expired\">EXPIRED</span>" ?></td>
                <td><?= htmlspecialchars($m['date_uploaded'] ? fmtDate($m['date_uploaded']) : '-') ?></td>
                <td><?= htmlspecialchars($m['valid_until'] ? fmtDate($m['valid_until']) : '-') ?></td>
                <td>
                  <?php if (!empty($m['moa_file'])): ?>
                    <a href="<?= htmlspecialchars('../' . $m['moa_file']) ?>" target="_blank"><?= htmlspecialchars(basename($m['moa_file'])) ?></a>
                  <?php else: ?>—<?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div> <!-- end overflow-x wrapper -->

      <!-- Add MOA modal -->
      <div class="modal-backdrop" id="moaModalBackdrop" aria-hidden="true">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
          <h3 id="modalTitle">Add MOA</h3>
          <form id="moaForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_moa">
            <div class="form-row">
              <label>School</label>
              <input type="text" name="school" required placeholder="School name">
            </div>
            <div class="form-row">
              <label>Date Signed</label>
              <input type="date" name="date_signed" required>
            </div>
            <div class="form-row">
              <label>Valid Until</label>
              <input type="date" name="valid_until" required>
            </div>
            <div class="form-row">
              <label>Upload a copy (pdf)</label>
              <input type="file" name="moa_file" accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <div class="modal-actions">
              <button type="button" class="btn-ghost" id="moaCancel">Cancel</button>
              <button type="submit" class="btn-primary">SEND</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </main>

<script>
(function(){
  const search = document.getElementById('search');
  search.addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    document.querySelectorAll('#tblMoa tbody tr[data-search]').forEach(tr=>{
      tr.style.display = (tr.getAttribute('data-search')||'').indexOf(q) === -1 ? 'none' : '';
    });
  });

  document.getElementById('btnExport').addEventListener('click', function(){
    const rows = Array.from(document.querySelectorAll('#tblMoa tbody tr')).filter(tr=>tr.style.display!=='none');
    if (rows.length === 0) { alert('No rows to export'); return; }
    const cols = ['Students','School Name','MOA Status','Date Signed','Valid Until','Uploaded Copy'];
    const data = [cols.join(',')];
    rows.forEach(tr=>{
      const cells = Array.from(tr.querySelectorAll('td')).map(td => '"' + td.textContent.replace(/"/g,'""').trim() + '"');
      data.push(cells.join(','));
    });
    const csv = data.join('\n');
    const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'moa_list.csv'; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  });

  // modal helpers (use class .show to avoid hidden element blocking clicks)
  const backdrop = document.getElementById('moaModalBackdrop');
  const btnAdd = document.getElementById('btnAdd');
  const btnCancel = document.getElementById('moaCancel');
  const form = document.getElementById('moaForm');
  function showModal(){ backdrop.classList.add('show'); backdrop.setAttribute('aria-hidden','false'); }
  function hideModal(){ backdrop.classList.remove('show'); backdrop.setAttribute('aria-hidden','true'); form.reset(); }
  btnAdd.addEventListener('click', function(e){ e.preventDefault(); showModal(); });
  btnCancel.addEventListener('click', function(e){ e.preventDefault(); hideModal(); });
  backdrop.addEventListener('click', function(e){ if (e.target === backdrop) hideModal(); });

  document.getElementById('moaForm').addEventListener('submit', function(e){
    e.preventDefault();
    const form = this;

    // client-side required validation (works with HTML required attributes)
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    // date validation: valid_until must be same or after date_signed
    const dateSigned = form.querySelector('[name="date_signed"]').value;
    const validUntil = form.querySelector('[name="valid_until"]').value;
    if (dateSigned && validUntil && (new Date(validUntil) < new Date(dateSigned))) {
      alert('Valid Until must be the same or after Date Signed.');
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn ? submitBtn.textContent : 'SEND';
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Sending...'; }

    const formData = new FormData(form);
    fetch('', { method: 'POST', body: formData })
    .then(response => response.text())
    .then(text => {
      try {
        const data = JSON.parse(text);
        if (data.success && data.moa) {
          // update table (same logic you already have)
          const tblBody = document.querySelector('#tblMoa tbody');
          if (tblBody.querySelector('td.empty')) tblBody.innerHTML = '';

          const formatDate = (d) => {
            if (!d) return '-';
            const dt = new Date(d); if (isNaN(dt)) return '-';
            return dt.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
          };

          const m = data.moa;
          const students = Number(m.students || 0);
          const school = m.school_name || '—';
          const statusClass = (m.status === 'ACTIVE') ? 'status-active' : 'status-expired';
          const dateUploaded = m.date_uploaded ? formatDate(m.date_uploaded) : '-';
          const validUntilFmt = m.valid_until ? formatDate(m.valid_until) : '-';
          const fileHtml = m.moa_file ? `<a href="../${m.moa_file}" target="_blank">${m.moa_file.split('/').pop()}</a>` : '—';

          const newRow = document.createElement('tr');
          newRow.setAttribute('data-search', (school || '').toLowerCase());
          newRow.innerHTML = `
            <td style="text-align:center">${students}</td>
            <td>${school}</td>
            <td><span class="${statusClass}">${m.status}</span></td>
            <td>${dateUploaded}</td>
            <td>${validUntilFmt}</td>
            <td>${fileHtml}</td>
          `;
          tblBody.insertBefore(newRow, tblBody.firstChild);

          // confirmation and close modal
          alert('MOA added successfully');
          hideModal();
        } else {
          alert(data.message || 'Error adding MOA');
        }
      } catch (err) {
        console.error('Server returned non-JSON:', text);
        alert('Server error. Check PHP error log or Network response.');
      }
    })
    .catch(err => { console.error(err); alert('Error processing request'); })
    .finally(() => {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalBtnText; }
    });
  });
})();
</script>
</body>
</html>