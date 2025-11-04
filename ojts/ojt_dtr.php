<?php
session_start();
require_once __DIR__ . '/../conn.php';
date_default_timezone_set('Asia/Manila');

// require login (optional — uncomment if you use session user guard)
// if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit(); }

$user_id = $_SESSION['user_id'] ?? null;
$student_id = null;
$name = 'User Name';
$office_display = '';

if ($user_id) {
    $su = $conn->prepare("SELECT user_id, first_name, last_name, office_name FROM users WHERE user_id = ? LIMIT 1");
    $su->bind_param("i", $user_id);
    $su->execute();
    $ur = $su->get_result()->fetch_assoc();
    $su->close();
    if ($ur) {
        $name = trim(($ur['first_name'] ?? '') . ' ' . ($ur['last'] ?? $ur['last_name'] ?? '')) ?: $name;
        if (!empty($ur['office_name'])) $office_display = preg_replace('/\s+Office\s*$/i', '', trim($ur['office_name']));
    }
    $s = $conn->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
    $s->bind_param("i", $user_id);
    $s->execute();
    $sr = $s->get_result()->fetch_assoc();
    $s->close();
    if ($sr) $student_id = (int)$sr['student_id'];
}
$role = $office_display ? "OJT - " . $office_display : "OJT";

// fetch daily logs (last 10) for panel-daily
$daily_rows = [];
if ($student_id) {
    $q = $conn->prepare("SELECT log_date, am_in, am_out, pm_in, pm_out, hours FROM dtr WHERE student_id = ? ORDER BY log_date DESC LIMIT 10");
    $q->bind_param('i', $student_id);
    $q->execute();
    $daily_rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    $q->close();
}

