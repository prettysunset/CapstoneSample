<?php
// filepath: c:\xampp\htdocs\capstone_sample\CapstoneSample\hr_head\hr_head_ojts.php
session_start();
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// fetch user info
$stmtUser = $conn->prepare("SELECT first_name, middle_name, last_name, role FROM users WHERE user_id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc() ?: [];
$stmtUser->close();

$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'User';

// fetch all OJT applications with status 'approved' or 'rejected'
$q = "SELECT oa.application_id, oa.date_submitted, oa.status,
             s.first_name, s.last_name, s.college, s.course, s.year_level,
             oa.office_preference1, oa.office_preference2,
             o1.office_name AS office1, o2.office_name AS office2,
             oa.remarks,
             s.hours_rendered, s.total_hours_required
      FROM ojt_applications oa
      LEFT JOIN students s ON oa.student_id = s.student_id
      LEFT JOIN offices o1 ON oa.office_preference1 = o1.office_id
      LEFT JOIN offices o2 ON oa.office_preference2 = o2.office_id
      WHERE oa.status IN ('approved', 'rejected')
      ORDER BY oa.date_submitted DESC, oa.application_id DESC";
$stmt = $conn->prepare($q);
$stmt->execute();
$result = $stmt->get_result();
$students = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$current_time = date("g:i A");
$current_date = date("l, F j, Y");

// --- NEW: fetch offices + requested limits + active OJTs count ---
$offices_for_requests = [];
$off_q = $conn->query("SELECT office_id, office_name, current_limit, requested_limit, reason, status FROM offices ORDER BY office_name");
if ($off_q) {
    $stmtCount = $conn->prepare("
        SELECT COUNT(DISTINCT student_id) AS filled
        FROM ojt_applications
        WHERE (office_preference1 = ? OR office_preference2 = ?) AND status = 'approved'
    ");
    while ($r = $off_q->fetch_assoc()) {
        $office_id = (int)$r['office_id'];
        $stmtCount->bind_param("ii", $office_id, $office_id);
        $stmtCount->execute();
        $cnt = $stmtCount->get_result()->fetch_assoc();
        $filled = (int)($cnt['filled'] ?? 0);
        $capacity = is_null($r['current_limit']) ? null : (int)$r['current_limit'];
        $available = is_null($capacity) ? '—' : max(0, $capacity - $filled);

        $offices_for_requests[] = [
            'office_id' => $office_id,
            'office_name' => $r['office_name'],
            'current_limit' => $capacity,
            'active_ojts' => $filled,
            'available_slots' => $available,
            'requested_limit' => is_null($r['requested_limit']) ? '' : (int)$r['requested_limit'],
            'reason' => $r['reason'] ?? '',
            'status' => $r['status'] ?? ''
        ];
    }
    $stmtCount->close();
    $off_q->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>OJT-MS | HR Head OJTs</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
    *{box-sizing:border-box;font-family:'Poppins',sans-serif}
    body{background:#f7f8fc;display:flex;min-height:100vh;margin:0}
    .sidebar{background:#2f3850;width:220px;color:#fff;display:flex;flex-direction:column;align-items:center;padding:30px 0}
    .profile{text-align:center;margin-bottom:20px}
    .profile img{width:90px;height:90px;border-radius:50%;background:#cfd3db;margin-bottom:10px}
    .profile h3{font-size:16px;font-weight:600}
    .profile p{font-size:13px;color:#bfc4d1}
    .nav{display:flex;flex-direction:column;gap:10px;width:100%}
    .nav a{color:#fff;text-decoration:none;padding:10px 20px;display:flex;align-items:center;gap:10px;border-radius:25px;margin:0 15px}
    .nav a:hover,.nav a.active{background:#fff;color:#2f3850;font-weight:600}
    .main{flex:1;padding:24px}
    .top-section{display:flex;justify-content:space-between;gap:20px;margin-bottom:20px}
    .datetime h2{font-size:22px;color:#2f3850;margin:0}
    .datetime p{color:#6d6d6d;margin:0}
    .table-container{background:#fff;border-radius:8px;padding:16px;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
    .table-tabs{display:flex;gap:16px;margin-bottom:12px;border-bottom:2px solid #eee}
    .table-tabs a{padding:8px 12px;text-decoration:none;color:#555;border-radius:6px}
    .table-tabs a.active{background:#2f3850;color:#fff}
    .ojt-table-searchbar{display:flex;gap:12px;margin-bottom:12px;align-items:center}
    .ojt-table-searchbar input[type="text"]{
        padding:8px 12px;border-radius:8px;border:1px solid #ccc;width:220px;font-size:15px;
        background:#f7f8fc;
    }
    .ojt-table-searchbar select{
        padding:8px 12px;border-radius:8px;border:1px solid #ccc;font-size:15px;background:#f7f8fc;
    }
    .ojt-table-searchbar .sort-btn{
        padding:8px 12px;border-radius:8px;border:1px solid #ccc;background:#f7f8fc;cursor:pointer;font-size:15px;
    }
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:10px;border:1px solid #eee;text-align:left}
    th{background:#f5f6fa}
    .view-btn{background:none;border:none;cursor:pointer;font-size:18px;color:#222}
    .empty{padding:20px;text-align:center;color:#666}
    .status-approved{color:#0b7a3a;font-weight:600;}
    .status-rejected{color:#a00;font-weight:600;}
    /* Responsive tweaks */
    @media (max-width:900px){
        .main{padding:8px}
        .table-container{padding:6px}
        .ojt-table-searchbar input,.ojt-table-searchbar select{width:100px;font-size:13px;}
        th,td{padding:6px}
    }
</style>
</head>
<body>
<div class="sidebar">
    <div class="profile">
        <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Profile">
        <h3><?= htmlspecialchars($full_name ?: ($_SESSION['username'] ?? '')) ?></h3>
        <p><?= htmlspecialchars($role_label) ?></p>
    </div>
    <div class="nav">
        <a href="hr_head_home.php">🏠 Home</a>
        <a href="hr_head_ojts.php" class="active">👥 OJTs</a>
        <a href="hr_head_dtr.php">🕒 DTR</a>
        <a href="hr_head_moa.php">🕒 MOA</a>
        <a href="hr_head_accounts.php">⚙️ Accounts</a>
        <a href="hr_head_reports.php">📊 Reports</a>
    </div>
    <p style="margin-top:auto;font-weight:600">OJT-MS</p>
</div>
<div class="main">
    <div class="top-section">
        <div>
            <div class="datetime">
                <h2><?= $current_time ?></h2>
                <p><?= $current_date ?></p>
            </div>
        </div>
    </div>
    <div class="table-container">
    <div class="tabs" role="tablist" aria-label="OJT Tabs" style="display:flex;align-items:flex-end;gap:24px;margin-bottom:12px;">
        <button class="tab active" data-tab="ojts" role="tab" aria-selected="true" aria-controls="tab-ojts">On-the-Job Trainees (<?= count($students) ?>)</button>
        <button class="tab" data-tab="requested" role="tab" aria-selected="false" aria-controls="tab-requested">Requested OJTs</button>
        <div style="flex:1"></div>
        <div class="ojt-table-searchbar" style="margin-left:auto;">
            <input type="text" id="searchInput" placeholder="Search">
            <select id="yearFilter">
                <option value="">Year</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
            </select>
            <select id="sortBy">
                <option value="">Sort by</option>
                <option value="name">Name</option>
                <option value="office">Office</option>
                <option value="school">School</option>
                <option value="course">Course</option>
                <option value="year">Year Level</option>
                <option value="hours">Hours</option>
                <option value="status">Status</option>
            </select>
        </div>
    </div>

    <!-- underline bar -->
    <div id="tabsUnderline" aria-hidden="true" style="height:3px;background:#2f3850;border-radius:3px;width:180px;transition:all .25s;margin-bottom:12px;"></div>

    <!-- Tab panels -->
    <div id="tab-ojts" class="tab-panel" role="tabpanel" aria-labelledby="tab-ojts" style="display:block;">
        <div style="overflow-x:auto;">
        <table id="ojtTable">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Office</th>
                    <th>School</th>
                    <th>Course</th>
                    <th>Year Level</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>View</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($students)): ?>
                <tr><td colspan="8" class="empty">No OJT trainees found.</td></tr>
            <?php else: foreach ($students as $row):
                $office = $row['office1'] ?: ($row['office2'] ?: '—');
                $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                $school = $row['college'] ?? '—';
                $course = $row['course'] ?? '—';
                $year = $row['year_level'] ?? '—';
                $hours = (int)($row['hours_rendered'] ?? 0) . ' /' . (int)($row['total_hours_required'] ?? 500) . ' hrs';
                $status = $row['status'] ?? '';
                $statusClass = $status === 'approved' ? 'status-approved' : ($status === 'rejected' ? 'status-rejected' : '');
            ?>
                <tr>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><?= htmlspecialchars($office) ?></td>
                    <td><?= htmlspecialchars($school) ?></td>
                    <td><?= htmlspecialchars($course) ?></td>
                    <td><?= htmlspecialchars($year) ?></td>
                    <td><?= htmlspecialchars($hours) ?></td>
                    <td class="<?= $statusClass ?>"><?= ucfirst($status) ?></td>
                    <td>
                        <button class="view-btn" title="View" onclick="openViewModal(<?= (int)$row['application_id'] ?>)">👁️</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div id="tab-requested" class="tab-panel" role="tabpanel" aria-labelledby="tab-requested" style="display:none;">
        <!-- Requested OJTs panel content -->
        <div style="overflow-x:auto;padding:12px">
          <?php if (count($offices_for_requests) === 0): ?>
            <div class="empty">No office requests found.</div>
          <?php else: ?>
            <table class="request-table" role="table" aria-label="Requested OJTs">
              <thead>
                <tr>
                  <th>Office</th>
                  <th style="text-align:center">Current Limit</th>
                  <th style="text-align:center">Active OJTs</th>
                  <th style="text-align:center">Available Slots</th>
                  <th style="text-align:center">Requested Limit</th>
                  <th>Reason</th>
                  <th style="text-align:center">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($offices_for_requests as $of): ?>
                  <tr>
                    <td><?= htmlspecialchars($of['office_name']) ?></td>
                    <td style="text-align:center"><?= $of['current_limit'] === null ? '—' : (int)$of['current_limit'] ?></td>
                    <td style="text-align:center"><?= (int)$of['active_ojts'] ?></td>
                    <td style="text-align:center"><?= htmlspecialchars((string)$of['available_slots']) ?></td>
                    <td style="text-align:center"><?= $of['requested_limit'] === '' ? '—' : (int)$of['requested_limit'] ?></td>
                    <td><?= htmlspecialchars($of['reason'] ?: '—') ?></td>
                    <td style="text-align:center">
                      <?php if (strtolower($of['status']) === 'approved' || strtolower($of['status']) === 'Approved'): ?>
                        <span class="action-ok">Approved</span>
                      <?php elseif (strtolower($of['status']) === 'declined' || strtolower($of['status']) === 'Declined'): ?>
                        <span style="color:#a00;font-weight:700">Declined</span>
                      <?php else: ?>
                        <span class="action-pending">
                          <button type="button" class="ok" onclick="handleOfficeRequest(<?= (int)$of['office_id'] ?>, 'approve')" title="Approve" aria-label="Approve">✔</button>
                          <button type="button" class="no" onclick="handleOfficeRequest(<?= (int)$of['office_id'] ?>, 'decline')" title="Decline" aria-label="Decline">✖</button>
                        </span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function(){
    // tabs underline positioning
    const tabs = Array.from(document.querySelectorAll('.tabs .tab'));
    const underline = document.getElementById('tabsUnderline');
    function positionUnderline(btn){
        const rect = btn.getBoundingClientRect();
        const containerRect = btn.parentElement.getBoundingClientRect();
        underline.style.width = Math.max(80, rect.width) + 'px';
        underline.style.transform = `translateX(${rect.left - containerRect.left}px)`;
    }
    // init
    const active = document.querySelector('.tabs .tab.active') || tabs[0];
    if (active) positionUnderline(active);

    tabs.forEach(btn=>{
        btn.addEventListener('click', function(){
            // toggle active class
            tabs.forEach(t=>{ t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
            this.classList.add('active');
            this.setAttribute('aria-selected','true');
            // panels
            const tab = this.getAttribute('data-tab');
            document.querySelectorAll('.tab-panel').forEach(p=>{
                p.style.display = p.id === 'tab-'+tab ? 'block' : 'none';
            });
            positionUnderline(this);
        });
    });

    // reposition underline on resize
    window.addEventListener('resize', ()=> {
        const cur = document.querySelector('.tabs .tab.active') || tabs[0];
        if (cur) positionUnderline(cur);
    });

    // expose simple view open used elsewhere
    window.openViewModal = window.openViewModal || function(appId){
        // fallback: navigate to application_view.php if modal endpoint not available
        window.location.href = 'application_view.php?id=' + encodeURIComponent(appId);
    };

    // call backend to approve/decline office requested limits
    window.handleOfficeRequest = async function(officeId, action) {
      if (!confirm(`Are you sure you want to ${action} the requested limit for office #${officeId}?`)) return;
      try {
        const res = await fetch('../hr_actions.php', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ action: 'respond_office_request', office_id: parseInt(officeId,10), response: action })
        });
        const j = await res.json();
        if (!j || !j.success) {
          alert('Failed: ' + (j?.message || 'Unknown error'));
          return;
        }
        // success — reload so HR + Office Head pages reflect updated limits/status
        alert('Request processed: ' + (j.message || 'OK'));
        location.reload();
      } catch (err) {
        console.error(err);
        alert('Request failed');
      }
    }
})();
</script>
</body>
</html>