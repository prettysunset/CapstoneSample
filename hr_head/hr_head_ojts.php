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

// --- NEW: load MOA rows for client usage (array of {school_name, moa_file}) ---
$moa_rows = [];
$moa_q = $conn->query("SELECT school_name, moa_file FROM moa");
if ($moa_q) {
    while ($r = $moa_q->fetch_assoc()) {
        $sn = trim($r['school_name'] ?? '');
        $mf = trim($r['moa_file'] ?? '');
        if ($sn === '' || $mf === '') continue; // only include valid rows
        $moa_rows[] = ['school_name' => $sn, 'moa_file' => $mf];
    }
    $moa_q->free();
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
    thead th {
                  background: #dadadaff;
                  color: black;
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

    /* View modal styles (adjusted) */
    .view-overlay { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(16,24,40,0.28); z-index: 9999; padding: 18px; }
    /* lock page when modal open */
    body.modal-open { overflow: hidden; height: 100%; }

    .view-card {
      width: 880px;
      max-width: 94vw;
      border-radius: 20px;
      background: transparent;
      box-shadow: 0 22px 60px rgba(16,24,40,0.28);
      overflow: visible;
      position: relative;
      padding: 18px;
      font-family: 'Poppins', sans-serif;
      max-height: 80vh;
      display:flex;
      flex-direction:column;
    }

    .view-inner {
      background:#fff;
      border-radius:14px;
      padding:18px;
      box-shadow: none;
      border: 1px solid rgba(231,235,241,0.9);
      min-height: 460px;
      max-height: calc(80vh - 36px);
      display:flex;
      flex-direction:column;
      overflow:hidden; /* panel will scroll, not whole page */
    }
    
    /* panels scroll internally */
    .view-panel { flex:1 1 auto; min-height:360px; box-sizing:border-box; overflow:auto; padding-top:8px; }

    /* close button */
    .view-close { position: absolute; right: 18px; top: 18px; width:36px;height:36px;border-radius:50%;background:#fff;border:0;box-shadow:0 6px 18px rgba(16,24,40,0.06);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:18px; z-index:10010; }

    /* header */
    .view-header { display:flex; gap:18px; align-items:center; margin-bottom:6px; }
    .view-avatar { width:96px;height:96px;border-radius:50%;background:#eceff3;flex:0 0 96px; display:flex;align-items:center;justify-content:center; overflow:hidden; }
    .view-avatar img{ width:100%; height:100%; object-fit:cover; display:block; }
    .view-name { font-size:20px; font-weight:800; color:#222e50; margin:0 0 6px 0; letter-spacing:0.2px; }
    .view-submeta { font-size:13px; color:#6b7280; display:flex; gap:12px; align-items:center; }

    /* small utility icons / prints */
    .view-tools { display:flex; gap:12px; align-items:center; margin-top:8px; }

    /* tabs inline */
    .view-tabs { display:flex; gap:20px; align-items:center; margin-top:10px; padding-bottom:10px; }
    .view-tab { padding:6px 10px; cursor:pointer; border-radius:6px; color:#6b7280; font-weight:700; font-size:13px; }
    .view-tab.active { color:#1f2937; border-bottom:3px solid #344154; }

    /* body layout inside inner box */
    .view-body { display:flex; gap:18px; margin-top:8px; align-items:flex-start; }
    .view-left { flex:1; padding:14px; border-radius:10px; min-width:320px; }
    .view-right { width:340px; min-width:260px; padding:14px; }

    /* info rows: compact, labels narrow and bold values */
    .info-row{ display:flex; gap:10px; padding:6px 0; align-items:flex-start; }
    .info-label{ width:110px; font-weight:700; color:#222e50; font-size:13px; }
    .info-value{ color:#111827; font-weight:800; font-size:13px; line-height:1.1; }
    hr.section-sep{ border:0; border-top:1px solid #eef2f6; margin:12px 0; }

    /* emergency block smaller spacing */
    .emergency{ margin-top:12px; padding-top:8px; border-top:1px solid #eef2f6; }

    /* donut smaller and vertically centered */
    .donut { width:100px; height:100px; display:grid; place-items:center; }
    .donut svg { transform:rotate(-90deg); }
    .donut .percent{ position:absolute; font-weight:800; color:#111827; font-size:15px; }
    .progress-meta{ font-weight:800; color:#111827; font-size:14px; }

    @media (max-width:980px){
      .view-card{ width:calc(100% - 32px); padding:12px; }
      .view-inner{ padding:12px; }
      .view-body{ flex-direction:column; }
      .view-avatar { width:72px;height:72px; flex:0 0 72px; }
      .view-right{ width:100%; min-width:0; }
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
      <a href="hr_head_home.php">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px">
          <path d="M3 11.5L12 4l9 7.5"></path>
          <path d="M5 12v7a1 1 0 0 0 1 1h3v-5h6v5h3a1 1 0 0 0 1-1v-7"></path>
        </svg>
        Home
      </a>
      <a href="#" class="active">
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
  <!-- top-right outline icons: notifications, settings, logout
       NOTE: removed position:fixed to prevent overlapping; icons now flow with page
       and stay visible. -->
  <div id="top-icons" style="display:flex;justify-content:flex-end;gap:14px;align-items:center;margin:8px 0 12px 0;z-index:50;">
      <a href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0 1 18 14.158V11a6 6 0 1 0-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
      </a>
      <a href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82L4.3 4.46a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09c0 .64.38 1.2 1 1.51h.09a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.64.3 1.03.87 1.03 1.51V12c0 .64-.39 1.21-1.03 1.51z"></path></svg>
      </a>
      <a id="top-logout" href="/logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;background:transparent;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2f3459" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
      </a>
  </div>

    <div class="top-section">
        <div>
            <div class="datetime">
                <h2><?= $current_time ?></h2>
                <p><?= $current_date ?></p>
            </div>
        </div>
    </div>
    <div class="table-container">
      <div style="display:flex;flex-direction:column;gap:12px;">
      <!-- First row: centered tabs (no background on buttons, only underline at bottom) -->
      <div class="tabs" role="tablist" aria-label="OJT Tabs" style="display:flex;justify-content:center;align-items:flex-end;gap:24px;font-size:18px;">
        <button class="tab active" data-tab="ojts" role="tab" aria-selected="true" aria-controls="tab-ojts" style="background:transparent;border:none;padding:10px 14px;border-radius:6px;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;">
          On-the-Job Trainees (<?= count($students) ?>)
        </button>
        <button class="tab" data-tab="requested" role="tab" aria-selected="false" aria-controls="tab-requested" style="background:transparent;border:none;padding:10px 14px;border-radius:6px;cursor:pointer;color:#2f3850;font-weight:600;outline:none;font-size:18px;">
          Requested OJTs
        </button>
      </div>

      <!-- underline bar (moved under the buttons row) -->
      <div id="tabsUnderline" aria-hidden="true" style="height:3px;background:#2f3850;border-radius:3px;width:180px;transition:all .25s;margin-bottom:12px;margin-top:6px;"></div>

      <!-- Second row: search / filters / sort (now spans full width with icons) -->
      <div style="display:flex;align-items:center;gap:12px;width:100%;padding:6px 0;">
        <div class="ojt-table-searchbar" style="flex:1;display:flex;align-items:center;gap:8px;">
          <!-- Search input with icon -->
          <div style="display:flex;align-items:center;background:#f7f8fc;border:1px solid #ccc;border-radius:8px;padding:6px 8px;min-width:0;flex:1;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" style="flex:0 0 auto;margin-right:8px;">
          <path d="M21 21l-4.35-4.35" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="11" cy="11" r="6" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <input type="text" id="searchInput" placeholder="Search" aria-label="Search" style="border:0;background:transparent;outline:none;padding:6px 4px;font-size:15px;flex:0 0 50%;width:50%;min-width:0;max-width:50%;"
               onfocus="this.style.outline='3px solid #2f3850';this.style.outlineOffset='2px';this.parentElement.style.boxShadow='0 0 0 3px rgba(47,56,80,0.08)';"
               onblur="this.style.outline='';this.style.outlineOffset='';this.parentElement.style.boxShadow='';">
          </div>

          <!-- Year filter -->
          <select id="yearFilter" aria-label="Filter by year" style="padding:8px 10px;border-radius:8px;border:1px solid #ccc;background:#f7f8fc;font-size:15px;flex:0 0 110px;"
            onfocus="this.style.outline='3px solid #2f3850';this.style.outlineOffset='2px';this.style.boxShadow='0 0 0 3px rgba(47,56,80,0.08)';"
            onblur="this.style.outline='';this.style.outlineOffset='';this.style.boxShadow='';">
        <option value="">Year</option>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
        <option value="4">4</option>
            </select>

            <!-- Sort by with icon inside the select (icon absolutely positioned) -->
            <div style="flex:0 0 220px;position:relative;display:inline-block;">
            <select id="sortBy" aria-label="Sort by" style="padding:8px 40px 8px 12px;border-radius:8px;border:1px solid #ccc;background:#f7f8fc;font-size:15px;width:100%;box-sizing:border-box;appearance:none;-webkit-appearance:none;-moz-appearance:none;cursor:pointer;"
              onfocus="this.style.outline='3px solid #2f3850';this.style.outlineOffset='2px';this.style.boxShadow='0 0 0 3px rgba(47,56,80,0.08)';"
              onblur="this.style.outline='';this.style.outlineOffset='';this.style.boxShadow='';">
              <option value="">Sort by</option>
              <option value="name">Name</option>
              <option value="office">Office</option>
              <option value="school">School</option>
              <option value="course">Course</option>
              <option value="year">Year Level</option>
              <option value="hours">Hours</option>
              <option value="status">Status</option>
            </select>
            <div aria-hidden="true" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;display:flex;align-items:center;justify-content:center;width:24px;height:24px;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" focusable="false">
              <path d="M6 9h12" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M6 15h8" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M10 5l-4 4" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M14 19l4-4" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            </div>
          </div>
          </div>
      </div>
      </div>

    <!-- underline bar -->
    <div id="tabsUnderline" aria-hidden="true" style="height:3px;background:#2f3850;border-radius:3px;width:180px;transition:all .25s;margin-bottom:12px;"></div>

    <!-- Tab panels -->
    <div id="tab-ojts" class="tab-panel" role="tabpanel" aria-labelledby="tab-ojts" style="display:block;">
        <div style="overflow-x:auto;">
        <table id="ojtTable">
            <thead>
                <tr style="background-color:#f5f6fa;">
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
                        <button class="view-btn" title="View" onclick="openViewModal(<?= (int)$row['application_id'] ?>)" aria-label="View">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/>
                            <circle cx="12" cy="12" r="3"/>
                          </svg>
                        </button>
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

<!-- View Application Modal (insert near end of body) -->
<div id="viewOverlay" class="view-overlay" aria-hidden="true" style="display:none;">
  <div class="view-card" role="dialog" aria-modal="true" aria-labelledby="viewTitle">
    <button class="view-close" aria-label="Close modal" onclick="window.closeViewModal && window.closeViewModal()">✕</button>

    <!-- inner white container -->
    <div class="view-inner">
      <div class="view-header">
        <div class="view-avatar" id="view_avatar"> <!-- image inserted via JS --> </div>
        <div class="view-meta">
          <h2 class="view-name" id="view_name">Name Surname</h2>
          <div class="view-submeta" id="view_statusline">
            <span id="view_status_badge" style="display:flex;align-items:center;gap:8px;font-weight:700;color:#0b7a3a">
              <span style="width:10px;height:10px;background:#10b981;border-radius:50%;display:inline-block"></span>
              Active OJT
            </span>
            <span id="view_department" style="display:flex;align-items:center;gap:6px;color:#6b7280">IT Department</span>
          </div>

          <div class="view-tools" aria-hidden="true">
            <button class="tool-link" id="printEndorse">Print Endorsement</button>
            <button class="tool-link" id="printDTR">Print DTR</button>
          </div>
        </div>
      </div>

      <div class="view-tabs" role="tablist" aria-label="View tabs">
        <div class="view-tab active" data-tab="info" onclick="switchViewTab(event)">Information</div>
        <div class="view-tab" data-tab="late" onclick="switchViewTab(event)">Late DTR Submissions</div>
        <div class="view-tab" data-tab="atts" onclick="switchViewTab(event)">Attachments</div>
        <div class="view-tab" data-tab="eval" onclick="switchViewTab(event)">Evaluation</div>
      </div>

      <!-- Panels: info uses the two-column view-body, other panels span full inner width -->
      <div id="panel-info" class="view-panel" style="display:block;">
        <div class="view-body">
          <div class="view-left">
            <div style="display:flex;gap:12px;">
              <div style="flex:1">
                <div class="info-row"><div class="info-label">Age</div><div class="info-value" id="view_age">—</div></div>
                <div class="info-row"><div class="info-label">Birthday</div><div class="info-value" id="view_birthday">—</div></div>
                <div class="info-row"><div class="info-label">Address</div><div class="info-value" id="view_address">—</div></div>
                <div class="info-row"><div class="info-label">Phone</div><div class="info-value" id="view_phone">—</div></div>
                <div class="info-row"><div class="info-label">Email</div><div class="info-value" id="view_email">—</div></div>
              </div>
            </div>

            <div style="height:14px"></div>

            <div style="border-top:1px solid #f1f5f9;padding-top:12px;">
              <div class="info-row"><div class="info-label">College/University</div><div class="info-value" id="view_college">—</div></div>
              <div class="info-row"><div class="info-label">Course</div><div class="info-value" id="view_course">—</div></div>
              <div class="info-row"><div class="info-label">Year level</div><div class="info-value" id="view_year">—</div></div>
              <div class="info-row"><div class="info-label">School Address</div><div class="info-value" id="view_school_address">—</div></div>
              <div class="info-row"><div class="info-label">OJT Adviser</div><div class="info-value" id="view_adviser">—</div></div>
            </div>

            <div class="emergency">
              <div style="font-weight:700;margin-bottom:8px">Emergency Contact</div>
              <div class="info-row"><div class="info-label" style="width:120px">Name</div><div class="info-value" id="view_emg_name">—</div></div>
              <div class="info-row"><div class="info-label">Relationship</div><div class="info-value" id="view_emg_rel">—</div></div>
              <div class="info-row"><div class="info-label">Contact Number</div><div class="info-value" id="view_emg_contact">—</div></div>
            </div>
          </div>

          <div class="view-right">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <div style="font-weight:700">Progress</div>
              <div style="font-size:12px;color:#6b7280">Expected / Required</div>
            </div>

            <div class="progress-wrap" style="display:flex;gap:12px;align-items:flex-start;margin-top:8px">
              <div class="donut" id="view_donut">
                <svg width="120" height="120" viewBox="0 0 120 120">
                  <circle cx="60" cy="60" r="48" stroke="#eef2f6" stroke-width="18" fill="none"></circle>
                  <circle id="donut_fore" cx="60" cy="60" r="48" stroke="#10b981" stroke-width="18" stroke-linecap="round" fill="none" stroke-dasharray="302" stroke-dashoffset="302"></circle>
                </svg>
                <div style="position:absolute;font-weight:800;color:#111827;font-size:16px" id="view_percent">0%</div>
              </div>
              <div style="flex:1">
                <div style="font-size:14px;font-weight:700" id="view_hours_text">0 out of 500 hours</div>
                <div style="font-size:12px;color:#6b7280;margin-top:6px" id="view_dates">Date Started: — <br> Expected End Date: —</div>
                <div class="assigned" id="view_assigned" style="margin-top:10px">
                  <div style="font-weight:700">Assigned Office:</div>
                  <div id="view_assigned_office">—</div>
                  <div style="margin-top:8px;font-weight:700">Office Head:</div>
                  <div id="view_office_head">—</div>
                  <div style="margin-top:8px;font-weight:700">Contact #:</div>
                  <div id="view_office_contact">—</div>
                </div>
              </div>
            </div>

          </div>
        </div> <!-- .view-body -->
      </div> <!-- #panel-info -->

      <div id="panel-late" class="view-panel" style="display:none;padding:12px 6px;">
        <div style="background:#fff;border-radius:10px;padding:12px;border:1px solid #eef2f6;">
          <div style="overflow:auto">
            <table aria-label="Late DTR Submissions" style="width:100%;border-collapse:collapse;font-size:14px">
              <thead>
                <tr style="background:#f3f4f6;color:#111">
                  <th rowspan="2" style="padding:10px;border:1px solid #eee;text-align:left">Date Filed</th>
                  <th colspan="2" style="padding:10px;border:1px solid #eee;text-align:center">A.M.</th>
                  <th colspan="2" style="padding:10px;border:1px solid #eee;text-align:center">P.M.</th>
                  <th rowspan="2" style="padding:10px;border:1px solid #eee;text-align:center">HOURS</th>
                  <th rowspan="2" style="padding:10px;border:1px solid #eee;text-align:left">DATE</th>
                  <th rowspan="2" style="padding:10px;border:1px solid #eee;text-align:left">STATUS</th>
                </tr>
                <tr style="background:#f3f4f6;color:#111">
                  <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">ARRIVAL</th>
                  <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">DEPARTURE</th>
                  <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">ARRIVAL</th>
                  <th style="padding:8px;border:1px solid #eee;text-align:center;font-weight:700">DEPARTURE</th>
                </tr>
              </thead>
              <tbody id="late_dtr_tbody">
                <tr class="empty">
                  <td colspan="8" style="padding:18px;text-align:center;color:#6b7280">No late DTR submissions found.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div id="panel-atts" class="view-panel" style="display:none;padding:12px 6px;">
        <div id="attachments_full" style="background:#fff;border-radius:10px;padding:12px;border:1px solid #eef2f6;min-height:160px;">
          <div id="view_attachments_list" style="display:flex;flex-direction:column;gap:8px;"></div>
        </div>
      </div>

      <div id="panel-eval" class="view-panel" style="display:none;padding:12px 6px;">
        <div style="background:#fff;border-radius:10px;padding:12px;border:1px solid #eef2f6;min-height:160px;color:#6b7280" id="eval_full">
          Evaluation content here.
        </div>
      </div>
     </div> <!-- .view-inner -->
   </div> <!-- .view-card -->
 </div> <!-- #viewOverlay -->

<!-- expose mapping to JS -->
<script>
  window.moaBySchool = <?php echo json_encode($moa_rows, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
</script>

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

    /* tab switcher - show panel-* elements */
    function switchViewTab(e){
      // support being called either with an Event or with a tab name string
      const tab = (typeof e === 'string') ? e : (e.currentTarget ? e.currentTarget.getAttribute('data-tab') : null);
      if (!tab) return;
      // update active tab button
      document.querySelectorAll('.view-tab').forEach(t=>t.classList.remove('active'));
      const btn = document.querySelector('.view-tab[data-tab="'+tab+'"]');
      if (btn) btn.classList.add('active');
      // hide all panels and show the selected one
      document.querySelectorAll('.view-panel').forEach(p=>p.style.display = 'none');
      const panel = document.getElementById('panel-' + tab);
      if (panel) panel.style.display = 'block';
    }

    // expose for inline onclick and add robust event listeners
    window.switchViewTab = switchViewTab;
    document.querySelectorAll('.view-tab').forEach(t => {
      t.removeAttribute('onclick'); // optional: avoid duplicate handlers
      t.addEventListener('click', switchViewTab);
    });
    // openViewModal: fetch application details and populate modal
    window.openViewModal = async function(appId){
      showViewOverlay();
      // reset
      ['view_name','view_age','view_birthday','view_address','view_phone','view_email','view_college','view_course','view_year','view_school_address','view_adviser','view_emg_name','view_emg_rel','view_emg_contact','view_hours_text','view_dates','view_assigned_office','view_office_head','view_office_contact','view_attachments_list'].forEach(id=>{
        const el = document.getElementById(id);
        if(el) el.textContent = '—';
      });
      // avatar
      const avatarEl = document.getElementById('view_avatar');
      avatarEl.innerHTML = '👤';

      try{
        const res = await fetch('../hr_actions.php', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ action:'get_application', application_id: parseInt(appId,10) })
        });
        const json = await res.json();
        if (!json.success) { alert('Failed to load application'); closeViewModal(); return; }
        const d = json.data;
        const s = d.student || {};

        // top meta
        document.getElementById('view_name').textContent = ((s.first_name||'') + ' ' + (s.last_name||'')).trim() || 'N/A';
        document.getElementById('view_status_badge').style.display = d.status && d.status.toLowerCase()==='approved' ? 'inline-flex' : 'none';
        document.getElementById('view_department').textContent = d.office1 || d.office || '—';

        // avatar image if available
        if (d.picture){
          avatarEl.innerHTML = '';
          const img = document.createElement('img');
          img.src = '../' + d.picture;
          img.alt = 'avatar';
          avatarEl.appendChild(img);
        }

        // personal info
        document.getElementById('view_age').textContent = s.age || '—';
        document.getElementById('view_birthday').textContent = s.birthday || (s.birthdate||'—');
        document.getElementById('view_address').textContent = s.address || s.school_address || '—';
        document.getElementById('view_phone').textContent = s.contact_number || '—';
        document.getElementById('view_email').textContent = s.email || '—';

        // school info
        document.getElementById('view_college').textContent = s.college || '—';
        document.getElementById('view_course').textContent = s.course || '—';
        document.getElementById('view_year').textContent = s.year_level || '—';
        document.getElementById('view_school_address').textContent = s.school_address || '—';
        document.getElementById('view_adviser').textContent = (s.ojt_adviser || '') + (s.adviser_contact ? ' | ' + s.adviser_contact : '');

        // emergency contact (if provided)
        if (s.emg_name || s.emg_contact){
          document.getElementById('view_emg_name').textContent = s.emg_name || s.emergency_name || '—';
          document.getElementById('view_emg_rel').textContent = s.emg_relation || s.emergency_relation || '—';
          document.getElementById('view_emg_contact').textContent = s.emg_contact || s.emergency_contact || '—';
        }

        // hours + progress
        const rendered = Number(s.hours_rendered || d.hours_rendered || 0);
        const required = Number(s.total_hours_required || d.total_hours_required || 500);
        document.getElementById('view_hours_text').textContent = `${rendered} out of ${required} hours`;
        const start = d.date_started || d.date_submitted || '—';
        const expected = d.expected_end_date || d.expected_end || '—';
        document.getElementById('view_dates').textContent = `Date Started: ${start}\nExpected End Date: ${expected}`;
        const pct = required>0 ? (rendered / required * 100) : 0;
        setDonut(pct);

        // assigned office block
        document.getElementById('view_assigned_office').textContent = d.office1 || d.office || '—';
        document.getElementById('view_office_head').textContent = d.office_head || d.office_head_name || '—';
        document.getElementById('view_office_contact').textContent = d.office_contact || '—';

        // attachments (existing attachments from application)
        const attRoot = document.getElementById('view_attachments_list');
        attRoot.innerHTML = '';
        // base attachments from application record
        const attachments = [
          {label:'Letter of Intent', file:d.letter_of_intent},
          {label:'Endorsement', file:d.endorsement_letter},
          {label:'Resume', file:d.resume},
          {label:'MOA (application)', file:d.moa_file},
          {label:'Picture', file:d.picture}
        ].filter(a=>a && a.file); // remove falsy items

        // prefer server-provided school_moa (if hr_actions returned it)
        if (d.school_moa && !attachments.some(a=>a.file === d.school_moa)) {
          attachments.push({ label: 'MOA (school)', file: d.school_moa });
        }

        // fallback: simple deterministic match against embedded MOA rows (logs for debugging)
        (function(){
          try{
            console.log('MOA rows embedded on page:', window.moaBySchool);
            const schoolRaw = (s.college || s.school_name || s.school || s.school_address || '').toString().trim();
            console.log('student school value:', schoolRaw);
            if (!schoolRaw || !Array.isArray(window.moaBySchool) || !window.moaBySchool.length) return;
            const normalize = txt => (txt||'').toString().toLowerCase().replace(/[^\w\s]/g,' ').replace(/\s+/g,' ').trim();
            const sNorm = normalize(schoolRaw);
            for(const entry of window.moaBySchool){
              if (!entry || !entry.school_name) continue;
              const eNorm = normalize(entry.school_name);
              if (!eNorm) continue;
              // direct equality or substring match (both directions)
              if (eNorm === sNorm || eNorm.includes(sNorm) || sNorm.includes(eNorm)) {
                if (!attachments.some(a=>a.file === entry.moa_file)) {
                  attachments.push({ label: 'MOA (school)', file: entry.moa_file });
                  console.log('MOA matched and added:', entry);
                }
                break;
              }
            }
          }catch(ex){
            console.warn('MOA matching error', ex);
          }
        })();

        // render attachments list
        attachments.forEach(a=>{
          const filePath = a.file || '';
          const row = document.createElement('div');
          row.style.display='flex';
          row.style.justifyContent='space-between';
          row.style.alignItems='center';
          row.style.padding='6px 0';
          const safe = filePath.replace(/'/g,"\\'");
          row.innerHTML = `<div style="font-size:14px;font-weight:600">${a.label}</div>
                           <div style="display:flex;gap:8px">
                             <button class="tool-link" onclick="window.open('../${safe}','_blank')">View</button>
                             <button class="tool-link" onclick="(function(f){const aL=document.createElement('a');aL.href='../'+f;aL.download='';document.body.appendChild(aL);aL.click();aL.remove();})('${safe}')">Download</button>
                           </div>`;
          attRoot.appendChild(row);
        });

        // wire print buttons (simple open new window to printable endpoint)
        document.getElementById('printEndorse').onclick = function(){ window.open('print_endorsement.php?id=' + encodeURIComponent(appId),'_blank'); };
        document.getElementById('printDTR').onclick = function(){ window.open('print_dtr.php?id=' + encodeURIComponent(appId),'_blank'); };

      }catch(err){
        console.error(err);
        alert('Failed to load details');
        closeViewModal();
      }
    }

    // show/hide helpers (exposed globally so inline onclick works)
    window.showViewOverlay = function(){ const o=document.getElementById('viewOverlay'); if(o){ o.style.display='flex'; o.setAttribute('aria-hidden','false'); } };
    window.closeViewModal = function(){ const o=document.getElementById('viewOverlay'); if(o){ o.style.display='none'; o.setAttribute('aria-hidden','true'); } };

    // close when clicking outside the inner box
    (function(){
      const overlay = document.getElementById('viewOverlay');
      if (!overlay) return;
      overlay.addEventListener('click', function(e){
        if (e.target === overlay) window.closeViewModal();
      });
      // Esc to close
      document.addEventListener('keydown', function(ev){
        if (ev.key === 'Escape') window.closeViewModal();
      });
    })();

    /* helper to set donut progress */
    function setDonut(percent){
      percent = Math.max(0, Math.min(100, Number(percent) || 0));
      const circle = document.getElementById('donut_fore');
      const radius = 48;
      const circumference = 2 * Math.PI * radius;
      const offset = circumference - (percent / 100) * circumference;
      circle.style.strokeDasharray = circumference;
      circle.style.strokeDashoffset = offset;
      document.getElementById('view_percent').textContent = Math.round(percent) + '%';
    }
})();
</script>
</body>
</html>