// fetch late submissions (prefer late_dtr, join dtr for actual times)
$late_rows = [];
$late_note = '';
try {
    if (!$student_id) {
        $late_note = 'Student not resolved for current user.';
    } else {
        $sql = "SELECT ld.late_id, ld.date_filed, ld.late_date, ld.reason, ld.attachment, ld.status,
                       d.am_in, d.am_out, d.pm_in, d.pm_out, d.hours
                FROM late_dtr ld
                LEFT JOIN dtr d ON ld.student_id = d.student_id AND ld.late_date = d.log_date
                WHERE ld.student_id = ?
                ORDER BY ld.date_filed DESC
                LIMIT 100";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $late_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Exception $e) {
    $late_rows = [];
    $late_note = 'Unable to load late submissions.';
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>OJT DTR</title>
  <link rel="stylesheet" href="stylesforojt.css">
  <style>
    .content-wrap { position:fixed; left:260px; top:0; right:0; bottom:0; padding:32px; background:#f6f7fb; overflow:auto; }
    .card { background:#fff;padding:20px;border-radius:12px;box-shadow:0 6px 20px rgba(47,52,89,0.04); max-width:1100px; margin:0 auto; }
    table.dtr { width:100%; border-collapse:collapse; margin-top:12px; }
    table.dtr th, table.dtr td { padding:10px; text-align:center; border-bottom:1px solid #eef1f6; font-size:13px; }
    table.dtr thead th { background:#f5f7fb; color:#2f3459; }
    .date-pill { background:#f0f0f0; padding:6px 8px; border-radius:16px; display:inline-block; color:#2f3459; font-size:13px; }
    .top-actions { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
    .tabs { display:flex; gap:8px; align-items:center; margin-bottom:12px; }
    .tab-btn { padding:8px 14px; border-radius:10px; border:1px solid #e6e9f2; background:transparent; cursor:pointer; color:#2f3459; font-weight:600; }
    .tab-btn.active { background:#fff; box-shadow:0 4px 10px rgba(0,0,0,0.04); color:#2f3459; }
    .tab-panel { display:none; }
    .tab-panel.active { display:block; }
    .late-note { color:#8a8f9d; font-size:13px; margin-top:8px; }
    .late-table th, .late-table td { padding:8px; border-bottom:1px solid #eef1f6; font-size:13px; text-align:center; }
    .late-table thead th { background:#fff4f4; color:#a00; }

    /* Late DTR modal styles */
    .late-modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15,15,20,0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 2200;
    }
    .late-modal {
      width: 340px;
      max-width: 92%;
      background: #fff;
      border-radius: 18px;
      padding: 18px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.2);
      font-family: inherit;
    }
    .late-modal h4 { margin:0 0 10px; font-size:16px; color:#2f3459; }
    .late-modal .row { margin-bottom:10px; }
    .late-modal label { display:block; font-size:13px; color:#333; margin-bottom:6px; }
    .late-modal input[type="date"], .late-modal input[type="time"], .late-modal textarea, .late-modal input[type="file"] {
      width:100%; padding:8px 10px; border-radius:8px; border:1px solid #e6e9f2; box-sizing:border-box; font-size:13px;
    }
    .late-modal textarea { min-height:72px; resize:vertical; }
    .late-modal .actions { display:flex; gap:8px; justify-content:flex-end; margin-top:6px; }
    .late-modal .btn { padding:8px 12px; border-radius:14px; border:0; cursor:pointer; font-weight:600; }
    .late-modal .btn.cancel { background:#f2f2f4; color:#333; }
    .late-modal .btn.upload { background:#4f4aa6; color:#fff; }
    .late-modal .error { color:#a00; font-size:13px; margin-top:6px; display:none; }

    /* Late DTR create modal */
    #lateModalOverlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 15, 20, 0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 2200;
    }
    .late-modal[role="dialog"] {
      width: 400px;
      max-width: 90%;
      background: #fff;
      border-radius: 18px;
      padding: 18px;
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
      font-family: inherit;
    }
    .late-modal[role="dialog"] h4 {
      margin: 0 0 10px;
      font-size: 16px;
      color: #2f3459;
    }
    .late-modal[role="dialog"] .row {
      margin-bottom: 10px;
    }
    .late-modal[role="dialog"] label {
      display: block;
      font-size: 13px;
      color: #333;
      margin-bottom: 6px;
    }
    .late-modal[role="dialog"] input[type="date"],
    .late-modal[role="dialog"] input[type="time"],
    .late-modal[role="dialog"] textarea,
    .late-modal[role="dialog"] input[type="file"] {
      width: 100%;
      padding: 8px 10px;
      border-radius: 8px;
      border: 1px solid #e6e9f2;
      box-sizing: border-box;
      font-size: 13px;
    }
    .late-modal[role="dialog"] textarea {
      min-height: 72px;
      resize: vertical;
    }
    .late-modal[role="dialog"] .actions {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      margin-top: 6px;
    }
    .late-modal[role="dialog"] .btn {
      padding: 8px 12px;
      border-radius: 14px;
      border: 0;
      cursor: pointer;
      font-weight: 600;
    }
    .late-modal[role="dialog"] .btn.cancel {
      background: #f2f2f4;
      color: #333;
    }
    .late-modal[role="dialog"] .btn.upload {
      background: #4f4aa6;
      color: #fff;
    }
    .late-modal[role="dialog"] .error {
      color: #a00;
      font-size: 13px;
      margin-top: 6px;
      display: none;
    }
  </style>
</head>
<body>
  <!-- top icons -->
  <div id="top-icons" style="position:fixed;top:18px;right:28px;display:flex;gap:14px;z-index:1200;">
      <a id="top-notif" href="notifications.php" title="Notifications" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
          <!-- svg -->
      </a>
      <a id="top-settings" href="settings.php" title="Settings" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
          <!-- svg -->
      </a>
      <a id="top-logout" href="../logout.php" title="Logout" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:8px;color:#2f3459;text-decoration:none;">
          <!-- svg -->
      </a>
  </div>

  <div class="sidebar">
    <!-- sidebar markup (updated to mirror ojt_home / ojt_profile) -->
    <div style="height:100%; display:flex; flex-direction:column; justify-content:space-between;">
      <div>
        <div style="text-align:center; padding: 8px 12px 20px;">
          <div style="width:76px;height:76px;margin:0 auto 8px;border-radius:50%;background:#ffffff22;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:24px;overflow:hidden;">
            <?php
              // initials from $name (resolved earlier in this file)
              $initials = '';
              foreach (explode(' ', trim($name)) as $p) if ($p !== '') $initials .= strtoupper($p[0]);
              echo htmlspecialchars(substr($initials,0,2) ?: 'UN');
            ?>
          </div>
          <h3 style="color:#fff;font-size:16px;margin-bottom:4px;"><?php echo htmlspecialchars($name); ?></h3>
          <p style="color:#d6d9ee;font-size:13px;margin-top:0;"><?php echo htmlspecialchars($role); ?></p>
        </div>

        <nav style="padding: 6px 10px 12px;">
          <a href="ojt_home.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">Home</a>
          <a href="ojt_profile.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">Profile</a>
          <a href="ojt_dtr.php" class="active" aria-current="page" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#2f3459;background:#fff;">DTR</a>
          <a href="ojt_reports.php" style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin:8px 0;border-radius:12px;text-decoration:none;color:#fff;background:transparent;">Reports</a>
        </nav>
      </div>
      <div style="padding:14px 12px 26px;"></div>
    </div>
  </div>

  <div class="content-wrap">
    <div class="card">
      <div class="top-actions" style="flex-direction:column;align-items:stretch;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <div>
            <h2 style="margin:0;color:#2f3459;">Daily Logs</h2>
            <p style="margin:6px 0 0;color:#6b6f8b;">Review your daily time records</p>
          </div>
          <div style="display:flex;gap:12px;align-items:center;">
            <select id="monthSelect" style="padding:8px;border-radius:8px;border:1px solid #e6e9f2;background:#fff;">
              <?php for ($m=1;$m<=12;$m++): $label = date('F', mktime(0,0,0,$m,1)); ?>
                <option value="<?php echo $m; ?>" <?php if ($m== (int)date('n')) echo 'selected'; ?>><?php echo htmlspecialchars($label . ' ' . date('Y')); ?></option>
              <?php endfor; ?>
            </select>

            <!-- Create Late DTR button -->
            <button id="btnCreateLate" style="padding:10px 16px;border-radius:20px;border:0;background:#4f4aa6;color:#fff;font-weight:600;cursor:pointer;">
              Create Late DTR
            </button>
          </div>
        </div>

        <div class="tabs" role="tablist" aria-label="DTR sections">
          <button class="tab-btn active" data-target="panel-daily" role="tab" aria-selected="true">Daily Logs</button>
          <button class="tab-btn" data-target="panel-late" role="tab" aria-selected="false">Late DTR Submissions</button>
        </div>
      </div>

      <!-- Daily panel -->
      <div id="panel-daily" class="tab-panel active" role="tabpanel">
        <table class="dtr">
          <thead>
            <tr>
              <th>DATE</th>
              <th>A.M. Arrival</th>
              <th>A.M. Departure</th>
              <th>P.M. Arrival</th>
              <th>P.M. Departure</th>
              <th>HOURS</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($daily_rows)): ?>
              <?php foreach ($daily_rows as $r):
                $d = date('M j, Y', strtotime($r['log_date']));
                $am_in = $r['am_in'] ?: '-'; $am_out = $r['am_out'] ?: '-';
                $pm_in = $r['pm_in'] ?: '-'; $pm_out = $r['pm_out'] ?: '-';
                $hrs = is_numeric($r['hours']) ? (int)$r['hours'] : '-';
              ?>
                <tr>
                  <td><span class="date-pill"><?php echo htmlspecialchars($d); ?></span></td>
                  <td><?php echo htmlspecialchars($am_in); ?></td>
                  <td><?php echo htmlspecialchars($am_out); ?></td>
                  <td><?php echo htmlspecialchars($pm_in); ?></td>
                  <td><?php echo htmlspecialchars($pm_out); ?></td>
                  <td style="font-weight:700;color:#2f3459;"><?php echo htmlspecialchars($hrs); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center;color:#8a8f9d;padding:18px;">No records — please log in as a student.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Late panel: always render headers -->
      <div id="panel-late" class="tab-panel" role="tabpanel" aria-hidden="true">
        <h3 style="margin-top:6px;color:#2f3459;">Late DTR Submissions</h3>
        <?php if ($late_note): ?><div class="late-note"><?php echo htmlspecialchars($late_note); ?></div><?php endif; ?>

        <table class="late-table" style="width:100%;border-collapse:collapse;margin-top:12px;">
          <thead>
            <tr>
              <th>DATE FILED</th>
              <th>A.M. Arrival</th>
              <th>A.M. Departure</th>
              <th>P.M. Arrival</th>
              <th>P.M. Departure</th>
              <th>HOURS</th>
              <th>DATE</th>
              <th>STATUS</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($late_rows)): ?>
              <?php foreach ($late_rows as $r):
                $date_filed = !empty($r['date_filed']) ? date('M j, Y', strtotime($r['date_filed'])) : '-';
                $late_date = !empty($r['late_date']) ? date('M j, Y', strtotime($r['late_date'])) : '-';
                $am_in = $r['am_in'] ?: '-'; $am_out = $r['am_out'] ?: '-';
                $pm_in = $r['pm_in'] ?: '-'; $pm_out = $r['pm_out'] ?: '-';
                $hrs = is_numeric($r['hours']) ? (int)$r['hours'] : '-';
                $status = $r['status'] ?: '-';
              ?>
                <tr>
                  <td><span class="date-pill"><?php echo htmlspecialchars($date_filed); ?></span></td>
                  <td><?php echo htmlspecialchars($am_in); ?></td>
                  <td><?php echo htmlspecialchars($am_out); ?></td>
                  <td><?php echo htmlspecialchars($pm_in); ?></td>
                  <td><?php echo htmlspecialchars($pm_out); ?></td>
                  <td style="font-weight:700;color:#2f3459;"><?php echo htmlspecialchars($hrs); ?></td>
                  <td><?php echo htmlspecialchars($late_date); ?></td>
                  <td><?php echo htmlspecialchars(ucfirst($status)); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8" style="text-align:center;color:#8a8f9d;padding:18px;">No late submissions found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>

  <!-- Single Late DTR modal (no reason/attachment fields) -->
  <div id="lateModalOverlay" class="late-modal-overlay" aria-hidden="true" style="display:none;">
    <div class="late-modal" role="dialog" aria-labelledby="lateTitle" aria-modal="true">
      <h4 id="lateTitle">Late DTR</h4>

      <div class="row">
        <label for="late_date">Date</label>
        <input id="late_date" type="date" />
      </div>

      <div class="row">
        <label>AM Arrival / Departure</label>
        <input id="late_am_in" type="time" />
        <div style="height:8px"></div>
        <input id="late_am_out" type="time" />
      </div>

      <div class="row">
        <label>PM Arrival / Departure</label>
        <input id="late_pm_in" type="time" />
        <div style="height:8px"></div>
        <input id="late_pm_out" type="time" />
      </div>

      <div class="error" id="lateError" style="display:none;"></div>

      <div class="actions">
        <button class="btn cancel" id="lateCancel" type="button">Cancel</button>
        <button class="btn upload" id="lateUpload" type="button">Upload</button>
      </div>
    </div>
  </div>

  <script>
    // tab switching
    (function(){
      const tabs = document.querySelectorAll('.tab-btn');
      tabs.forEach(btn => btn.addEventListener('click', function(){
        const target = btn.getAttribute('data-target');
        if (!target) return;
        document.querySelectorAll('.tab-btn').forEach(b=>{ b.classList.remove('active'); b.setAttribute('aria-selected','false'); });
        btn.classList.add('active'); btn.setAttribute('aria-selected','true');
        document.querySelectorAll('.tab-panel').forEach(p=>{ p.classList.remove('active'); p.setAttribute('aria-hidden','true'); });
        const panel = document.getElementById(target);
        if (panel) { panel.classList.add('active'); panel.setAttribute('aria-hidden','false'); }
      }));
    })();

    // logout (replace history to avoid back -> protected page)
    (function(){
      var el = document.getElementById('top-logout');
      if (!el) return;
      el.addEventListener('click', function(e){
        e.preventDefault();
        if (confirm('Log out?')) {
          window.location.replace(el.getAttribute('href') || '../logout.php');
        }
      });
    })();

    // Late DTR modal
    (function(){
      var modal = document.getElementById('lateModal');
      var btnOpen = document.getElementById('btnCreateLate');
      var btnClose = document.getElementById('btnCancelLate');
      var btnSubmit = document.getElementById('btnUploadLate');
      var errorMsg = document.getElementById('lateError');

      // Open modal
      if (btnOpen) {
        btnOpen.addEventListener('click', function(){
          modal.style.display = 'flex';
          setTimeout(() => { modal.classList.add('show'); }, 10);
        });
      }

      // Close modal
      if (btnClose) {
        btnClose.addEventListener('click', function(){
          modal.classList.remove('show');
          setTimeout(() => { modal.style.display = 'none'; }, 300);
        });
      }

      // Submit late DTR
      if (btnSubmit) {
        btnSubmit.addEventListener('click', function(){
          var date = document.getElementById('lateDate').value;
          var amIn = document.getElementById('lateAmIn').value;
          var amOut = document.getElementById('lateAmOut').value;
          var pmIn = document.getElementById('latePmIn').value;
          var pmOut = document.getElementById('latePmOut').value;
          var reason = document.getElementById('lateReason').value;
          var attachment = document.getElementById('lateAttachment').files[0];

          // Validate
          if (!date || !reason) {
            errorMsg.textContent = 'Please fill in all required fields.';
            errorMsg.style.display = 'block';
            return;
          } else {
            errorMsg.style.display = 'none';
          }

          // Prepare form data
          var formData = new FormData();
          formData.append('action', 'upload_late_dtr');
          formData.append('student_id', '<?php echo $student_id; ?>');
          formData.append('date', date);
          formData.append('am_in', amIn);
          formData.append('am_out', amOut);
          formData.append('pm_in', pmIn);
          formData.append('pm_out', pmOut);
          formData.append('reason', reason);
          if (attachment) formData.append('attachment', attachment);

          // Upload
          fetch('ajax/late_dtr_upload.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              alert('Late DTR submitted successfully.');
              location.reload();
            } else {
              errorMsg.textContent = data.message || 'Upload failed. Please try again.';
              errorMsg.style.display = 'block';
            }
          })
          .catch(error => {
            errorMsg.textContent = 'Upload failed. Please check your network connection.';
            errorMsg.style.display = 'block';
          });
        });
      }
    })();

    // Late DTR create modal
    (function(){
      const btn = document.getElementById('btnCreateLate');
      const overlay = document.getElementById('lateModalOverlay');
      const cancel = document.getElementById('lateCancel');
      const upload = document.getElementById('lateUpload');
      const errEl = document.getElementById('lateError');

      function openModal(){
        errEl.style.display='none'; errEl.textContent='';
        overlay.style.display='flex'; overlay.setAttribute('aria-hidden','false');
        const d = document.getElementById('late_date');
        if (d && !d.value) d.value = new Date().toISOString().slice(0,10);
      }
      function closeModal(){
        overlay.style.display='none'; overlay.setAttribute('aria-hidden','true');
        ['late_date','late_am_in','late_am_out','late_pm_in','late_pm_out'].forEach(id=>{
          const el = document.getElementById(id);
          if(!el) return;
          el.value = '';
        });
      }

      if (btn) btn.addEventListener('click', openModal);
      if (cancel) cancel.addEventListener('click', function(e){ e.preventDefault(); closeModal(); });

      overlay.addEventListener('click', function(e){
        if (e.target === overlay) closeModal();
      });

      function showError(msg){
        errEl.style.display='block';
        errEl.textContent = msg;
      }

      upload.addEventListener('click', async function(e){
        e.preventDefault();
        errEl.style.display='none'; errEl.textContent='';

        const date = document.getElementById('late_date').value.trim();
        const am_in = document.getElementById('late_am_in').value.trim();
        const am_out = document.getElementById('late_am_out').value.trim();
        const pm_in = document.getElementById('late_pm_in').value.trim();
        const pm_out = document.getElementById('late_pm_out').value.trim();

        if (!date) return showError('Please select a date.');

        const amFilled = am_in !== '' || am_out !== '';
        const pmFilled = pm_in !== '' || pm_out !== '';
        const amComplete = am_in !== '' && am_out !== '';
        const pmComplete = pm_in !== '' && pm_out !== '';

        if (!amComplete && !pmComplete) {
          if (amFilled && (!am_in || !am_out)) return showError('If using AM fields, fill both Arrival and Departure.');
          if (pmFilled && (!pm_in || !pm_out)) return showError('If using PM fields, fill both Arrival and Departure.');
          return showError('Please provide both AM times or both PM times.');
        }

        // prepare FormData (server handler needed to accept this)
        const fd = new FormData();
        fd.append('action','create_late');
        fd.append('late_date', date);
        if (amComplete) { fd.append('am_in', am_in); fd.append('am_out', am_out); }
        if (pmComplete) { fd.append('pm_in', pm_in); fd.append('pm_out', pm_out); }
        fd.append('student_id', '<?php echo $student_id ?? ''; ?>');

        upload.disabled = true;
        upload.textContent = 'Uploading...';

        try {
          const res = await fetch('ojt_dtr_action.php', { method:'POST', body: fd });
          const json = await res.json();
          if (json && json.success) {
            closeModal();
            location.reload();
          } else {
            showError(json && json.message ? json.message : 'Upload failed.');
            upload.disabled = false;
            upload.textContent = 'Upload';
          }
        } catch (err) {
          console.error(err);
          showError('Request failed. Check console.');
          upload.disabled = false;
          upload.textContent = 'Upload';
        }
      });
    })();
  </script>
</body>
</html>