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

    $html = "<div style='page-break-after:always;padding:40px;font-family:Arial, Helvetica, sans-serif;color:#111'>";
    $html .= "<div style='text-align:right;margin-bottom:20px'>" . $date . "</div>";
    $html .= "<div style='margin-bottom:16px'>The Office Head<br><strong>$office</strong><br>City Government of Malolos</div>";
    $html .= "<div style='margin-bottom:12px'>Dear " . ($office_head ? $office_head : '(Name of Office head)') . ",</div>";
    $html .= "<div style='margin-bottom:12px'>Good day.</div>";
    $html .= "<div style='margin-bottom:12px'>This is to formally endorse <strong>" . $student . "</strong>, a <strong>" . $course . "</strong> student from <strong>" . $college . "</strong>, who is required to complete <strong>" . $hours . " hours</strong> of On-the-Job Training as part of the academic requirements of his/her program.</div>";
    $html .= "<div style='margin-bottom:12px'>In line with this, we respectfully request your good office to accommodate the said student for internship training. Your office has been identified as a suitable venue where the student may gain relevant knowledge, skills, and practical experience aligned with his/her field of study.</div>";
    $html .= "<div style='margin-bottom:12px'>We are confident that this training opportunity will greatly contribute to the student’s professional development.</div>";
    $html .= "<div style='margin-bottom:12px'>Thank you for your usual support and cooperation.</div>";
    $html .= "<div style='margin-top:40px'>Respectfully yours,<br><br><strong>" . ($hr_head ? $hr_head : '[Name of HR Head]') . "</strong><br>City Human Resource Management Officer<br>Office of the City Human Resource Management Office<br>City Government of Malolos</div>";
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