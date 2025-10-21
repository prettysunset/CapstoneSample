<?php
session_start();
date_default_timezone_set('Asia/Manila');

// require DB connection (conn.php used elsewhere in project)
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// ensure we have user's name; prefer session but fallback to users table
$user_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if ($user_name === '') {
    $su = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $su->bind_param("i", $user_id);
    $su->execute();
    $ur = $su->get_result()->fetch_assoc();
    $su->close();
    if ($ur) $user_name = trim(($ur['first_name'] ?? '') . ' ' . ($ur['last_name'] ?? ''));
}
if ($user_name === '') $user_name = 'Office Head';

// find the office assigned to this office head via office_heads -> offices
$office = null;
$s = $conn->prepare("
    SELECT o.* 
    FROM office_heads oh
    JOIN offices o ON oh.office_id = o.office_id
    WHERE oh.user_id = ?
    LIMIT 1
");
$s->bind_param("i", $user_id);
$s->execute();
$office = $s->get_result()->fetch_assoc();
$s->close();

// fallback: try to find office by users.office_name if office_heads row missing
if (!$office) {
    $su = $conn->prepare("SELECT office_name FROM users WHERE user_id = ? LIMIT 1");
    $su->bind_param("i", $user_id);
    $su->execute();
    $urow = $su->get_result()->fetch_assoc();
    $su->close();
    if (!empty($urow['office_name'])) {
        $office_name = $urow['office_name'];
        $q = $conn->prepare("SELECT * FROM offices WHERE office_name LIKE ? LIMIT 1");
        $like = "%{$office_name}%";
        $q->bind_param("s", $like);
        $q->execute();
        $office = $q->get_result()->fetch_assoc();
        $q->close();
    }
}

// safe defaults if no office found
if (!$office) {
    $office = [
        'office_id' => 0,
        'office_name' => 'Unknown Office',
        'current_limit' => 0,
        'requested_limit' => 0,
        'reason' => '',
        'status' => 'open'
    ];
}

// helper: return short display name (remove trailing " Office")
function short_office_name($name) {
    if (empty($name)) return '';
    // remove trailing " Office" (case-insensitive) and trim
    return preg_replace('/\s+Office\s*$/i', '', trim($name));
}

// display-only name
$office_display = short_office_name($office['office_name'] ?? 'Unknown Office');

// counts (use correct role/status values from your schema)
$office_name_for_query = $office['office_name'] ?? '';

// Active OJTs - users.role = 'ojt', users.status = 'active'
$active_ojts = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND status = 'active' AND office_name = ?");
$stmt->bind_param("s", $office_name_for_query);
$stmt->execute();
$active_ojts = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Completed OJTs - if you track completed in students table use students.status = 'completed' else users.status
$completed_ojts = 0;
$s2 = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = 'ojt' AND status = 'inactive' AND office_name = ?");
$s2->bind_param("s", $office_name_for_query);
$s2->execute();
$completed_ojts = (int)$s2->get_result()->fetch_assoc()['total'];
$s2->close();

// Pending student applications (if table exists)
$pending_students = 0;
// check if table exists to avoid exception on environments without that table
$tblCheck = $conn->query("SHOW TABLES LIKE 'student_applications'");
if ($tblCheck && $tblCheck->num_rows > 0) {
    $s3 = $conn->prepare("SELECT COUNT(*) AS total FROM student_applications WHERE status = 'Pending' AND office_name = ?");
    $s3->bind_param("s", $office_name_for_query);
    $s3->execute();
    $pending_students = (int)$s3->get_result()->fetch_assoc()['total'];
    $s3->close();
} else {
    $pending_students = 0;
}

// Pending office requests for this office_id
$pending_office = 0;
$office_id = (int)($office['office_id'] ?? 0);
$s4 = $conn->prepare("SELECT COUNT(*) AS total FROM office_requests WHERE status = 'Pending' AND office_id = ?");
$s4->bind_param("i", $office_id);
$s4->execute();
$pending_office = (int)$s4->get_result()->fetch_assoc()['total'];
$s4->close();

// Fetch recent DTR rows for users in this office (most recent 20)
$late_dtr = $conn->prepare("
    SELECT u.first_name, u.last_name, d.am_in, d.am_out, d.pm_in, d.pm_out, d.hours
    FROM dtr d
    JOIN users u ON d.student_id = u.user_id
    WHERE u.office_name = ?
    ORDER BY d.log_date DESC
    LIMIT 20
");
$late_dtr->bind_param("s", $office_name_for_query);
$late_dtr->execute();
$late_dtr_res = $late_dtr->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Office Head | OJT-MS</title>
<style>
    body {
        font-family: 'Poppins', sans-serif;
        margin: 0;
        background-color: #f5f6fa;
    }
    .sidebar {
        width: 220px;
        background-color: #2f3459;
        height: 100vh;
        color: white;
        position: fixed;
        padding-top: 30px;
    }
    .sidebar h3 {
        text-align: center;
        margin-bottom: 5px;
    }
    .sidebar p {
        text-align: center;
        font-size: 14px;
        margin-top: 0;
    }
    .sidebar a {
        display: block;
        padding: 10px 20px;
        margin: 10px;
        color: black;
        background: white;
        border-radius: 20px;
        text-decoration: none;
    }
    .sidebar a.active {
        background-color: #b3b7d6;
    }
    .main {
        margin-left: 240px;
        padding: 20px;
    }
    .cards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
    }
    .card {
        background: #dcdff5;
        padding: 15px;
        border-radius: 15px;
        text-align: center;
    }
    .card h2 { margin: 0; }
    .table-section {
        margin-top: 30px;
        background: white;
        border-radius: 15px;
        padding: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        text-align: center;
    }
    th, td {
        border: 1px solid #ccc;
        padding: 8px;
    }
    th {
        background-color: #f1f1f1;
    }
    .edit-section {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        margin-top: 10px;
    }
    .edit-section input {
        text-align: center;
        border: 1px solid #ccc;
        padding: 5px;
        border-radius: 5px;
    }
</style>
</head>
<body>

<div class="sidebar">
    <h3><?= htmlspecialchars($user_name) ?></h3>
    <p>Office Head - <?= htmlspecialchars($office_display) ?></p>
    <a href="#" class="active">🏠 Home</a>
    <a href="#">👥 OJT</a>
    <a href="#">📊 Reports</a>
    <h3 style="position:absolute; bottom:20px; width:100%; text-align:center;">OJT-MS</h3>
</div>

<div class="main">
    <div class="cards">
        <div class="card">
            <p>Active OJTs</p>
            <h2><?= $active_ojts ?></h2>
        </div>
        <div class="card">
            <p>Completed OJTs</p>
            <h2><?= $completed_ojts ?></h2>
        </div>
        <div class="card">
            <p>Pending Student Applications</p>
            <h2><?= $pending_students ?></h2>
        </div>
        <div class="card">
            <p>Pending Office Request</p>
            <h2><?= $pending_office ?></h2>
        </div>
    </div>

    <div class="table-section">
        <div style="display:flex;align-items:center;justify-content:space-between">
            <!-- keep only the Edit button (no heading text) -->
            <div></div>
            <button id="btnEditOffice" style="padding:6px 10px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer">Edit</button>
        </div>

        <!-- Office Information table with headers -->
        <div style="margin-top:12px; overflow-x:auto;">
          <table style="width:100%; border-collapse:collapse; text-align:center;">
            <thead>
              <tr>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Current Limit</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Active OJTs</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Available Slots</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Requested Limit</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Reason</th>
                <th style="padding:8px; background:#f7f7f7; border:1px solid #e0e0e0;">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <input id="ci_current_limit" type="text" value="<?= htmlspecialchars($office['current_limit']) ?>" readonly style="width:70px;border:0;background:transparent;text-align:center;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <input id="ci_active_ojts" type="text" value="<?= $active_ojts ?>" readonly style="width:70px;border:0;background:transparent;text-align:center;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <input id="ci_available_slots" type="text" value="<?= max((int)$office['current_limit'] - $active_ojts, 0) ?>" readonly style="width:70px;border:0;background:transparent;text-align:center;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <input id="ci_requested_limit" type="text" value="<?= htmlspecialchars($office['requested_limit']) ?>" readonly style="width:90px;border:0;background:transparent;text-align:center;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0; max-width:300px;">
                  <input id="ci_reason" type="text" value="<?= htmlspecialchars($office['reason']) ?>" readonly style="width:100%;border:0;background:transparent;text-align:left;">
                </td>
                <td style="padding:8px; border:1px solid #e0e0e0;">
                  <input id="ci_status" type="text" value="<?= ucfirst($office['status']) ?>" readonly style="width:90px;border:0;background:transparent;text-align:center;">
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Edit Modal (unchanged) -->
        <div id="officeModal" style="display:none;position:fixed;left:0;top:0;right:0;bottom:0;background:rgba(0,0,0,0.35);align-items:center;justify-content:center;">
            <div style="background:#fff;padding:18px;border-radius:8px;width:420px;box-shadow:0 8px 30px rgba(0,0,0,0.12);">
                <h4 style="margin:0 0 8px 0">Request Change - <?= htmlspecialchars($office_display) ?></h4>
                <div style="display:grid;gap:8px;margin-top:8px">
                    <label>Current Limit <input id="m_current_limit" readonly style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></label>
                    <label>Active OJTs <input id="m_active_ojts" readonly style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></label>
                    <label>Available Slots <input id="m_available_slots" readonly style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></label>
                    <label>Requested Limit <input id="m_requested_limit" type="number" min="0" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></label>
                    <label>Reason <textarea id="m_reason" rows="3" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ddd"></textarea></label>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px">
                        <button id="m_cancel" style="padding:8px 10px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer">Cancel</button>
                        <button id="m_request" style="padding:8px 12px;border-radius:6px;border:none;background:#5b5f89;color:#fff;cursor:pointer">Request</button>
                    </div>
                </div>
            </div>
        </div>
        <input type="hidden" id="oh_office_id" value="<?= (int)$office['office_id'] ?>">
    </div>

    <div class="table-section">
        <div style="display:flex;align-items:center;justify-content:space-between">
            <h3>Late DTR Submissions</h3>
            <div style="display:flex;align-items:center;gap:8px">
                <!-- date picker (native calendar icon used by browser) -->
                <input id="lateDate" type="date" value="<?= date('Y-m-d') ?>" style="padding:6px;border-radius:6px;border:1px solid #ddd;">
            </div>
        </div>

        <!-- add explicit id's so JS updates only this table -->
        <table id="lateDtrTable">
            <thead>
              <tr>
                <th>NAME</th>
                <th colspan="2">A.M.</th>
                <th colspan="2">P.M.</th>
                <th>HOURS</th>
                <th>STATUS</th>
              </tr>
              <tr>
                <th></th>
                <th>ARRIVAL</th>
                <th>DEPARTURE</th>
                <th>ARRIVAL</th>
                <th>DEPARTURE</th>
                <th></th>
                <th></th>
              </tr>
            </thead>
            <tbody id="lateDtrTbody">
            <?php while ($row = $late_dtr_res->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                <td><?= $row['am_in'] ? htmlspecialchars(date('H:i', strtotime($row['am_in']))) : '' ?></td>
                <td><?= $row['am_out'] ? htmlspecialchars(date('H:i', strtotime($row['am_out']))) : '' ?></td>
                <td><?= $row['pm_in'] ? htmlspecialchars(date('H:i', strtotime($row['pm_in']))) : '' ?></td>
                <td><?= $row['pm_out'] ? htmlspecialchars(date('H:i', strtotime($row['pm_out']))) : '' ?></td>
                <td><?= htmlspecialchars($row['hours']) ?></td>
                <td><?= (!empty($row['hours']) ? 'Validated' : '') ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <script>
    (function(){
      const btn = document.getElementById('btnEditOffice');
      const modal = document.getElementById('officeModal');
      const cancel = document.getElementById('m_cancel');
      const requestBtn = document.getElementById('m_request');
      const officeId = document.getElementById('oh_office_id').value;

      function openModal(){
        document.getElementById('m_current_limit').value = document.getElementById('ci_current_limit').value;
        document.getElementById('m_active_ojts').value = document.getElementById('ci_active_ojts').value;
        document.getElementById('m_available_slots').value = document.getElementById('ci_available_slots').value;
        document.getElementById('m_requested_limit').value = document.getElementById('ci_requested_limit').value || '';
        document.getElementById('m_reason').value = document.getElementById('ci_reason').value || '';
        modal.style.display = 'flex';
      }
      function closeModal(){ modal.style.display = 'none'; }

      btn.addEventListener('click', openModal);
      cancel.addEventListener('click', closeModal);

      requestBtn.addEventListener('click', function(){
        const reqLimit = document.getElementById('m_requested_limit').value;
        const reason = document.getElementById('m_reason').value.trim();
        // basic validation
        if (reqLimit === '' || isNaN(reqLimit)) { alert('Please enter requested limit'); return; }
        // send AJAX
        fetch('office_head_action.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ action: 'request_limit', office_id: parseInt(officeId,10), requested_limit: parseInt(reqLimit,10), reason })
        }).then(r=>r.json()).then(j=>{
          if (!j || !j.success) { alert('Request failed: '+(j?.message||'unknown')); return; }
          // update UI fields
          document.getElementById('ci_requested_limit').value = j.data.requested_limit;
          document.getElementById('ci_reason').value = j.data.reason;
          document.getElementById('ci_status').value = j.data.status.charAt(0).toUpperCase() + j.data.status.slice(1);
          closeModal();
        }).catch(e=>{
          console.error(e);
          alert('Request failed');
        });
      });
    })();
    </script>

    <script>
    (function(){
      const dateInput = document.getElementById('lateDate');
      const tbody = document.getElementById('lateDtrTbody'); // target the second table explicitly
      const officeId = document.getElementById('oh_office_id').value;

      function renderRows(rows) {
        tbody.innerHTML = '';
        if (!rows || rows.length === 0) {
          const tr = document.createElement('tr');
          const td = document.createElement('td');
          td.colSpan = 7;
          td.textContent = 'No records found for selected date.';
          tr.appendChild(td);
          tbody.appendChild(tr);
          return;
        }
        rows.forEach(r=>{
          const tr = document.createElement('tr');
          function td(text){ const el = document.createElement('td'); el.textContent = text || ''; return el; }
          tr.appendChild(td((r.first_name || '') + ' ' + (r.last_name || '')));
          tr.appendChild(td(r.am_in ? r.am_in : ''));
          tr.appendChild(td(r.am_out ? r.am_out : ''));
          tr.appendChild(td(r.pm_in ? r.pm_in : ''));
          tr.appendChild(td(r.pm_out ? r.pm_out : ''));
          tr.appendChild(td(r.hours !== null && r.hours !== undefined ? String(r.hours) : ''));
          tr.appendChild(td(r.status || (r.hours ? 'Validated' : '')));
          tbody.appendChild(tr);
        });
      }

      function fetchForDate(d) {
        fetch('office_head_action.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ action: 'get_late_dtr', office_id: parseInt(officeId,10), date: d })
        }).then(r=>r.json()).then(j=>{
          if (!j || !j.success) {
            console.error('Fetch late dtr failed', j);
            renderRows([]);
            return;
          }
          renderRows(j.data || []);
        }).catch(err=>{
          console.error(err);
          renderRows([]);
        });
      }

      dateInput.addEventListener('change', function(){ fetchForDate(this.value); });

      // initial load for today's date
      fetchForDate(dateInput.value);
    })();
    </script>
</div>

</body>
</html>
