<?php
// Reusable sidebar that matches the home layout.
// Safe: starts session if needed and loads DB conn if missing.
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($conn)) {
    $connPath = __DIR__ . '/../conn.php';
    if (is_file($connPath)) require_once $connPath;
}

// resolve user id
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// prefer values saved in session
$display_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
$office_name_from_user = $_SESSION['office_name'] ?? '';

// fallback to DB if needed
if ($user_id && empty($display_name)) {
    $st = $conn->prepare("SELECT first_name, last_name, office_name FROM users WHERE user_id = ? LIMIT 1");
    $st->bind_param('i', $user_id);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();
    if ($u) {
        if (empty($display_name)) $display_name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        if (empty($office_name_from_user) && !empty($u['office_name'])) $office_name_from_user = $u['office_name'];
    }
}

// final fallbacks
if ($display_name === '') $display_name = 'Office Head';

// find office record (prefer office_heads -> offices, else try offices by users.office_name)
$office = null;
if ($user_id && isset($conn)) {
    $tblCheck = $conn->query("SHOW TABLES LIKE 'office_heads'");
    if ($tblCheck && $tblCheck->num_rows > 0) {
        $s = $conn->prepare("
            SELECT o.*
            FROM office_heads oh
            JOIN offices o ON oh.office_id = o.office_id
            WHERE oh.user_id = ?
            LIMIT 1
        ");
        $s->bind_param("i", $user_id);
        $s->execute();
        $office = $s->get_result()->fetch_assoc() ?: null;
        $s->close();
    }
    if (!$office && !empty($office_name_from_user)) {
        $q = $conn->prepare("SELECT * FROM offices WHERE office_name LIKE ? LIMIT 1");
        $like = '%' . $office_name_from_user . '%';
        $q->bind_param("s", $like);
        $q->execute();
        $office = $q->get_result()->fetch_assoc() ?: null;
        $q->close();
    }
}
if (!$office) $office = ['office_id' => 0, 'office_name' => 'Unknown Office'];

// display strings
$office_display = preg_replace('/\s+Office\s*$/i', '', trim($office['office_name'] ?? 'Unknown Office'));
$display_role = (stripos($display_name, 'office') === false) ? 'Office Head' : ucwords(str_replace('_',' ', $display_name));

// initials
$initials = '';
foreach (explode(' ', trim($display_name)) as $p) if ($p !== '') $initials .= strtoupper($p[0]);
$initials = htmlspecialchars(substr($initials ?: 'OH', 0, 2), ENT_QUOTES, 'UTF-8');

// links per folder
$scriptDir = basename(dirname($_SERVER['SCRIPT_FILENAME']));
if ($scriptDir === 'ojts') {
    $links = [
      ['href'=>'ojt_home.php','label'=>'Home'],
      ['href'=>'ojt_profile.php','label'=>'Profile'],
      ['href'=>'ojt_dtr.php','label'=>'DTR'],
      ['href'=>'ojt_reports.php','label'=>'Reports'],
    ];
} elseif ($scriptDir === 'office_head') {
    $links = [
      ['href'=>'office_head_home.php','label'=>'Home'],
      ['href'=>'office_head_ojts.php','label'=>'OJT'],
      ['href'=>'office_head_dtr.php','label'=>'DTR'],
      ['href'=>'office_head_reports.php','label'=>'Reports'],
    ];
} else {
    $links = [
      ['href'=>'ojts/ojt_home.php','label'=>'Home'],
      ['href'=>'ojts/ojt_profile.php','label'=>'Profile'],
      ['href'=>'ojts/ojt_dtr.php','label'=>'DTR'],
      ['href'=>'ojts/ojt_reports.php','label'=>'Reports'],
    ];
}

// helper to mark active
$current = basename($_SERVER['SCRIPT_NAME']);
?>
<div class="sidebar">
  <div style="height:100%; display:flex; flex-direction:column; justify-content:space-between;">
    <div>
      <div style="text-align:center; padding: 8px 12px 20px;">
        <div style="width:76px;height:76px;margin:0 auto 8px;border-radius:50%;background:#ffffff22;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:24px;overflow:hidden;">
          <?php echo $initials; ?>
        </div>
        <h3 style="color:#fff;font-size:16px;margin-bottom:4px;"><?php echo htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8'); ?></h3>
        <p style="color:#d6d9ee;font-size:13px;margin-top:0;"><?php echo htmlspecialchars($display_role . ' — ' . $office_display, ENT_QUOTES, 'UTF-8'); ?></p>
      </div>

      <nav style="padding: 6px 10px 12px;">
        <?php foreach ($links as $ln):
            $isActive = ($current === basename($ln['href']));
            $aStyle = $isActive ? 'display:block;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#2f3459;background:#fff;' 
                                : 'display:block;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;';
        ?>
          <a href="<?php echo htmlspecialchars($ln['href'], ENT_QUOTES, 'UTF-8'); ?>" style="<?php echo $aStyle; ?>">
            <?php echo htmlspecialchars($ln['label'], ENT_QUOTES, 'UTF-8'); ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div style="padding:14px 12px 26px;"></div>
  </div>
  <div style="padding:14px;color:#fff;font-weight:700;text-align:center;">OJT-MS</div>
</div>