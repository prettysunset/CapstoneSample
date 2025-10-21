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
        <a href="#">🕒 DTR</a>
        <a href="#">⚙️ Accounts</a>
        <a href="#">📊 Reports</a>
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
        <div style="display:flex;align-items:center;gap:24px;margin-bottom:8px;">
            <div style="font-size:20px;font-weight:600;">On-the-Job Trainees (<?= count($students) ?>)</div>
            <div style="flex:1"></div>
            <div class="ojt-table-searchbar">
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
                // Office: prefer office_preference1 if approved, else office_preference2
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
                        <button class="view-btn" title="View" onclick="window.location.href='application_view.php?id=<?= (int)$row['application_id'] ?>'">👁️</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<script>
// Simple search, filter, and sort for the table
const searchInput = document.getElementById('searchInput');
const yearFilter = document.getElementById('yearFilter');
const sortBy = document.getElementById('sortBy');
const table = document.getElementById('ojtTable');
const rows = Array.from(table.tBodies[0].rows);

function filterTable() {
    const search = (searchInput.value || '').toLowerCase();
    const year = yearFilter.value;
    rows.forEach(row => {
        const cells = row.cells;
        let show = true;
        if (search && !Array.from(cells).some(td => td.textContent.toLowerCase().includes(search))) show = false;
        if (year && cells[4].textContent.trim() !== year) show = false;
        row.style.display = show ? '' : 'none';
    });
}
searchInput.addEventListener('input', filterTable);
yearFilter.addEventListener('change', filterTable);

sortBy.addEventListener('change', function(){
    const idx = {
        name: 0, office: 1, school: 2, course: 3, year: 4, hours: 5, status: 6
    }[sortBy.value];
    if (idx === undefined) return;
    const sorted = rows.slice().sort((a,b)=>{
        const ta = a.cells[idx].textContent.trim().toLowerCase();
        const tb = b.cells[idx].textContent.trim().toLowerCase();
        if (sortBy.value === 'hours') {
            // sort by rendered hours (parse int)
            const ha = parseInt(ta), hb = parseInt(tb);
            return ha - hb;
        }
        return ta.localeCompare(tb);
    });
    sorted.forEach(tr=>table.tBodies[0].appendChild(tr));
});

</script>
</body>
</html>