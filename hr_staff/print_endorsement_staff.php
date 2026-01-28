<?php
require_once __DIR__ . '/../conn.php';
// Simple printable endorsement letter generator
// Supports: ?application_id=NN  -> prints single
//           ?student_id=NN      -> prints single (find latest application if needed)
//           ?session_id=NN&all=1 -> prints all assigned students in that session

function fetch_hr_head($conn){
    $q = $conn->query("SELECT first_name, last_name FROM users WHERE role = 'hr_head' LIMIT 1");
    if ($q) {
        $r = $q->fetch_assoc();
        if ($r) return trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
    }
    return 'HR Head';
}

function fetch_office_head_by_officeid($conn, $office_id){
    $office_name = null; $head = null;
    $so = $conn->prepare('SELECT office_name FROM offices WHERE office_id = ? LIMIT 1');
    if ($so) { $so->bind_param('i',$office_id); $so->execute(); $ro = $so->get_result()->fetch_assoc(); if ($ro) $office_name = $ro['office_name']; $so->close(); }
    if ($office_name) {
        $su = $conn->prepare('SELECT first_name, last_name FROM users WHERE role = "office_head" AND office_name = ? LIMIT 1');
        if ($su) { $su->bind_param('s', $office_name); $su->execute(); $ru = $su->get_result()->fetch_assoc(); if ($ru) $head = trim(($ru['first_name'] ?? '') . ' ' . ($ru['last_name'] ?? '')); $su->close(); }
    }
    return [$office_name, $head];
}

function fetch_application_info($conn, $application_id){
    $sql = 'SELECT app.application_id, app.student_id, app.office_preference1, s.first_name, s.last_name, s.college, s.course, s.total_hours_required
            FROM ojt_applications app
            LEFT JOIN students s ON app.student_id = s.student_id
            WHERE app.application_id = ? LIMIT 1';
    $st = $conn->prepare($sql);
    if (!$st) return null;
    $st->bind_param('i',$application_id);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    return $row;
}

function fetch_latest_application_by_student($conn, $student_id){
    $sql = 'SELECT app.application_id FROM ojt_applications app WHERE app.student_id = ? ORDER BY app.date_updated DESC, app.application_id DESC LIMIT 1';
    $st = $conn->prepare($sql);
    if (!$st) return null;
    $st->bind_param('i',$student_id);
    $st->execute();
    $res = $st->get_result();
    $r = $res ? $res->fetch_assoc() : null;
    $st->close();
    return $r['application_id'] ?? null;
}

