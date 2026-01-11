<?php
// add server-side data before rendering HTML so design/header stays unchanged
require_once __DIR__ . '/conn.php';

// detect capacity column (match logic used in hr_head_home)
$capacityCol = null;
$variants = ['current_limit','slot_capacity','capacity','slots','max_slots'];
foreach ($variants as $v) {
    $res = $conn->query("SHOW COLUMNS FROM offices LIKE '".$conn->real_escape_string($v)."'");
    if ($res && $res->num_rows > 0) { $capacityCol = $v; break; }
    if ($res) $res->free();
}

// fetch offices list with chosen capacity column (if found)
if ($capacityCol) {
    $sql = "SELECT office_id, office_name, `".$conn->real_escape_string($capacityCol)."` AS capacity FROM offices ORDER BY office_name";
} else {
    $sql = "SELECT office_id, office_name FROM offices ORDER BY office_name";
}
$offices = [];
if ($resOff = $conn->query($sql)) {
    while ($r = $resOff->fetch_assoc()) $offices[] = $r;
    if ($resOff) $resOff->free();
}

// fetch allowed courses per office (map office_id => [course_name,...])
$officeCourses = [];
$resOc = $conn->query("SELECT oc.office_id, c.course_name FROM office_courses oc JOIN courses c ON oc.course_id = c.course_id");
if ($resOc) {
    while ($row = $resOc->fetch_assoc()) {
        $officeCourses[(int)$row['office_id']][] = $row['course_name'];
    }
    $resOc->free();
}

// prepare statement to count active/approved OJTs assigned to an office (same source as hr_head_home)
$stmtFilled = $conn->prepare("
    SELECT COUNT(*) AS cnt
    FROM users
    WHERE role = 'ojt' AND office_name = ? AND status IN ('approved','ongoing')
");
?>
<html>
<head>
    <meta charset="UTF-8" />
    <link rel="stylesheet" href="stylenibilog.css" />
    <title>OJT-MS Offices</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&display=swap" rel="stylesheet">
<style>
/* General Page */
body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    padding: 0;
    background-color: #e6f2ff;
    color: #333;
}

/* Centered Modern Navbar */
.navbar {
    width: 100%;
    display: flex;
    justify-content: center;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    border-radius: 20px;
    padding: 10px 25px;
    margin: 20px auto;
}

.nav-container {
    width: 100%;
    max-width: 1100px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.logo {
    font-weight: 900;
    font-size: 1.6rem;
    letter-spacing: 1px;
    text-decoration: none;
    color: #344265;
    transition: color 0.3s ease;
}

.logo:hover {
    color: #4a6ff3;
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 25px;
    margin: 0;
    padding: 0;
    align-items: center;
}

.nav-links li {
    position: relative;
}

.nav-links a {
    text-decoration: none;
    color: #3a4163;
    font-weight: 500;
    font-size: 0.95rem;
    padding: 8px 15px;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.nav-links a::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: 0;
    transform: translateX(-50%) scaleX(0);
    transform-origin: center;
    width: 60%;
    height: 2px;
    background-color: #4a6ff3;
    transition: transform 0.3s ease;
}

.nav-links a:hover::after {
    transform: translateX(-50%) scaleX(1);
}

.nav-links a:hover {
    color: #4a6ff3;
    background-color: rgba(74, 111, 243, 0.1);
}

/* Login button */
.nav-links .login a {
    background-color: #4a6ff3;
    color: white;
    border-radius: 25px;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(74, 111, 243, 0.3);
    transition: all 0.3s ease;
}

.nav-links .login a:hover {
    background-color: #344265;
    box-shadow: 0 2px 8px rgba(52, 66, 101, 0.4);
}

/* Responsive */
@media (max-width: 700px) {
    .nav-container {
        flex-direction: column;
        gap: 10px;
    }

    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
    }

    .navbar {
        margin: 10px auto;
        padding: 15px;
        border-radius: 15px;
    }
}

