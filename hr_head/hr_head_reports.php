<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit(); }

$uid = (int)($_SESSION['user_id'] ?? 0);
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, role FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i",$uid); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: []; $stmt->close();
$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'User';

function fetch_students($conn){
    $q = "
    SELECT s.student_id, s.first_name, s.last_name, s.college, s.course,
           s.hours_rendered, s.total_hours_required, s.status AS student_status,
           u.office_name AS office_name,
           oa.remarks AS app_remarks, oa.date_submitted
    FROM students s
    LEFT JOIN users u ON u.user_id = s.user_id
    LEFT JOIN (
        SELECT oa1.*
        FROM ojt_applications oa1
        JOIN (
            SELECT student_id, MAX(date_submitted) AS max_date
            FROM ojt_applications
            WHERE status = 'approved'
            GROUP BY student_id
        ) mx ON oa1.student_id = mx.student_id AND oa1.date_submitted = mx.max_date
        WHERE oa1.status = 'approved'
    ) oa ON oa.student_id = s.student_id
    ORDER BY s.last_name, s.first_name
    ";
    $res = $conn->query($q);
    $rows = [];
    if ($res) { while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
    return $rows;
}

function fetch_offices($conn){
    $rows = [];
    $res = $conn->query("SELECT office_id, office_name, current_limit, requested_limit, reason, status FROM offices ORDER BY office_name");
    if ($res) {
        $stmtCount = $conn->prepare("
            SELECT COUNT(DISTINCT oa.student_id) AS filled FROM ojt_applications oa
            WHERE (oa.office_preference1 = ? OR oa.office_preference2 = ?) AND oa.status = 'approved'
        ");
        while ($r = $res->fetch_assoc()){
            $id = (int)$r['office_id'];
            $stmtCount->bind_param("ii",$id,$id);
            $stmtCount->execute();
            $cnt = $stmtCount->get_result()->fetch_assoc();
            $filled = (int)($cnt['filled'] ?? 0);
            $cap = is_null($r['current_limit']) ? null : (int)$r['current_limit'];
            $available = is_null($cap) ? '—' : max(0, $cap - $filled);
            $rows[] = array_merge($r, ['filled'=>$filled, 'available'=>$available]);
        }
        $stmtCount->close();
        $res->free();
    }
    return $rows;
}

function fetch_moa($conn){
    $rows = [];
    $res = $conn->query("SELECT moa_id, school_name, moa_file, date_uploaded, validity_months FROM moa ORDER BY date_uploaded DESC");
    if ($res){ while ($r = $res->fetch_assoc()) $rows[] = $r; $res->free(); }
    return $rows;
}

function fmtDate($d){ if (!$d) return '-'; $dt = date_create($d); return $dt ? $dt->format('M j, Y') : '-'; }

$students = fetch_students($conn);
$offices = fetch_offices($conn);
$moa = fetch_moa($conn);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>HR - Reports</title>
<style>
  *{box-sizing:border-box;font-family:'Poppins',sans-serif}
  body{margin:0;background:#f7f8fc;display:flex;min-height:100vh}
  .sidebar{width:220px;background:#2f3850;color:#fff;display:flex;flex-direction:column;align-items:center;padding:28px 12px;gap:8px}
  .main{flex:1;padding:24px}
  .card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 6px 20px rgba(0,0,0,0.05)}
  .tabs{display:flex;gap:24px;border-bottom:2px solid #eef1f6;padding-bottom:12px;margin-bottom:16px}
  .tabs button{background:none;border:none;padding:10px 8px;cursor:pointer;font-weight:700;color:#2f3850;border-radius:6px}
  .tabs button.active{border-bottom:3px solid #2f3850}
  .controls{display:flex;gap:12px;align-items:center;margin-bottom:12px}
  input[type=text]{padding:10px;border:1px solid #ddd;border-radius:8px}
  .tbl{width:100%;border-collapse:collapse}
  .tbl th,.tbl td{padding:12px;border:1px solid #eee;text-align:left}
  .tbl thead th{background:#f4f6fb;font-weight:700}
  .badge{display:inline-block;background:#f0f2f6;padding:6px 10px;border-radius:16px;font-size:13px}
  .empty{padding:18px;text-align:center;color:#777}
  @media(max-width:900px){ .sidebar{display:none} .main{padding:12px} .tbl th,.tbl td{padding:8px} }
</style>
</head>
<body>
  <div class="sidebar" role="navigation" aria-label="Sidebar">
    <div style="text-align:center;margin-top:12px">
      <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" style="width:84px;height:84px;border-radius:50%;background:#cfd3db" alt="">
      <div style="margin-top:8px;font-weight:700"><?= htmlspecialchars($full_name ?: ($_SESSION['username'] ?? 'HR Head')) ?></div>
      <div style="font-size:13px;color:#bfc4d1"><?= htmlspecialchars($role_label) ?></div>
    </div>
    <nav style="margin-top:18px;width:100%;display:flex;flex-direction:column;gap:8px;padding:0 12px">
      <a href="hr_head_home.php" style="color:#fff;text-decoration:none;padding:10px;border-radius:20px">🏠 Home</a>
      <a href="hr_head_ojts.php" style="color:#fff;text-decoration:none;padding:10px;border-radius:20px">👥 OJTs</a>
      <a href="hr_head_dtr.php" style="color:#fff;text-decoration:none;padding:10px;border-radius:20px">🕒 DTR</a>
      <a href="hr_head_accounts.php" style="color:#fff;text-decoration:none;padding:10px;border-radius:20px">👤 Accounts</a>
      <a href="hr_head_reports.php" style="color:#fff;background:#fff;color:#2f3850;padding:10px;border-radius:20px">📊 Reports</a>
    </nav>
    <div style="margin-top:auto;font-weight:700">OJT-MS</div>
  </div>

  <main class="main" role="main">
    <div class="card" role="region" aria-label="Reports">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <h2 style="margin:0;color:#2f3850">Reports</h2>
        <div style="display:flex;gap:12px;align-items:center">
          <div id="globalSearchWrap"><input type="text" id="globalSearch" placeholder="Search" style="width:320px"></div>
          <button id="exportBtn" style="padding:8px 12px;border-radius:8px;border:1px solid #ddd;background:#fff;cursor:pointer">Export</button>
        </div>
      </div>

      <div class="tabs" role="tablist">
        <button class="active" data-tab="students">Students</button>
        <button data-tab="offices">Offices</button>
        <button data-tab="moa">MOA</button>
      </div>

      <div id="panel-students" class="panel" style="display:block">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblStudents">
            <thead>
              <tr>
                <th>Name</th><th>Office</th><th>School</th><th>Course</th><th>Start Date</th><th>End Date</th><th style="text-align:center">Hours Rendered</th><th style="text-align:center">Required Hours</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($students)): ?>
                <tr><td colspan="9" class="empty">No students found.</td></tr>
              <?php else: foreach ($students as $s):
                $name = trim(($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''));
                $office = $s['office_name'] ?: '-';
                $school = $s['college'] ?: '-';
                $course = $s['course'] ?: '-';
                $hours = (int)($s['hours_rendered'] ?? 0);
                $req = (int)($s['total_hours_required'] ?? 0);
                // try extract dates from remarks (Orientation/Start: YYYY-MM-DD | Assigned Office: ...)
                $start = $end = '';
                if (!empty($s['app_remarks']) && preg_match('/Orientation\/Start\s*:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i',$s['app_remarks'],$m)) $start = $m[1];
                if (!empty($s['app_remarks']) && preg_match('/(End Date|Expected End Date)\s*:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})/i',$s['app_remarks'],$m2)) $end = $m2[2];
              ?>
                <tr data-search="<?= htmlspecialchars(strtolower($name.' '.$office.' '.$school.' '.$course)) ?>">
                  <td><?= htmlspecialchars($name ?: 'N/A') ?></td>
                  <td><?= htmlspecialchars($office) ?></td>
                  <td><?= htmlspecialchars($school) ?></td>
                  <td><?= htmlspecialchars($course) ?></td>
                  <td><?= htmlspecialchars($start ? fmtDate($start) : '-') ?></td>
                  <td><?= htmlspecialchars($end ? fmtDate($end) : '-') ?></td>
                  <td style="text-align:center"><?= $hours ?></td>
                  <td style="text-align:center"><?= $req ?></td>
                  <td><?= htmlspecialchars(ucfirst($s['student_status'] ?: '')) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="panel-offices" class="panel" style="display:none">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblOffices">
            <thead>
              <tr><th>Office</th><th style="text-align:center">Capacity</th><th style="text-align:center">Active OJTs</th><th style="text-align:center">Available</th><th>Requested Limit</th><th>Reason</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php if (empty($offices)): ?>
                <tr><td colspan="7" class="empty">No offices found.</td></tr>
              <?php else: foreach ($offices as $o): ?>
                <tr data-search="<?= htmlspecialchars(strtolower($o['office_name'])) ?>">
                  <td><?= htmlspecialchars($o['office_name']) ?></td>
                  <td style="text-align:center"><?= is_null($o['current_limit']) ? '—' : (int)$o['current_limit'] ?></td>
                  <td style="text-align:center"><?= (int)$o['filled'] ?></td>
                  <td style="text-align:center"><?= htmlspecialchars((string)$o['available']) ?></td>
                  <td style="text-align:center"><?= $o['requested_limit'] === null ? '—' : (int)$o['requested_limit'] ?></td>
                  <td><?= htmlspecialchars($o['reason'] ?: '—') ?></td>
                  <td><?= htmlspecialchars($o['status'] ?: '—') ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="panel-moa" class="panel" style="display:none">
        <div style="overflow-x:auto">
          <table class="tbl" id="tblMoa">
            <thead>
              <tr><th>School</th><th>MOA File</th><th>Uploaded</th><th>Validity (months)</th></tr>
            </thead>
            <tbody>
              <?php if (empty($moa)): ?>
                <tr><td colspan="4" class="empty">No MOA records.</td></tr>
              <?php else: foreach ($moa as $m): ?>
                <tr data-search="<?= htmlspecialchars(strtolower($m['school_name'])) ?>">
                  <td><?= htmlspecialchars($m['school_name']) ?></td>
                  <td>
                    <?php if (!empty($m['moa_file'])): ?>
                      <a href="<?= htmlspecialchars('../' . $m['moa_file']) ?>" target="_blank">View</a>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($m['date_uploaded'] ? date('M j, Y', strtotime($m['date_uploaded'])) : '-') ?></td>
                  <td style="text-align:center"><?= (int)($m['validity_months'] ?? 0) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </main>

<script>
(function(){
  // tab switching
  document.querySelectorAll('.tabs button').forEach(btn=>{
    btn.addEventListener('click', function(){
      document.querySelectorAll('.tabs button').forEach(b=>b.classList.remove('active'));
      this.classList.add('active');
      const tab = this.getAttribute('data-tab');
      document.getElementById('panel-students').style.display = tab==='students' ? 'block' : 'none';
      document.getElementById('panel-offices').style.display = tab==='offices' ? 'block' : 'none';
      document.getElementById('panel-moa').style.display = tab==='moa' ? 'block' : 'none';
      document.getElementById('globalSearch').value = '';
    });
  });

  // global search applies to visible panel
  document.getElementById('globalSearch').addEventListener('input', function(){
    const q = (this.value||'').toLowerCase().trim();
    const visible = document.querySelector('.panel[style*="display:block"]');
    if (!visible) return;
    visible.querySelectorAll('tbody tr[data-search]').forEach(tr=>{
      tr.style.display = (tr.getAttribute('data-search')||'').indexOf(q)===-1 ? 'none' : '';
    });
  });

  // export visible table to CSV
  document.getElementById('exportBtn').addEventListener('click', function(){
    const visiblePanel = document.querySelector('.panel[style*="display:block"]');
    if (!visiblePanel) return alert('No data to export.');
    const rows = Array.from(visiblePanel.querySelectorAll('tbody tr')).filter(r=>r.style.display!=='none');
    if (rows.length === 0) return alert('No rows to export.');
    const cols = Array.from(visiblePanel.querySelectorAll('thead th')).map(th=>th.textContent.trim());
    const data = [cols.map(c => '"' + c.replace(/"/g,'""') + '"').join(',')];
    rows.forEach(tr=>{
      const cells = Array.from(tr.querySelectorAll('td')).map(td => '"' + td.textContent.replace(/"/g,'""').trim() + '"');
      data.push(cells.join(','));
    });
    const csv = data.join('\n');
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = 'reports_export.csv'; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  });
})();
</script>
</body>
</html>