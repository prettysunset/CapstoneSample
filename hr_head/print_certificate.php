<?php
session_start();
require_once __DIR__ . '/../conn.php';

$eval_id = isset($_GET['eval_id']) ? (int)$_GET['eval_id'] : 0;
if ($eval_id <= 0) {
    echo "Invalid evaluation id.";
    exit;
}

// fetch evaluation + student + evaluator
$stmt = $conn->prepare("SELECT e.eval_id, e.rating, e.school_eval, e.feedback, e.date_evaluated, e.cert_serial,
  s.student_id, s.first_name AS s_first, s.last_name AS s_last, s.college AS s_college, s.course AS s_course, s.total_hours_required, s.user_id AS s_user_id,
  u.first_name AS eval_first, u.last_name AS eval_last
  FROM evaluations e
    LEFT JOIN students s ON e.student_id = s.student_id
    LEFT JOIN users u ON e.user_id = u.user_id
    WHERE e.eval_id = ? LIMIT 1");
$stmt->bind_param('i', $eval_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo "Evaluation not found.";
    exit;
}

$student_name = trim(($row['s_first'] ?? '') . ' ' . ($row['s_last'] ?? '')) ?: 'N/A';
$rating = $row['rating'] ?? null;
$feedback = $row['feedback'] ?? '';
$date_evaluated = $row['date_evaluated'] ?? null;
$cert_serial = trim((string)($row['cert_serial'] ?? ''));

// determine hours rendered (sum from dtr) if user_id present
$hours_rendered = 0;
if (!empty($row['s_user_id'])) {
    $stmt2 = $conn->prepare("SELECT IFNULL(SUM(hours + minutes/60),0) AS total FROM dtr WHERE student_id = ?");
    $stmt2->bind_param('i', $row['s_user_id']);
    if ($stmt2->execute()) {
        $r2 = $stmt2->get_result()->fetch_assoc();
        $hours_rendered = isset($r2['total']) ? (float)$r2['total'] : 0.0;
    }
    $stmt2->close();
}

// determine DTR period (earliest and latest log_date) if present
$period_from = null; $period_to = null;
if (!empty($row['s_user_id'])) {
  $qf = $conn->prepare("SELECT log_date FROM dtr WHERE student_id = ? AND COALESCE(log_date,'') <> '' ORDER BY log_date ASC LIMIT 1");
  $ql = $conn->prepare("SELECT log_date FROM dtr WHERE student_id = ? AND COALESCE(log_date,'') <> '' ORDER BY log_date DESC LIMIT 1");
  if ($qf && $ql) {
    $sid = (int)$row['s_user_id'];
    $qf->bind_param('i', $sid); $qf->execute(); $r1 = $qf->get_result()->fetch_assoc(); if ($r1) $period_from = $r1['log_date']; $qf->close();
    $ql->bind_param('i', $sid); $ql->execute(); $r2 = $ql->get_result()->fetch_assoc(); if ($r2) $period_to = $r2['log_date']; $ql->close();
  }
}

// fetch student's office (users.office_name) if available
$office_name = '';
if (!empty($row['s_user_id'])) {
  $so = $conn->prepare("SELECT office_name FROM users WHERE user_id = ? LIMIT 1");
  if ($so) { $so->bind_param('i', $row['s_user_id']); $so->execute(); $ro = $so->get_result()->fetch_assoc(); if ($ro) $office_name = $ro['office_name'] ?? ''; $so->close(); }
}

// try to find internship period columns on students table (multiple possible column names)
// Only run fallback if DTR did not provide period_from/period_to
if (empty($period_from) || empty($period_to)) {
  $pairs = [ ['start_date','end_date'], ['period_from','period_to'], ['date_from','date_to'] ];
  foreach ($pairs as $p) {
    $c1 = $p[0]; $c2 = $p[1];
    $check1 = $conn->query("SHOW COLUMNS FROM students LIKE '" . $conn->real_escape_string($c1) . "'");
    $check2 = $conn->query("SHOW COLUMNS FROM students LIKE '" . $conn->real_escape_string($c2) . "'");
    if ($check1 && $check1->num_rows > 0 && $check2 && $check2->num_rows > 0) {
      // re-fetch student row to get those fields
      $sid = (int)$row['student_id'];
      $stmt3 = $conn->prepare("SELECT `$c1` AS cfrom, `$c2` AS cto FROM students WHERE student_id = ? LIMIT 1");
      $stmt3->bind_param('i', $sid);
      $stmt3->execute();
      $rr = $stmt3->get_result()->fetch_assoc();
      $period_from = $rr['cfrom'] ?? null;
      $period_to = $rr['cto'] ?? null;
      $stmt3->close();
      break;
    }
  }
}

function fmtDateNice($d) {
    if (!$d) return '';
    $dt = date_create($d);
    return $dt ? $dt->format('F j, Y') : '';
}

function ordinal($n){ $n=(int)$n; $s = ['th','st','nd','rd','th','th','th','th','th','th']; if (($n%100)>=11 && ($n%100)<=13) return $n.'th'; return $n.$s[$n%10]; }

$issued_date = date('Y-m-d');
$issued_label = '';
if ($issued_date) {
    $dt = date_create($issued_date);
    if ($dt) {
        $issued_label = sprintf("Issued this %s day of %s, %s", ordinal($dt->format('j')), $dt->format('F'), $dt->format('Y'));
    }
}

$period_label = '';
if ($period_from && $period_to) {
  $period_label = fmtDateNice($period_from) . ' to ' . fmtDateNice($period_to);
} elseif ($period_from && !$period_to) {
  $period_label = fmtDateNice($period_from) . ' to ' . fmtDateNice($period_from);
}

$hours_label = (int)round($hours_rendered);
$required_hours = isset($row['total_hours_required']) ? (int)$row['total_hours_required'] : $hours_label;
$school_eval_val = isset($row['school_eval']) ? $row['school_eval'] : null;

// signer: prefer current session user, else fallback to a generic title
$signer_name = '';
if (!empty($_SESSION['user_id'])) {
    $suid = (int)$_SESSION['user_id'];
    $st = $conn->prepare("SELECT first_name, middle_name, last_name FROM users WHERE user_id = ? LIMIT 1");
    $st->bind_param('i', $suid);
    $st->execute();
    $sr = $st->get_result()->fetch_assoc();
    if ($sr) $signer_name = trim(($sr['first_name'] ?? '') . ' ' . ($sr['middle_name'] ?? '') . ' ' . ($sr['last_name'] ?? ''));
    $st->close();
}
if (!$signer_name) $signer_name = 'City Human Resource Management Officer';

$logo_web_path = '';
if (is_file(__DIR__ . '/logo_certt.jpg')) {
  $logo_web_path = 'logo_certt.jpg';
} elseif (is_file(__DIR__ . '/logo_certt.jpeg')) {
  $logo_web_path = 'logo_certt.jpeg';
} elseif (is_file(__DIR__ . '/logo_certt.png')) {
  $logo_web_path = 'logo_certt.png';
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Certificate of Completion - <?= htmlspecialchars($student_name) ?></title>
  <style>
    @page { size: A4; margin: 1in 1in .5in 1in; }
    html,body{height:100%;margin:0;background:#fff}
    body{padding:0;box-sizing:border-box;font-family:Arial, Helvetica, sans-serif;color:#111}
    .cert{width:100%;padding:0;box-sizing:border-box;border:0;background:#fff;height:calc(297mm - 1.5in);min-height:calc(297mm - 1.5in);display:flex;flex-direction:column;margin:0 auto;position:relative}
    .serial-top-right{position:absolute;top:1cm;right:1cm;font-size:12px;font-weight:700;color:#222;letter-spacing:.4px}
    .seal{display:block;text-align:center;margin-bottom:6px}
    .org{display:block;text-align:center;color:#d34e4e;font-weight:700;margin-bottom:6px}
    .header{margin-bottom:20px;text-align:center;padding-top:.5in}
    .seal img{width:56px;height:56px;object-fit:contain;display:block;margin:0 auto}
    .govline{display:block;width:100%;max-width:none;font-size:12px;line-height:1.2;text-align:center;margin:0;color:#222}
    .org{font-size:21px;line-height:1.15;margin:8px 0 4px;text-shadow:.3px .3px 0 #ab3d3d;font-weight:700}
    .rule{border:0;border-top:1px solid #777;width:calc(100% - 2in);margin:8px 1in 0}
    h1{font-size:22px;text-align:center;letter-spacing:1px;margin:22px 0 22px;font-weight:800}
    p{font-size:12px;line-height:1.35;text-align:center;margin:0 auto 18px;max-width:88%}
    .center{text-align:center}
    .big{font-weight:700}
    .mainpara{font-size:18px;padding-left:1in;padding-right:1in;box-sizing:border-box;max-width:none;width:100%;text-align:justify;text-indent:1in}
    .signature{margin-top:36px;text-align:center}
    .sigline{display:block;margin-top:10px;width:52%;margin-left:auto;margin-right:auto;padding-top:6px;font-size:18px;font-weight:700;text-transform:uppercase}
    .sigtitle{font-size:18px}
    .footer{margin-top:auto;text-align:center;padding-top:24px;padding-bottom:0;padding-left:.5in;padding-right:.5in;position:relative;top:80px}
    .footer .rule{width:calc(100% - 1in);margin:8px .5in 0}
    .motto{color:#a24545;font-size:13px;font-weight:700;margin:10px 0 2px}
    .addr{color:#222;font-size:10px;margin:0;max-width:none}
    @media screen {
      body{padding:1in 1in .5in 1in}
      .cert{max-width:calc(210mm - 2in)}
    }
    @media print { body{padding:0} .cert{box-shadow:none} }
  </style>
</head>
<body>
  <div class="cert" role="document">
    <?php if ($cert_serial !== ''): ?>
      <div class="serial-top-right">Serial No.: <?= htmlspecialchars($cert_serial) ?></div>
    <?php endif; ?>
    <div class="header">
      <div class="seal">
        <?php if ($logo_web_path): ?>
          <img src="<?= htmlspecialchars($logo_web_path) ?>" alt="City of Malolos Seal">
        <?php endif; ?>
      </div>
      <p class="govline">Republic of the Philippines</p>
      <p class="govline">Province of Bulacan</p>
      <p class="govline">City of Malolos</p>
      <div class="org">Office of the City Human Resource Management Officer</div>
      <hr class="rule">
    </div> 
    <h1>CERTIFICATE OF COMPLETION</h1>

        <p class="mainpara">This is to certify that <span class="big"><?= htmlspecialchars(strtoupper($student_name)) ?></span> has completed the <strong><?= htmlspecialchars($required_hours) ?></strong> hours of internship in the City Government of Malolos under the <strong><?= htmlspecialchars($office_name ?: 'the appropriate office') ?></strong>, covering the Period of <strong><?= htmlspecialchars($period_label ?: 'N/A') ?></strong>.</p>

        <?php if ($school_eval_val !== null && $school_eval_val !== ''): ?>
          <p class="mainpara">Based on the performance evaluation, Mr./Ms. <?= htmlspecialchars($row['s_last'] ?? '') ?> got a total rating of <strong><?= htmlspecialchars(number_format((float)$school_eval_val, 2, '.', '')) ?></strong>, which is highly commendable and evidence of excellent performance.</p>
        <?php elseif ($rating !== null): ?>
          <p class="mainpara">Based on the performance evaluation, Mr./Ms. <?= htmlspecialchars($row['s_last'] ?? '') ?> got a total rating of <strong><?= htmlspecialchars($rating) ?></strong>.</p>
        <?php endif; ?>

        <p class="mainpara" style="margin-top:20px"><?= htmlspecialchars($issued_label) ?> at the Office of the City Human Resource Management Office, 3rd Floor City Hall Building, Mac Arthur Highway, Bulacan, City of Malolos, Bulacan.</p>

    <div class="signature">
      <div class="sigline"><?= htmlspecialchars($signer_name) ?></div>
      <div class="sigtitle" style="margin-top:6px;font-weight:200">City Human Resource Management Officer</div>
    </div>

    <div class="footer">
      <hr class="rule">
      <div class="motto">"DAKILA ANG BAYAN NA MAY MALASAKIT SA MAMAMAYAN"</div>
      <p class="addr">3F City Government Building, Mac Arthur Highway, Barangay Bulihan, City of Malolos 3000</p>
    </div>
  </div>
  <script>
    // auto-print when opened in new tab
    window.onload = function(){ window.print(); };
  </script>
</body>
</html>