/* Page Header */
h1 {
    text-align: center;
    font-weight: 700;
    margin-top: 40px;
}

/* Table Section */
.table-section {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 30px;
    margin: 30px auto;
    max-width: 1100px;
}

/* Table Style */
table {
    width: 45%;
    border-collapse: collapse;
    background-color: rgba(255, 255, 255, 0.85);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

/* Table Headers */
th {
    background-color: #f0f6ff;
    color: #3a4163;
    text-align: left;
    padding: 12px; /* reduced from 15px */
    font-weight: 700;
    font-size: 16px;
    border-bottom: 2px solid #d6e4f0;
}

.floor-header th {
    background-color: #d8e8ff !important;
    color: #3a4163 !important;
    text-align: center;
    font-size: 20px !important;
    font-weight: 800;
    border-bottom: 3px solid #b8d0f0;
    height: 60px;
    vertical-align: middle;
    letter-spacing: 0.5px;
}

/* Table Cells */
td {
    padding: 10px 12px; /* reduced from 14px 15px */
    border-bottom: 1px solid #d6e4f0;
    font-size: 15px;
}

/* Hover Effect */
tr:hover td {
    background-color: rgba(74,111,243,0.05);
}

/* Status Colors (updated: add colored stroke/border for Open/Full) */
.status{
    font-weight:600;
    display:inline-block;
    padding:6px 10px;
    border-radius:999px;
    font-size:13px;
    box-sizing:border-box;
    border:2px solid transparent; /* default stroke */
}

/* Open: green text + green stroke + subtle green bg */
.status.open{
    color:#0b7a3a; /* dark green text */
    background: rgba(16,185,129,0.06); /* subtle green tint */
    border-color:#10b981; /* green stroke */
}

/* Full: red text + red stroke + subtle red bg */
.status.full{
    color:#b91c1c; /* dark red text */
    background: rgba(220,38,38,0.04); /* subtle red tint */
    border-color:#ef4444; /* red stroke */
}

/* Course badges */
.course-badge{
    display:inline-block;
    background:rgba(58,65,99,0.06);
    color:#344265;
    padding:6px 8px;
    border-radius:999px;
    font-size:13px;
    margin:2px 4px 2px 0;
    border:1px solid rgba(52,66,101,0.06);
}
</style>
</head>
<body>
    <!-- ✅ Centered Navbar -->
    <nav class="navbar" role="navigation">
        <div class="nav-container">
            <a class="logo" href="about.php">OJT-MS</a>
            <ul class="nav-links">
                <li><a href="home.php">Home</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="contacts.php">Contacts</a></li>
                <li><a href="offices.php" style="color:#4a6ff3; font-weight:700;">Offices</a></li>
                <li class="login"><a href="login.php">Login</a></li>
            </ul>
        </div>
    </nav>

    <h1>OJT Slot <span style="color: #4a6ff3;">Availability</span></h1>

    <!-- ===== simplified single table with accurate counts (based on hr_head_home logic) ===== -->
    <div class="table-section" style="justify-content:center;max-width:1100px;margin-bottom:40px;">
        <?php if (empty($offices)): ?>
            <div class="empty">No offices found.</div>
        <?php else: ?>
            <!-- search bar above the table -->
            <div style="width:100%;max-width:900px;margin:0 auto 12px;display:block;">
                <input id="officeSearch" type="search" placeholder="Search office / slots / status" aria-label="Search offices"
                       style="width:100%;padding:8px 10px;border:1px solid #e6e9fb;border-radius:8px;background:#fff;box-sizing:border-box;">
            </div>

            <div style="width:100%;max-width:900px;margin:0 auto;overflow:hidden;">
            <table id="officesTable" style="width:100%;border-collapse:collapse;background-color:rgba(255,255,255,0.95);">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:12px;border:1px solid #e6e9fb">Office</th>
                                        <th style="text-align:left;padding:12px;border:1px solid #e6e9fb">Allowed Courses</th>
                        <th style="text-align:center;padding:12px;border:1px solid #e6e9fb">Available Slots</th>
                        <th style="text-align:center;padding:12px;border:1px solid #e6e9fb">Status</th>
                    </tr>
                </thead>
                <tbody id="offices_tbody">
                <?php foreach ($offices as $o):
                    $officeName = $o['office_name'] ?? '';
                    $officeId = isset($o['office_id']) ? (int)$o['office_id'] : 0;
                    $cap = array_key_exists('capacity', $o) ? (is_null($o['capacity']) ? null : (int)$o['capacity']) : null;

                    $filled = 0;
                    if ($stmtFilled) {
                        $stmtFilled->bind_param('s', $officeName);
                        $stmtFilled->execute();
                        $stmtFilled->bind_result($cnt);
                        $stmtFilled->fetch();
                        $filled = (int)($cnt ?? 0);
                        $stmtFilled->free_result();
                    }

                    if ($cap === null) {
                        $availableDisplay = '—';
                        $statusLabel = 'Open';
                        $statusClass = 'status-open';
                    } else {
                        $availableNum = max(0, $cap - $filled);
                        $availableDisplay = $availableNum;
                        if ($availableNum === 0) {
                            $statusLabel = 'Full';
                            $statusClass = 'status-full';
                        } else {
                            $statusLabel = 'Open';
                            $statusClass = 'status-open';
                        }
                    }
                ?>
                    <tr class="office-row">
                        <td class="col-office" style="padding:10px;border:1px solid #e6e9fb;"><?= htmlspecialchars($officeName ?: '—') ?></td>
                        <td class="col-courses" style="padding:10px;border:1px solid #e6e9fb;">
                            <?php
                                $courseList = $officeCourses[$officeId] ?? [];
                                if (empty($courseList)) {
                                    echo '—';
                                } else {
                                    foreach ($courseList as $cn) {
                                        echo '<span class="course-badge">'.htmlspecialchars($cn).'</span> ';
                                    }
                                }
                            ?>
                        </td>
                        <td class="col-available" style="text-align:center;padding:10px;border:1px solid #e6e9fb"><?= htmlspecialchars((string)$availableDisplay) ?></td>
                        <td class="col-status" style="text-align:center;padding:10px;border:1px solid #e6e9fb"><span class="<?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <div id="noResults" class="empty" style="display:none;text-align:center;padding:14px;color:#6b7280">No matching offices found.</div>
        <?php endif; ?>
    </div>

    <script>
    // live client-side filtering (filters as you type)
    (function(){
        const input = document.getElementById('officeSearch');
        const tbody = document.getElementById('offices_tbody');
        if (!input || !tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr.office-row'));
        const noResults = document.getElementById('noResults');

        function norm(s){ return (s||'').toString().toLowerCase().trim(); }

        function filter(){
            const q = norm(input.value);
            let any = false;
            rows.forEach(r=>{
                const office = norm(r.querySelector('.col-office')?.textContent);
                const available = norm(r.querySelector('.col-available')?.textContent);
                const status = norm(r.querySelector('.col-status')?.textContent);
                    const courses = norm(r.querySelector('.col-courses')?.textContent);
                const show = !q || office.indexOf(q) !== -1 || available.indexOf(q) !== -1 || status.indexOf(q) !== -1;
                    // include courses in search
                    const showWithCourses = !q || courses.indexOf(q) !== -1 || office.indexOf(q) !== -1 || available.indexOf(q) !== -1 || status.indexOf(q) !== -1;
                    r.style.display = showWithCourses ? '' : 'none';
                    if (showWithCourses) any = true;
            });
            if (!any) {
                noResults && (noResults.style.display = '');
            } else {
                noResults && (noResults.style.display = 'none');
            }
        }

        input.addEventListener('input', filter, {passive:true});
        // initial run to apply any pre-filled value
        filter();
    })();
    </script>
<?php
if ($stmtFilled) $stmtFilled->close();
?>
<!-- ...existing code (rest of page) ... -->
</body>
</html>