function fetch_assignments_for_session($conn, $session_id){
    $sql = 'SELECT oa.application_id, a.student_id, s.first_name, s.last_name, s.college, s.course, s.total_hours_required, app.office_preference1
            FROM orientation_assignments oa
            JOIN ojt_applications app ON oa.application_id = app.application_id
            LEFT JOIN ojt_applications a ON a.application_id = oa.application_id
            LEFT JOIN students s ON app.student_id = s.student_id
            WHERE oa.session_id = ?';
    // Note: some schemas vary; try a more compatible query
    $st = $conn->prepare('SELECT oa.application_id, app.student_id, s.first_name, s.last_name, s.college, s.course, s.total_hours_required, app.office_preference1
            FROM orientation_assignments oa
            LEFT JOIN ojt_applications app ON oa.application_id = app.application_id
            LEFT JOIN students s ON app.student_id = s.student_id
            WHERE oa.session_id = ?');
    if (!$st) return [];
    $st->bind_param('i', $session_id);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
    $st->close();
    return $rows;
}

// render one letter HTML
function render_letter($data){
    // $data must contain: student_fullname, course, college, total_hours_required, office_name, office_head_name, hr_head_name
    $date = date('F j, Y');
    $student = htmlspecialchars($data['student_fullname'] ?? '');
    $course = htmlspecialchars($data['course'] ?? '');
    $college = htmlspecialchars($data['college'] ?? '');
    $hours = htmlspecialchars((string)($data['total_hours_required'] ?? ''));
    $office = htmlspecialchars($data['office_name'] ?? '');
    $office_head = htmlspecialchars($data['office_head_name'] ?? '');
    $hr_head = htmlspecialchars($data['hr_head_name'] ?? '');
    // Layout tuned to match the provided endorsement photo
    $html = "<div style='page-break-after:always;padding:36px 40px;font-family:Arial, Helvetica, sans-serif;color:#111'>";

    // centered seal / emblem (hidden if missing)
    $html .= "<div style='text-align:center;margin-bottom:6px'>";
    $html .= "<img src='../assets/seal.png' alt='' style='width:64px;height:auto;display:block;margin:0 auto' onerror='this.style.display=\"none\"'/>";
    $html .= "</div>";

    // authority lines and red office header with underline
    $html .= "<div style='text-align:center;margin-bottom:6px;font-size:12px;color:#333'>Republic of the Philippines<br>Province of Bulacan<br>City of Malolos</div>";
    $html .= "<div style='text-align:center;color:#c0392b;font-weight:800;font-size:20px;margin-bottom:8px;padding-bottom:6px;border-bottom:2px solid rgba(192,57,43,0.12)'>Office of the City Human Resource Management Officer</div>";

    // left date
    $html .= "<div style='text-align:left;margin-top:12px;margin-bottom:18px;font-size:13px;color:#222'>" . $date . "</div>";

    // recipient block (left)
    $html .= "<div style='text-align:left;margin-bottom:12px;font-size:13px'>";
    $html .= "<div style='font-weight:700;text-transform:uppercase;margin-bottom:4px'>" . ($office_head ? $office_head : 'MR. [OFFICE HEAD]') . "</div>";
    $html .= "<div style='margin-bottom:2px'>" . ($office ? $office : 'Office of the City Mayor') . "</div>";
    $html .= "<div style='margin-bottom:10px'>City of Malolos</div>";
    $html .= "</div>";

    // greeting
    $html .= "<div style='margin-bottom:12px;font-size:13px'>Sir:</div>";

    // body paragraph (left-justified, narrow column)
    $html .= "<div style='max-width:620px;font-size:13px;line-height:1.6;text-align:justify;margin-bottom:18px'>";
    $html .= "The state is committed to enriching and developing young individuals to be productive citizens through education and social welfare programs. Immersion in the working environment provides the students with the essential experience of being part of an organization outside of the school platform through internship program. Whereas the City Government upholds the values of capacitating its citizenry and thus endorses to your good office, the student:";
    $html .= "</div>";

    // centered student block
    $html .= "<div style='text-align:center;margin-bottom:18px'>";
    $html .= "<div style='font-weight:800;font-size:15px;margin-bottom:6px'>" . $student . "</div>";
    $html .= "<div style='font-size:13px;margin-bottom:2px'>" . ($course ?: '') . "</div>";
    $html .= "<div style='font-size:13px;margin-bottom:2px'>" . ($college ?: '') . "</div>";
    $html .= "</div>";

    // follow-up paragraph and closing
    $html .= "<div style='max-width:620px;font-size:13px;line-height:1.6;text-align:justify;margin-bottom:18px'>We kindly request the guidance of your office and supervisor to assist in meeting the above students' training requirement. Thank you very much.</div>";

    $html .= "<div style='margin-top:12px;font-size:13px;margin-bottom:24px'>Respectfully yours,<br><br></div>";

    // signature block (left aligned)
    $html .= "<div style='max-width:400px;text-align:left;margin-bottom:8px'>";
    $html .= "<div style='height:64px'></div>"; // space for signature
    $html .= "<div style='font-weight:800;font-size:14px'>" . ($hr_head ? $hr_head : '[Name of HR Head]') . "</div>";
    $html .= "<div style='font-size:12px'>City Human Resource Management Officer</div>";
    $html .= "</div>";

    // footer - pinned near page bottom and formalized
    $html .= "<div style='position:fixed;left:0;right:0;bottom:30px;text-align:center;font-family:Arial, Helvetica, sans-serif'>";
    $html .= "<div style='margin:0 auto;width:90%;border-top:1px solid #ddd;padding-top:10px'></div>";
    $html .= "<div style='color:#8B2E2E;font-weight:600;font-size:12px;letter-spacing:0.6px;margin-top:8px'>DAKILA ANG BAYAN NA MAY MALASAKIT SA MAMAMAYAN</div>";
    $html .= "<div style='font-size:11px;color:#555;margin-top:4px'>3F City Government Building, Mac Arthur Highway, Barangay Bulihan, City of Malolos 3000</div>";
    $html .= "</div>";

    $html .= "</div>";
    return $html;
}

// main logic
$appId = isset($_GET['application_id']) ? (int)$_GET['application_id'] : null;
$studId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;
$all = isset($_GET['all']) ? true : false;

$hr_head_name = fetch_hr_head($conn);

echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>Endorsement Letter</title></head><body>";

if ($sessionId && $all) {
    $assigns = fetch_assignments_for_session($conn, $sessionId);
    if (empty($assigns)) {
        echo "<p>No students assigned for this session.</p>";
    } else {
        foreach ($assigns as $a) {
            $student_fullname = trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''));
            $data = [
                'student_fullname' => $student_fullname ?: ('App #' . ($a['application_id'] ?? '')),
                'course' => $a['course'] ?? '',
                'college' => $a['college'] ?? '',
                'total_hours_required' => $a['total_hours_required'] ?? '',
                'office_name' => '',
                'office_head_name' => '' ,
                'hr_head_name' => $hr_head_name
            ];
            // try to populate office info
            if (!empty($a['office_preference1'])) {
                list($office_name, $office_head) = fetch_office_head_by_officeid($conn, $a['office_preference1']);
                $data['office_name'] = $office_name;
                $data['office_head_name'] = $office_head;
            }
            echo render_letter($data);
        }
    }
} else {
    if (!$appId && $studId) {
        $appId = fetch_latest_application_by_student($conn, $studId);
    }
    if ($appId) {
        $info = fetch_application_info($conn, $appId);
        if (!$info) { echo "<p>Application not found.</p>"; }
        else {
            $student_fullname = trim(($info['first_name'] ?? '') . ' ' . ($info['last_name'] ?? ''));
            $office_name = '';$office_head='';
            if (!empty($info['office_preference1'])) {
                list($office_name, $office_head) = fetch_office_head_by_officeid($conn, $info['office_preference1']);
            }
            $data = [
                'student_fullname' => $student_fullname ?: ('App #' . $info['application_id']),
                'course' => $info['course'] ?? '',
                'college' => $info['college'] ?? '',
                'total_hours_required' => $info['total_hours_required'] ?? '',
                'office_name' => $office_name,
                'office_head_name' => $office_head,
                'hr_head_name' => $hr_head_name
            ];
            echo render_letter($data);
        }
    } else {
        echo "<p>No application or student specified.</p>";
    }
}

// add auto-print and close behavior
echo "<script>
    window.addEventListener('load', function(){
        try { window.print(); } catch(e){}
    });
    // attempt to close after print dialog; browsers may block window.close() for non-opened windows
    window.addEventListener('afterprint', function(){ try{ window.close(); } catch(e){} });
</script></body></html>";

?>