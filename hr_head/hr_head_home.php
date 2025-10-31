<?php
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
$stmtUser = $conn->prepare("SELECT first_name, middle_name, last_name, role, office_name FROM users WHERE user_id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc() ?: [];
$stmtUser->close();

$full_name = trim(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$role_label = !empty($user['role']) ? ucwords(str_replace('_',' ', $user['role'])) : 'User';

// which tab: pending (default) or rejected
$tab = isset($_GET['tab']) && $_GET['tab'] === 'rejected' ? 'rejected' : 'pending';

// counts (replace pending/rejected counts with active/completed)
$stmt = $conn->prepare("SELECT COUNT(*) FROM ojt_applications WHERE status = 'approved'");
$stmt->execute();
$stmt->bind_result($active_count);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM ojt_applications WHERE status = 'completed'");
$stmt->execute();
$stmt->bind_result($completed_count);
$stmt->fetch();
$stmt->close();

// --- ADDED: pending/rejected counts used by the tabs ---
$stmt = $conn->prepare("SELECT COUNT(*) FROM ojt_applications WHERE status = 'pending'");
$stmt->execute();
$stmt->bind_result($pending_count);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) FROM ojt_applications WHERE status = 'rejected'");
$stmt->execute();
$stmt->bind_result($rejected_count);
$stmt->fetch();
$stmt->close();

// fetch applications for current tab (include student email)
$statusFilter = $tab === 'rejected' ? 'rejected' : 'pending';
$q = "SELECT oa.application_id, oa.date_submitted, oa.status,
             s.student_id, s.first_name AS s_first, s.last_name AS s_last, s.address AS s_address, s.email AS s_email,
             oa.office_preference1, oa.office_preference2,
             o1.office_name AS opt1, o2.office_name AS opt2
      FROM ojt_applications oa
      LEFT JOIN students s ON oa.student_id = s.student_id
      LEFT JOIN offices o1 ON oa.office_preference1 = o1.office_id
      LEFT JOIN offices o2 ON oa.office_preference2 = o2.office_id
      WHERE oa.status = ?
      ORDER BY oa.date_submitted DESC, oa.application_id DESC";
$stmtApps = $conn->prepare($q);
$stmtApps->bind_param("s", $statusFilter);
$stmtApps->execute();
$result = $stmtApps->get_result();
$apps = $result->fetch_all(MYSQLI_ASSOC);
$stmtApps->close();

// current server date/time
$current_time = date("g:i A");
$current_date = date("l, F j, Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>OJT-MS | HR Head Dashboard</title>
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
    table{width:100%;border-collapse:collapse;font-size:14px}
    th,td{padding:10px;border:1px solid #eee;text-align:left}
    th{background:#f5f6fa}
    .actions{display:flex;gap:8px;justify-content:center}
    .actions button{border:none;background:none;cursor:pointer;font-size:16px}
    .approve{color:green} .reject{color:red} .view{color:#0b74de}
    .empty{padding:20px;text-align:center;color:#666}

    /* top-right icons */
    .top-icons{display:flex;gap:10px;align-items:center}
    .top-icons button{background:none;border:none;cursor:pointer;font-size:18px;padding:8px;border-radius:8px}
    .top-icons button:hover{background:#f0f0f0}

    /* Modal / overlay */
    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(102, 51, 153, 0.18); /* light purple translucent blur look */
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      backdrop-filter: none;
    }
    .modal {
      background: #fff;
      border-radius: 10px;
      padding: 18px;
      width: 420px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      max-width: calc(100% - 40px);
    }
    .modal h3 { margin:0 0 12px 0; color:#3a2b6a }
    .modal .row { margin-bottom:8px; font-size:14px }
    .modal label { font-weight:600; font-size:13px; display:block; margin-bottom:4px }
    .modal input[type="date"] { width:100%; padding:8px; border-radius:6px; border:1px solid #ccc }
    .modal .values { padding:8px 10px; background:#faf7ff; border-radius:6px; color:#333; }
    .modal .actions { display:flex; gap:8px; justify-content:flex-end; margin-top:12px }
    .modal button { padding:8px 12px; border-radius:6px; border:none; cursor:pointer }
    .btn-cancel { background:#eee; color:#333 }
    .btn-send { background:#6a3db5; }

    /* View Application Modal specific styles */
    #view_name { font-weight:700; font-size:18px; }
    #view_status { color:#666; font-size:13px; }
    #view_avatar {
      width:120px;
      height:120px;
      border-radius:50%;
      background:#e9e9e9;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size:44px;
      color:#777;
      margin:0 auto 12px auto;
    }
    #view_approve_btn, #view_reject_btn {
      background:#fff;
      border:2px solid;
      padding:8px 14px;
      border-radius:24px;
      cursor:pointer;
      font-weight:500;
      display:inline-flex;
      align-items:center;
      gap:6px;
    }
    #view_approve_btn {
      color:#28a745;
      border-color:#28a745;
    }
    #view_reject_btn {
      color:#dc3545;
      border-color:#dc3545;
    }
    #view_attachments {
      display:flex;
      flex-direction:column;
      gap:8px;
    }
    .status-open{ color:#0b7a3a; font-weight:700; background:#e6f9ee; padding:6px 10px; border-radius:12px; display:inline-block; }
    .status-full{ color:#b22222; font-weight:700; background:#fff4f4; padding:6px 10px; border-radius:12px; display:inline-block; }

    /* make the left time/counters container smaller so slots table gets more horizontal space */
    .time-card { min-width:220px; max-width:260px; padding:14px; }
    .time-card .current-time { font-size:24px; color:#2f3850; margin:0; }
    .time-card .current-date { color:#6d6d6d; font-size:13px; }

    /* reduce counter size */
    .counter { padding:12px; }
    .counter h3 { margin:0; font-size:22px; color:#2f3850; }
    .counter p { margin:6px 0 0 0; color:#666; font-size:12px; }

    /* ensure slots card can expand */
    .slots-card { width:100%; }

    /* top bar / header styles */
    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 24px;
    }
    .topbar .card {
      background: #fff;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
      flex: 1;
      margin-right: 18px;
    }
    .topbar .card:last-child {
      margin-right: 0;
    }
    .topbar h2 {
      font-size: 28px;
      margin: 0 0 12px 0;
      color: #2f3850;
    }
    .topbar p {
      margin: 0;
      color: #6d6d6d;
      font-size: 14px;
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="profile">
        <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="Profile">
        <h3><?php echo htmlspecialchars($full_name ?: ($_SESSION['username'] ?? '')); ?></h3>
        <p><?php echo htmlspecialchars($role_label); ?></p>
        <?php if(!empty($user['office_name'])): ?>
            <p style="font-size:12px;color:#bfc4d1"><?php echo htmlspecialchars($user['office_name']); ?></p>
        <?php endif; ?>
    </div>

    <div class="nav">
      <a href="#" class="active">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <path d="M3 11.5L12 4l9 7.5"></path>
          <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
        </svg>
        Home
      </a>
      <a href="hr_head_ojts.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="8" r="3"></circle>
          <path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path>
        </svg>
        OJTs
      </a>
      <a href="hr_head_dtr.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="12" r="8"></circle>
          <path d="M12 8v5l3 2"></path>
        </svg>
        DTR
      </a>
      <a href="hr_head_moa.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="12" r="8"></circle>
          <path d="M12 8v5l3 2"></path>
        </svg>
        MOA
      </a>
      <a href="hr_head_accounts.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <circle cx="12" cy="12" r="3"></circle>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06A2 2 0 1 1 2.28 16.8l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09c.7 0 1.3-.4 1.51-1A1.65 1.65 0 0 0 4.27 6.3L4.2 6.23A2 2 0 1 1 6 3.4l.06.06c.5.5 1.2.7 1.82.33.7-.4 1.51-.4 2.21 0 .62.37 1.32.17 1.82-.33L12.6 3.4a2 2 0 1 1 1.72 3.82l-.06.06c-.5.5-.7 1.2-.33 1.82.4.7.4 1.51 0 2.21-.37.62-.17 1.32.33 1.82l.06.06A2 2 0 1 1 19.4 15z"></path>
        </svg>
        Accounts
      </a>
      <a href="hr_head_reports.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <rect x="3" y="10" width="4" height="10"></rect>
          <rect x="10" y="6" width="4" height="14"></rect>
          <rect x="17" y="2" width="4" height="18"></rect>
        </svg>
        Reports
      </a>
      </div>
    <p style="margin-top:auto;font-weight:600">OJT-MS</p>
</div>

<div class="main">
  <!-- Top bar: icons on the right -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <div><!-- left intentionally empty to keep icons on the right --></div>
    <div class="top-icons">
      <button id="btnNotif" title="Notifications" aria-label="Notifications">🔔</button>
      <button id="btnSettings" title="Settings" aria-label="Settings">⚙️</button>
      <button id="btnLogout" title="Log out" aria-label="Log out">🚪</button>
    </div>
  </div>

  <!-- Second row: left = date + counters, right = OJT slot availability (side-by-side) -->
  <div style="display:flex;gap:18px;align-items:stretch;margin-bottom:16px;">
    <!-- Left column: date on top, counters below -->
    <div style="flex:1;min-width:280px;display:flex;flex-direction:column;">
      <div style="background:#fff;border-radius:8px;padding:14px;box-shadow:0 2px 8px rgba(0,0,0,0.06);flex:1;display:flex;flex-direction:column;justify-content:space-between;">
        <div class="datetime" style="margin-bottom:12px">
          <h2 style="margin:0;font-size:34px;line-height:1"><?php echo $current_time; ?></h2>
          <p style="margin:0;color:#6d6d6d;font-size:30px"><?php echo $current_date; ?></p>
        </div>

        <div style="display:flex;gap:12px;align-items:center;margin-top:12px">
          <div style="background:#eceff3;padding:20px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.02);text-align:center;flex:1;min-height:140px;display:flex;flex-direction:column;justify-content:center;">
            <div style="font-size:36px;font-weight:700;color:#2f3850"><?php echo (int)$active_count; ?></div>
            <div style="color:#666;font-size:14px;margin-top:6px">Active OJTs</div>
          </div>
          <div style="background:#eceff3;padding:20px;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.02);text-align:center;flex:1;min-height:140px;display:flex;flex-direction:column;justify-content:center;">
            <div style="font-size:36px;font-weight:700;color:#2f3850"><?php echo (int)$completed_count; ?></div>
            <div style="color:#666;font-size:14px;margin-top:6px">Completed OJTs</div>
          </div>
        </div>
      </div>
    </div>

<?php
// --- Office slot availability block (prepare data) ---
$capacityCol = null;
$variants = ['current_limit','slot_capacity','capacity','slots','max_slots'];
foreach ($variants as $v) {
    $res = $conn->query("SHOW COLUMNS FROM offices LIKE '".$conn->real_escape_string($v)."'");
    if ($res && $res->num_rows > 0) { $capacityCol = $v; break; }
}

// fetch offices
if ($capacityCol) {
    $sql = "SELECT office_id, office_name, `$capacityCol` AS capacity FROM offices ORDER BY office_name";
} else {
    $sql = "SELECT office_id, office_name FROM offices ORDER BY office_name";
}
$offices = [];
if ($resOff = $conn->query($sql)) {
    while ($r = $resOff->fetch_assoc()) $offices[] = $r;
    $resOff->free();
}

// prepare stmt to count approved/assigned OJTs per office
$stmtCount = $conn->prepare("
    SELECT COUNT(DISTINCT student_id) AS filled
    FROM ojt_applications
    WHERE (office_preference1 = ? OR office_preference2 = ?) AND status = 'approved'
");
?>

    <!-- Right column: slot availability -->
    <div style="flex:1;min-width:360px;">
      <div class="table-container" style="padding:12px;">
        <h3 style="margin:0 0 12px 0; background:#3a4163;padding:12px;border-radius:8px; color:#fff">OJT Slot Availability by Office</h3>

        <?php if (empty($offices)): ?>
            <div class="empty">No offices found.</div>
        <?php else: ?>
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px">Office</th>
                        <th style="padding:8px">Capacity</th>
                        <th style="padding:8px">Active OJTs</th>
                        <th style="padding:8px">Available Slot</th>
                        <th style="padding:8px">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($offices as $o):
                    // support either 'capacity' or 'current_limit' depending on how $offices was built
                    $cap = isset($o['capacity']) ? (int)$o['capacity'] : (isset($o['current_limit']) ? (int)$o['current_limit'] : null);
                    $filled = isset($o['filled']) ? (int)$o['filled'] : 0;

                    if ($cap === null) {
                        $availableDisplay = '—';
                        $statusLabel = 'Open';
                        $statusClass = 'status-open';
                    } else {
                        $availableNum = max(0, $cap - $filled);
                        $availableDisplay = $availableNum;
                        // EXACT condition: if available is zero => Full; otherwise Open
                        if ($availableNum === 0) {
                            $statusLabel = 'Full';
                            $statusClass = 'status-full';
                        } else {
                            $statusLabel = 'Open';
                            $statusClass = 'status-open';
                        }
                    }
                ?>
                  <tr data-search="<?= htmlspecialchars(strtolower($o['office_name'] ?? '')) ?>">
                    <td><?= htmlspecialchars($o['office_name'] ?? '—') ?></td>
                    <td style="text-align:center"><?= $cap === null ? '—' : $cap ?></td>
                    <td style="text-align:center"><?= $filled ?></td>
                    <td style="text-align:center"><?= htmlspecialchars((string)$availableDisplay) ?></td>
                    <td style="text-align:center"><span class="<?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
      </div>
    </div>
  </div> <!-- end second row -->

<?php
$stmtCount->close();
?>

  <!-- Next row: the list of pending / rejected (keeps the existing table-container after placeholder) -->

    <div class="table-container">
        <div class="table-tabs">
            <a class="<?php echo $tab === 'pending' ? 'active' : ''; ?>" href="?tab=pending" <?php if ($tab === 'pending') echo 'style="background:#3a4163;color:#fff"'; ?>>Pending Approvals (<?php echo (int)$pending_count; ?>)</a>
            <a class="<?php echo $tab === 'rejected' ? 'active' : ''; ?>" href="?tab=rejected" <?php if ($tab === 'rejected') echo 'style="background:#3a4163;color:#fff"'; ?>>Rejected Students (<?php echo (int)$rejected_count; ?>)</a></div>

        <?php if (count($apps) === 0): ?>
            <div class="empty">No <?php echo $tab === 'rejected' ? 'rejected' : 'pending'; ?> applications found.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date Submitted</th>
                    <th>Name</th>
                    <th>Address</th>
                    <th>1st Option</th>
                    <th>2nd Option</th>
                    <?php if ($tab !== 'rejected'): ?>
                        <th style="text-align:center">Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apps as $row): ?>
                <tr>
                    <td><?php echo $row['date_submitted'] ? htmlspecialchars(date("M j, Y", strtotime($row['date_submitted']))) : '—'; ?></td>
                    <td><?php echo htmlspecialchars(trim(($row['s_first'] ?? '') . ' ' . ($row['s_last'] ?? '')) ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['s_address'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['opt1'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['opt2'] ?: 'N/A'); ?></td>
                    <?php if ($tab !== 'rejected'): ?>
                    <td class="actions">
                        <button type="button" class="view" title="View" onclick="openViewModal(<?= (int)$row['application_id'] ?>)">👁</button>
                        <button type="button"
                            class="approve"
                            title="Approve"
                            data-appid="<?php echo (int)$row['application_id']; ?>"
                            data-name="<?php echo htmlspecialchars(trim(($row['s_first'] ?? '') . ' ' . ($row['s_last'] ?? ''))); ?>"
                            data-email="<?php echo htmlspecialchars($row['s_email'] ?? ''); ?>"
                            data-opt1="<?php echo htmlspecialchars($row['opt1'] ?? ''); ?>"
                            data-opt2="<?php echo htmlspecialchars($row['opt2'] ?? ''); ?>"
                            data-opt1-id="<?php echo (int)($row['office_preference1'] ?? 0); ?>"
                            data-opt2-id="<?php echo (int)($row['office_preference2'] ?? 0); ?>"
                            onclick="openApproveModal(this)"
                        >✔</button>
                        <button type="button"
                            class="reject"
                            title="Reject"
                            data-appid="<?php echo (int)$row['application_id']; ?>"
                            data-name="<?php echo htmlspecialchars(trim(($row['s_first'] ?? '') . ' ' . ($row['s_last'] ?? ''))); ?>"
                            data-email="<?php echo htmlspecialchars($row['s_email'] ?? ''); ?>"
                            onclick="openRejectModal(this)"
                        >✖</button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal overlay for approval -->
<div id="overlay" class="overlay" role="dialog" aria-hidden="true">
  <div class="modal" role="document" aria-labelledby="approveTitle">
    <h3 id="approveTitle">Approve Application</h3>

    <div class="row">
      <label>Student Name</label>
      <div class="values" id="modal_name">—</div>
    </div>

    <div class="row">
      <label>Email</label>
      <div class="values" id="modal_email">—</div>
    </div>

    <div class="row">
      <label>Assigned Office (from applicant)</label>
      <div class="values" id="modal_office">—</div>
    </div>

    <div class="row">
      <label>Orientation / Starting Date</label>
      <input type="date" id="modal_date">
    </div>

    <!-- status message area (hidden until send result) -->
    <div id="modal_status" class="values" style="display:none;margin-top:10px;"></div>

    <div class="actions">
      <button class="btn-cancel" onclick="closeModal()" type="button">Cancel</button>
      <button id="btnSend" class="btn-send" type="button" onclick="sendApproval()" aria-disabled="true" disabled>Send</button>
    </div>
  </div>
</div>

<!-- Reject Modal overlay -->
<div id="rejectOverlay" class="overlay" role="dialog" aria-hidden="true">
  <div class="modal" role="document" aria-labelledby="rejectTitle">
    <h3 id="rejectTitle">Reject Application</h3>
    <div class="row">
      <label>Student Name</label>
      <div class="values" id="reject_name">—</div>
    </div>
    <div class="row">
      <label>Email</label>
      <div class="values" id="reject_email">—</div>
    </div>
    <div class="row">
      <label>Reason for Rejection <span style="color:red">*</span></label>
      <textarea id="reject_reason" rows="3" style="width:100%;padding:8px;border-radius:6px;border:1px solid #ccc" required></textarea>
    </div>
    <div id="reject_status" class="values" style="display:none;margin-top:10px;"></div>
    <div class="actions">
      <button class="btn-cancel" onclick="closeRejectModal()" type="button">Cancel</button>
      <button id="btnRejectSend" class="btn-send" type="button" onclick="sendReject()" aria-disabled="true" disabled>Reject</button>
    </div>
  </div>
</div>
<!-- View Application Modal -->
<div id="viewOverlay" class="overlay" role="dialog" aria-hidden="true" style="display:none;align-items:center;justify-content:center;">
  <div class="modal" style="width:760px;max-width:calc(100% - 40px);max-height:80vh;overflow:auto;padding:16px;">
    <!-- Top: avatar, name, status and action buttons -->
    <div style="display:flex;flex-direction:column;align-items:center;gap:12px;padding-bottom:8px;border-bottom:1px solid #eee">
      <div style="width:120px;height:120px;border-radius:50%;background:#e9e9e9;display:flex;align-items:center;justify-content:center;font-size:44px;color:#777" id="view_avatar">👤</div>
      <div style="text-align:center">
        <div id="view_name" style="font-weight:700;font-size:18px">Name</div>
        <div id="view_status" style="color:#666;font-size:13px">Status | hours</div>
      </div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <button style="background:#fff;border:2px solid #28a745;color:#28a745;padding:8px 14px;border-radius:24px;cursor:pointer" id="view_approve_btn">APPROVE</button>
        <button style="background:#fff;border:2px solid #dc3545;color:#dc3545;padding:8px 14px;border-radius:24px;cursor:pointer" id="view_reject_btn">REJECT</button>
      </div>
    </div>

    <!-- Below name: info and attachments shown as flex -->
    <div style="display:flex;gap:18px;margin-top:12px;flex-wrap:wrap;">
      <div style="flex:1;min-width:280px;border-right:1px solid #eee;padding-right:12px">
        <table style="width:100%;border-collapse:collapse">
          <tbody style="font-size:14px">
            <tr><td style="width:140px;font-weight:700">Age</td><td id="view_age"></td></tr>
            <tr><td style="font-weight:700">Birthday</td><td id="view_birthday"></td></tr>
            <tr><td style="font-weight:700">Address</td><td id="view_address"></td></tr>
            <tr><td style="font-weight:700">Phone</td><td id="view_phone"></td></tr>
            <tr><td style="font-weight:700">Email</td><td id="view_email"></td></tr>

            <tr><td style="height:8px"></td><td></td></tr>

            <tr><td style="font-weight:700">College/University</td><td id="view_college"></td></tr>
            <tr><td style="font-weight:700">Course</td><td id="view_course"></td></tr>
            <tr><td style="font-weight:700">Year level</td><td id="view_year"></td></tr>
            <tr><td style="font-weight:700">School Address</td><td id="view_school_address"></td></tr>
            <tr><td style="font-weight:700">OJT Adviser</td><td id="view_adviser"></td></tr>

            <tr><td style="height:8px"></td><td></td></tr>

            <tr><td style="font-weight:700">Emergency Contact</td><td id="view_emg_name"></td></tr>
            <tr><td style="font-weight:700">Relationship</td><td id="view_emg_relation"></td></tr>
            <tr><td style="font-weight:700">Contact Number</td><td id="view_emg_contact"></td></tr>
          </tbody>
        </table>
      </div>

      <div style="width:320px;padding-left:12px;min-width:220px">
        <div style="font-weight:700;margin-bottom:8px">Attachments</div>
        <div id="view_attachments" style="display:flex;flex-direction:column;gap:8px"></div>
      </div>
    </div>
    <style>
      /* make modal a positioned container so the button can be placed at its top-right */
      .modal { position: relative; }
    </style>

    <!-- top-right Close button inside the modal -->
    <div style="position:absolute; top:12px; right:12px;">
      <button class="btn-cancel"
              onclick="closeViewModal()"
              type="button"
              style="padding:8px 12px; border-radius:8px; border:none; background:#eee; color:#333; cursor:pointer;">
        Close
      </button>
    </div>
  </div>
</div>

<script>
// hold currently approving application id
let currentAppId = null;

async function openApproveModal(btn) {
    const el = btn;
    currentAppId = el.getAttribute('data-appid');
    const name = el.getAttribute('data-name') || '';
    const email = el.getAttribute('data-email') || '';
    const opt1Name = el.getAttribute('data-opt1') || '';
    const opt2Name = el.getAttribute('data-opt2') || '';
    const opt1Id = parseInt(el.getAttribute('data-opt1-id') || '0', 10) || null;
    const opt2Id = parseInt(el.getAttribute('data-opt2-id') || '0', 10) || null;

    document.getElementById('modal_name').textContent = name;
    document.getElementById('modal_email').textContent = email;

    // determine assigned office by asking server (pref1 if has capacity, else pref2, else show N/A)
    let assigned = '';
    try {
        const payload = { action: 'check_capacity', office1: opt1Id, office2: opt2Id };
        const res = await fetch('../hr_actions.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (json && json.success) {
            assigned = json.assigned || '';
        } else {
            // fallback display: prefer opt1 name then opt2
            assigned = opt1Name || opt2Name || 'N/A';
        }
    } catch (e) {
        assigned = opt1Name || opt2Name || 'N/A';
    }

    document.getElementById('modal_office').textContent = assigned || 'N/A';

    const dateInput = document.getElementById('modal_date');

    // --- ADDED: prevent selecting past dates ---
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const minDate = `${yyyy}-${mm}-${dd}`;
    dateInput.min = minDate;
    // optionally prefill with today
    dateInput.value = minDate;
    // --- end added ---

    // disable send until date chosen
    const btnSend = document.getElementById('btnSend');
    btnSend.disabled = false;
    btnSend.setAttribute('aria-disabled', 'false');

    // enable when date selected (also re-check validity)
    dateInput.oninput = function() {
        const val = dateInput.value;
        if (val && val >= dateInput.min) {
            btnSend.disabled = false;
            btnSend.setAttribute('aria-disabled', 'false');
        } else {
            btnSend.disabled = true;
            btnSend.setAttribute('aria-disabled', 'true');
        }
    };

    // show overlay
    const ov = document.getElementById('overlay');
    ov.style.display = 'flex';
    ov.setAttribute('aria-hidden', 'false');

    dateInput.focus();
}

function closeModal(){
    const ov = document.getElementById('overlay');
    ov.style.display = 'none';
    ov.setAttribute('aria-hidden', 'true');
    currentAppId = null;

    // cleanup date onchange
    const dateInput = document.getElementById('modal_date');
    dateInput.oninput = null;
}

function sendApproval(){
    if (!currentAppId) return alert('Invalid application.');

    const date = document.getElementById('modal_date').value;
    const statusEl = document.getElementById('modal_status');

    if (!date) {
        return alert('Please select the orientation / starting date.');
    }

    // disable button to prevent double submit
    const btnSend = document.getElementById('btnSend');
    btnSend.disabled = true;
    btnSend.setAttribute('aria-disabled', 'true');

    // clear/prepare status area
    statusEl.style.display = 'none';
    statusEl.textContent = '';
    statusEl.style.background = '';
    statusEl.style.color = '';

    const payload = {
        action: 'approve_send',
        application_id: parseInt(currentAppId,10),
        orientation_date: date
    };

    fetch('../hr_actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    }).then(r => r.json())
      .then(res => {
          if (res.success) {
              // show inline status based on mail result
              if (res.mail && res.mail === 'sent') {
                  statusEl.style.display = 'block';
                  statusEl.style.background = '#e6f9ee';
                  statusEl.style.color = '#0b7a3a';
                  statusEl.textContent = 'Email sent.';
              } else {
                  statusEl.style.display = 'block';
                  statusEl.style.background = '#fff4f4';
                  statusEl.style.color = '#a00';
                  statusEl.textContent = 'Email not sent (' + (res.mail || 'failed') + ').';
                  if (res.debug) statusEl.textContent += ' See debug.';
                  console.warn(res.debug || res.error || '');
              }

              // give user a short moment to see the message, then close modal and reload to reflect DB status change
              setTimeout(() => {
                  closeModal();
                  location.reload();
              }, 900);
          } else {
              // server returned success=false
              alert('Error: ' + (res.message || 'Unknown') + (res.error ? '\n' + res.error : ''));
              // re-enable send on failure
              btnSend.disabled = false;
              btnSend.setAttribute('aria-disabled', 'false');
          }
      }).catch(err => {
          alert('Request failed.');
          console.error(err);
          btnSend.disabled = false;
          btnSend.setAttribute('aria-disabled', 'false');
      });
}

// Reject modal functions
let currentRejectId = null;

function openRejectModal(btn) {
    currentRejectId = btn.getAttribute('data-appid');
    document.getElementById('reject_name').textContent = btn.getAttribute('data-name') || '';
    document.getElementById('reject_email').textContent = btn.getAttribute('data-email') || '';
    document.getElementById('reject_reason').value = '';
    document.getElementById('reject_status').style.display = 'none';
    document.getElementById('btnRejectSend').disabled = true;
    document.getElementById('btnRejectSend').setAttribute('aria-disabled', 'true');
    document.getElementById('rejectOverlay').style.display = 'flex';
    document.getElementById('rejectOverlay').setAttribute('aria-hidden', 'false');
    document.getElementById('reject_reason').focus();
}

// Enable Reject button only if reason is filled
document.addEventListener('input', function(e){
    if (e.target && e.target.id === 'reject_reason') {
        const btn = document.getElementById('btnRejectSend');
        if (e.target.value.trim().length > 0) {
            btn.disabled = false;
            btn.setAttribute('aria-disabled', 'false');
        } else {
            btn.disabled = true;
            btn.setAttribute('aria-disabled', 'true');
        }
    }
});

function closeRejectModal(){
    document.getElementById('rejectOverlay').style.display = 'none';
    document.getElementById('rejectOverlay').setAttribute('aria-hidden', 'true');
    currentRejectId = null;
}

function sendReject(){
    const reason = document.getElementById('reject_reason').value.trim();
    if (!currentRejectId || !reason) {
        alert('Please provide a reason for rejection.');
        return;
    }
    const statusEl = document.getElementById('reject_status');
    statusEl.style.display = 'none';
    statusEl.textContent = '';
    statusEl.style.background = '';
    statusEl.style.color = '';

    const btn = document.getElementById('btnRejectSend');
    btn.disabled = true;
    btn.setAttribute('aria-disabled', 'true');

    fetch('../hr_actions.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
            application_id: parseInt(currentRejectId,10),
            action: 'reject',
            reason: reason
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            statusEl.style.display = 'block';
            statusEl.style.background = '#fff4f4';
            statusEl.style.color = '#a00';
            statusEl.textContent = 'Application rejected.';
            setTimeout(() => {
                closeRejectModal();
                location.reload();
            }, 900);
        } else {
            alert('Error: ' + (res.message || 'Unknown'));
            btn.disabled = false;
            btn.setAttribute('aria-disabled', 'false');
        }
    })
    .catch(err => {
        alert('Request failed');
        btn.disabled = false;
        btn.setAttribute('aria-disabled', 'false');
    });
}

// existing handleAction for reject (uses hr_actions.php)
function handleAction(id, action) {
  if (!confirm('Are you sure?')) return;
  fetch('../hr_actions.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ application_id: id, action: action })
  })
  .then(response => response.text())
  .then(text => {
    // try parse JSON safely
    let json = null;
    try { json = JSON.parse(text); } catch(e) { console.error('Non-JSON response:', text); }
    if (json && json.success) {
      alert('Action completed.');
      location.reload();
    } else {
      console.error('Action failed:', json || text);
      alert('Error: ' + (json && json.message ? json.message : 'Request failed or invalid response'));
    }
  })
  .catch(err => {
    console.error('Request error:', err);
    alert('Request failed');
  });
}

/* View Application Modal scripts */
async function openViewModal(appId) {
  const overlay = document.getElementById('viewOverlay');
  // clear
  ['view_name','view_status','view_age','view_birthday','view_address','view_phone','view_email',
   'view_college','view_course','view_year','view_school_address','view_adviser',
   'view_emg_name','view_emg_relation','view_emg_contact','view_attachments'].forEach(id=> {
     const el = document.getElementById(id);
     if (el) el.textContent = '';
  });

  overlay.style.display = 'flex';
  overlay.setAttribute('aria-hidden','false');

  try {
    const res = await fetch('../hr_actions.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ action: 'get_application', application_id: appId })
    });
    const json = await res.json();
    if (!json.success) {
      alert('Error: ' + (json.message || 'Failed to load'));
      closeViewModal();
      return;
    }
    const d = json.data;
    const s = d.student || {};
    document.getElementById('view_name').textContent = (s.first_name + ' ' + s.last_name).trim();
    document.getElementById('view_status').textContent = (d.status || '') + (d.office1 ? ' | ' + d.office1 + (d.office2 ? ' | ' + d.office2 : '') : '');
    document.getElementById('view_age').textContent = s.age !== null && s.age !== undefined ? s.age : (s.age === 0 ? 0 : '—');
    document.getElementById('view_birthday').textContent = s.birthday || '—';
    document.getElementById('view_address').textContent = s.address || '—';
    document.getElementById('view_phone').textContent = s.contact_number || '—';
    document.getElementById('view_email').textContent = s.email || '—';
    document.getElementById('view_college').textContent = s.college || '—';
    document.getElementById('view_course').textContent = s.course || '—';
    document.getElementById('view_year').textContent = s.year_level || '—';
    document.getElementById('view_school_address').textContent = s.school_address || '—';
    document.getElementById('view_adviser').textContent = (s.ojt_adviser || '') + (s.adviser_contact ? ' | ' + s.adviser_contact : '');

    document.getElementById('view_emg_name').textContent = s.emergency_name || '—';
    document.getElementById('view_emg_relation').textContent = s.emergency_relation || '—';
    document.getElementById('view_emg_contact').textContent = s.emergency_contact || '—';

    // attachments
    const attRoot = document.getElementById('view_attachments');
    attRoot.innerHTML = '';
    const attachments = [
      {key:'letter_of_intent', label:'Letter of Intent', file:d.letter_of_intent},
      {key:'endorsement_letter', label:'Endorsement Letter', file:d.endorsement_letter},
      {key:'resume', label:'Resume', file:d.resume},
      {key:'moa_file', label:'MOA', file:d.moa_file},
      {key:'picture', label:'Picture', file:d.picture}
    ];
    attachments.forEach(a=>{
      if (a.file) {
        const wrap = document.createElement('div'); wrap.style.display='flex'; wrap.style.justifyContent='space-between'; wrap.style.alignItems='center';
        const name = document.createElement('div'); name.textContent = a.label + ' — ' + a.file.split('/').pop();
        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.gap = '6px';
        actions.style.alignItems = 'center';
        const viewBtn = document.createElement('button'); viewBtn.textContent = '👁'; viewBtn.title = 'View';
        viewBtn.onclick = ()=> window.open('../' + a.file, '_blank');
        const dlBtn = document.createElement('button'); dlBtn.textContent = '🡻'; dlBtn.title = 'Download';
        dlBtn.onclick = ()=> {
          const link = document.createElement('a'); link.href = '../' + a.file; link.download = ''; document.body.appendChild(link); link.click(); link.remove();
        };
        actions.appendChild(viewBtn); actions.appendChild(dlBtn);
        wrap.appendChild(name); wrap.appendChild(actions);
        attRoot.appendChild(wrap);
      }
        });

    // wire approve/reject buttons (open existing modals with this app id)
    document.getElementById('view_approve_btn').onclick = function(){ closeViewModal(); openApproveModal({ getAttribute: () => appId, getAttribute:function(){}, }); /* fallback: openApproveModal expects element; we instead call openApproveModal with a small shim below */ };
    // better: call openApproveModal by creating a dummy element with required attributes
    document.getElementById('view_approve_btn').onclick = function(){
      closeViewModal();
      const el = document.createElement('button');
      el.setAttribute('data-appid', appId);
      el.setAttribute('data-name', (s.first_name + ' ' + s.last_name).trim());
      el.setAttribute('data-email', s.email || '');
      el.setAttribute('data-opt1', d.office1 || '');
      el.setAttribute('data-opt2', d.office2 || '');
      el.setAttribute('data-opt1-id', d.office_preference1 || 0);
      el.setAttribute('data-opt2-id', d.office_preference2 || 0);
      openApproveModal(el);
    };
    document.getElementById('view_reject_btn').onclick = function(){
      closeViewModal();
      const el = document.createElement('button');
      el.setAttribute('data-appid', appId);
      el.setAttribute('data-name', (s.first_name + ' ' + s.last_name).trim());
      el.setAttribute('data-email', s.email || '');
      openRejectModal(el);
    };

  } catch (err) {
    console.error(err);
    alert('Failed to load application.');
    closeViewModal();
  }
}

function closeViewModal(){
  const overlay = document.getElementById('viewOverlay');
  overlay.style.display = 'none';
  overlay.setAttribute('aria-hidden','true');
}

// Add top-right icons handlers (notifications / settings / logout)
document.addEventListener('DOMContentLoaded', function(){
  const notifBtn = document.getElementById('btnNotif');
  const settingsBtn = document.getElementById('btnSettings');
  const logoutBtn = document.getElementById('btnLogout');

  if (notifBtn) {
    notifBtn.addEventListener('click', function(){
      alert('Walang bagong notification ngayon.');
    });
  }

  if (settingsBtn) {
    settingsBtn.addEventListener('click', function(){
      // adjust target if you have a settings page
      window.location.href = 'hr_head_settings.php';
    });
  }

  if (logoutBtn) {
    logoutBtn.addEventListener('click', function(){
      if (!confirm('Log out?')) return;
      // hr_head is one folder deep; logout.php is in project root
      window.location.href = '../logout.php';
    });
  }
});
</script>

</body>
</html>
