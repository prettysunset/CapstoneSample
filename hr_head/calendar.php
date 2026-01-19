<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Calendar</title>
  <?php
    // determine month/year from query params (defaults to current month/year)
    $m = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');
    $y = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
    if ($m < 1) { $m = 1; } elseif ($m > 12) { $m = 12; }
    $first = new DateTime();
    $first->setDate($y, $m, 1);
    $monthLabel = $first->format('F Y');
    $startWeekday = (int)$first->format('w'); // 0=Sun..6=Sat
    $daysInMonth = (int)$first->format('t');
    // previous month info (for leading day numbers)
    $prev = (clone $first)->modify('-1 month');
    $daysInPrev = (int)$prev->format('t');
    // build events map (day => label) from DB `orientation_sessions`
    $events = [];
    $eventsData = []; // structured data for JS (day => array of sessions)
    // try to load real orientation sessions from DB if available
    $connPath = __DIR__ . '/../conn.php';
    if (file_exists($connPath)) {
      @include_once $connPath;
      if (isset($conn) && $conn instanceof mysqli) {
        $cols = [];
        $resCols = $conn->query("SHOW COLUMNS FROM orientation_sessions");
        if ($resCols) {
          while ($r = $resCols->fetch_assoc()) { $cols[] = $r['Field']; }
          $dateCandidates = ['session_date','date','start','session_datetime','datetime','scheduled_at'];
          $timeCandidates = ['session_time','time','start_time'];
          $locCandidates = ['location','venue','place'];
          $dateCol = null; $timeCol = null; $locCol = null;
          foreach ($dateCandidates as $c) if (in_array($c,$cols)) { $dateCol = $c; break; }
          foreach ($timeCandidates as $c) if (in_array($c,$cols)) { $timeCol = $c; break; }
          foreach ($locCandidates as $c) if (in_array($c,$cols)) { $locCol = $c; break; }
          if ($dateCol) {
            $startDate = $first->format('Y-m-01');
            $endDate = $first->format('Y-m-t');
            $sql = "SELECT * FROM orientation_sessions WHERE DATE(`" . $dateCol . "`) BETWEEN ? AND ?";
            // prepare a count statement for assignments if table exists
            $countStmt = null;
            $resAssign = $conn->query("SHOW TABLES LIKE 'orientation_assignments'");
            $assignCols = [];
            $assignStmt = null;
            $nameCol = null;
            $idCol = null;
            if ($resAssign && $resAssign->num_rows > 0) {
              // detect assignment table columns to extract student names/ids
              $resAssignCols = $conn->query("SHOW COLUMNS FROM orientation_assignments");
              if ($resAssignCols) {
                while ($ac = $resAssignCols->fetch_assoc()) { $assignCols[] = $ac['Field']; }
                $nameCandidates = ['student_name','name','full_name','ojt_name','first_name','last_name'];
                $idCandidates = ['id','assignment_id','student_id','ojt_id'];
                foreach ($nameCandidates as $nc) if (in_array($nc,$assignCols)) { $nameCol = $nc; break; }
                foreach ($idCandidates as $ic) if (in_array($ic,$assignCols)) { $idCol = $ic; break; }
              }
              if (!$countStmt) {
                $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM orientation_assignments WHERE session_id = ?");
              }
              // select assignment rows and join to application -> students to obtain student names when available
              $assignStmt = $conn->prepare(
                'SELECT oa.*, app.application_id, app.student_id, s.first_name, s.last_name
                 FROM orientation_assignments oa
                 LEFT JOIN ojt_applications app ON oa.application_id = app.application_id
                 LEFT JOIN students s ON app.student_id = s.student_id
                 WHERE oa.session_id = ?'
              );
            }
            if ($stmt = $conn->prepare($sql)) {
              $stmt->bind_param('ss', $startDate, $endDate);
              $stmt->execute();
              $result = $stmt->get_result();
              while ($row = $result->fetch_assoc()) {
                $dt = $row[$dateCol];
                $ts = strtotime($dt);
                if ($ts === false) continue;
                $d = (int)date('j', $ts);
                // build HTML parts: time, location, count
                $timeHtml = '';
                $timeLabel = '';
                if ($timeCol && !empty($row[$timeCol])) {
                  $timeLabel = date('g:i A', strtotime($row[$timeCol]));
                  $timeHtml = '<div class="ev-time">' . htmlspecialchars($timeLabel) . '</div>';
                } else {
                  if (strpos($dt, ' ') !== false) { $timeLabel = date('g:i A', $ts); $timeHtml = '<div class="ev-time">' . htmlspecialchars($timeLabel) . '</div>'; }
                }
                $locLabel = ($locCol && !empty($row[$locCol])) ? $row[$locCol] : 'Location';
                $locHtml = '<div class="ev-loc">' . htmlspecialchars($locLabel) . '</div>';
                $countHtml = '';
                $students = [];
                if ($assignStmt && isset($row['session_id'])) {
                  $sid = $row['session_id'];
                  $assignStmt->bind_param('i', $sid);
                  $assignStmt->execute();
                  $ares = $assignStmt->get_result();
                  if ($ares) {
                    while ($ar = $ares->fetch_assoc()) {
                      $sname = trim((string)($ar['first_name'] ?? '') . ' ' . ($ar['last_name'] ?? ''));
                      $sid = $ar['student_id'] ?? ($ar['application_id'] ?? ($ar['id'] ?? null));
                      // fallback: if join didn't return a name but student_id exists, try to fetch student record
                      if (empty(trim($sname)) && !empty($sid) && is_numeric($sid)) {
                        $sres = $conn->prepare("SELECT first_name, last_name FROM students WHERE student_id = ?");
                        if ($sres) {
                          $sres->bind_param('i', $sid);
                          $sres->execute();
                          $sresr = $sres->get_result();
                          if ($sresr && $sr = $sresr->fetch_assoc()) {
                            $sname = trim((string)($sr['first_name'] ?? '') . ' ' . ($sr['last_name'] ?? ''));
                          }
                          $sres->close();
                        }
                      }
                      if (empty(trim($sname))) {
                        if (!empty($ar['application_id'])) $sname = 'App #' . $ar['application_id'];
                        else $sname = 'Assigned OJT';
                      }
                      $students[] = ['id' => $sid, 'name' => $sname];
                    }
                  }
                  $cnt = count($students);
                  if ($cnt > 0) {
                    $labelCnt = ($cnt === 1) ? '1 OJT' : ($cnt . ' OJTs');
                    $countHtml = '<div class="ev-count">' . htmlspecialchars($labelCnt) . '</div>';
                  }
                } elseif ($countStmt && isset($row['session_id'])) {
                  $sid = $row['session_id'];
                  $countStmt->bind_param('i', $sid);
                  $countStmt->execute();
                  $cres = $countStmt->get_result();
                  if ($cres && $crow = $cres->fetch_assoc()) {
                      $cnt = (int)$crow['cnt'];
                      if ($cnt > 0) {
                        $labelCnt = ($cnt === 1) ? '1 OJT' : ($cnt . ' OJTs');
                        $countHtml = '<div class="ev-count">' . htmlspecialchars($labelCnt) . '</div>';
                      }
                    }
                }
                $label = trim($timeHtml . $locHtml . $countHtml);
                if (isset($events[$d])) $events[$d] .= '<br>' . $label;
                else $events[$d] = $label;
                // store structured session data for JS
                $sess = [
                  'time' => $timeLabel,
                  'date' => $dt,
                  'location' => $locLabel,
                  'count' => isset($cnt) ? (int)$cnt : 0,
                  'students' => $students
                ];
                if (!isset($eventsData[$d])) $eventsData[$d] = [];
                $eventsData[$d][] = $sess;
              }
            }
            if ($countStmt) $countStmt->close();
          }
        }
      }
    }
    // navigation helpers
    $prevMonth = (clone $first)->modify('-1 month');
    $nextMonth = (clone $first)->modify('+1 month');
    $prevParams = '?m=' . (int)$prevMonth->format('n') . '&y=' . (int)$prevMonth->format('Y');
    $nextParams = '?m=' . (int)$nextMonth->format('n') . '&y=' . (int)$nextMonth->format('Y');
  ?>
  <style>
    *{box-sizing:border-box;font-family:Poppins,system-ui,Segoe UI,Arial,sans-serif}
    html,body{height:100%;margin:0;background:transparent;color:#111;overflow:hidden}
    .outer-panel{display:block;padding:0;margin:0;width:100%;height:100%}
    .outer-panel .panel{background:transparent;border-radius:0;padding:0;max-width:none;width:100%;box-shadow:none;height:100%;display:flex;align-items:center;justify-content:center}
    .card{background:#fff;border-radius:18px;padding:12px 12px;max-width:1180px;width:calc(100% - 64px);height:calc(100% - 64px);margin:0 auto;display:grid;grid-template-rows:auto 1fr;gap:8px;align-items:stretch;justify-content:stretch;box-shadow:0 18px 40px rgba(16,24,40,0.12)}
    .toolbar{display:grid;grid-template-rows:auto auto;row-gap:8px;align-items:center;margin-bottom:0}
    .toggles{display:flex;gap:8px}
    .toggle{padding:8px 12px;border-radius:12px;background:#f3f3ff;color:#5a3db0;font-weight:700;cursor:pointer;border:0}
    .toggles .muted{background:#fff;color:#667;border:1px solid #eee}
    .title{font-weight:800;color:#2f3850;text-align:center;width:100%}
    .content{display:grid;grid-template-columns:1fr 490px;gap:20px;align-items:stretch;height:100%}
    .calendar{padding:6px;border-radius:12px;background:transparent;display:flex;flex:1 1 auto;min-width:0}
    .inner-calendar{background:#fff;border-radius:12px;padding:12px;box-shadow:0 12px 30px rgba(16,24,40,0.08);max-width:none;width:100%;box-sizing:border-box;height:100%;display:flex;flex-direction:column}
    .monthLabel{font-weight:700;color:#2f3850;margin-bottom:8px}
    .inner-calendar{
      --upcoming-w:490px;
      --gap:0px;
      --inner-pad:12px;
      --avail-w: calc(100% - var(--upcoming-w) - var(--inner-pad));
      --avail-h: calc(100% - 120px);
      --cell-w: calc((var(--avail-w) - (6 * var(--gap))) / 7);
      --cell-h: calc((var(--avail-h) - (5 * var(--gap))) / 6);
      --cell-size: 85px;
    }
    .grid{display:grid;grid-template-columns:repeat(7,var(--cell-size));gap:0;justify-content:center;border-right:1px solid #f0f0f6;border-bottom:1px solid #f0f0f6}
    .dayHead{font-size:12px;color:#666;text-align:center;padding:6px 0;border-left:1px solid #f0f0f6;border-bottom:1px solid #f0f0f6}
    .grid.header{margin-bottom:0;grid-template-columns:repeat(7,var(--cell-size));}
    .cell{background:#fff;border-radius:0;border-left:1px solid #f0f0f6;border-top:1px solid #f0f0f6;padding:6px;position:relative;transition:all .12s ease;display:flex;flex-direction:column;box-sizing:border-box;width:var(--cell-size);height:var(--cell-size)}
    .cell .date{position:absolute;top:6px;right:8px;left:auto;font-size:11px;color:#9aa;transition:none;text-align:right;font-weight:700}
    .cell .content{margin-top:18px;flex:1;display:flex;align-items:flex-end;justify-content:flex-end;overflow:hidden;padding-right:0px;padding-left:12px}
    /* don't highlight day numbers purple for scheduled days; keep them muted gray */
    .cell.has-event .date{color:#9aa}
    /* remove hover animation on cells/dates */
    .cell.has-event:hover{box-shadow:none;transform:none}
    .cell.has-event:hover .date{color:#9aa}
    /* past events should look muted (no purple) */
    .cell.past-event .date{color:#9aa}
    .cell.past-event .event{background:#f5f5f7;color:#777}
    .cell.past-event:hover{box-shadow:none;transform:none}
    .cell.past-event:hover .date{color:#9aa}
    /* light purple container, strong purple text */
    .event{display:flex;flex-direction:column;align-items:flex-end;margin-left:auto;margin-top:6px;background:#efe6ff;color:#6a3db5;padding:6px 8px;border-radius:6px;font-size:11px;text-align:right}
    .event .ev-time, .event .ev-loc, .event .ev-count{font-size:10px;color:#6a3db5;text-align:right}
    .event .ev-count{font-weight:600}
    .controls{text-align:center;width:100%}
    .upcoming{background:#fff;border-radius:12px;padding:16px;border:1px solid #f3f0fb;height:100%;overflow:hidden}
    .upcoming h4{margin:0 0 10px 0;color:#3a2b6a}
    .upcoming .item{display:flex;align-items:flex-start;gap:8px;padding:8px 0;border-bottom:1px dashed #f2eef9}
    .upcoming .item:last-child{border-bottom:none}
    /* Orientation session layout: icon left, text right */
    #orientationContent .session{padding:8px 0;border-bottom:1px dashed #f2eef9}
    #orientationContent .session:last-child{border-bottom:none}
    #orientationContent .row{display:flex;align-items:center;gap:12px;padding:6px 0}
    /* icon: light purple circular background with purple outline-only SVG */
    #orientationContent .icon{width:36px;height:36px;border-radius:50%;background:#efe6ff;display:flex;align-items:center;justify-content:center;flex:0 0 36px;border:1px solid #6a3db5}
    #orientationContent .icon svg{width:18px;height:18px;fill:none;stroke:#6a3db5;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
    #orientationContent .text{font-size:14px;color:#111}
    /* date (small) should be black */
    #orientationContent .text.small{font-size:13px;color:#111}
    /* students list */
    #orientationContent .students{margin-top:6px}
    #orientationContent .student-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #f2eef9}
    #orientationContent .student-row .student-name{color:#111;font-size:14px}
    #orientationContent .print-icon{width:36px;height:36px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #6a3db5;background:transparent;color:#6a3db5;cursor:pointer}
    #orientationContent .print-icon svg{width:16px;height:16px;fill:none;stroke:#6a3db5;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
    #orientationContent .print-all{display:block;margin-top:8px;padding:8px 12px;border-radius:8px;border:1px solid #6a3db5;background:transparent;color:#6a3db5;text-align:center;text-decoration:none;width:100%}
    .dot{width:12px;height:12px;border-radius:50%;background:#efe6ff;flex:0 0 12px;margin-top:4px}
    .dot.purple{background:#efe6ff}
    /* today's day number: filled purple circle */
    .cell.today .date{background:#6a3db5;color:#fff;width:22px;height:22px;line-height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;text-align:center;padding:0}
    /* selected day: purple outline circle */
    .cell.selected .date{background:transparent;color:#6a3db5;border:2px solid #6a3db5;width:22px;height:22px;line-height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;padding:0}
    .upcoming .meta{color:#6d6d6d;font-size:13px}
    .resched{display:block;margin-top:12px;padding:8px 12px;border-radius:8px;background:#6a3db5;color:#fff;text-align:center;text-decoration:none;width:100%}
    @media (max-width:1200px){ .outer-panel .panel{width:100%;padding:18px} }
    @media (max-width:980px){ .content{grid-template-columns:1fr 260px} }
    @media (max-width:820px){
      .inner-calendar{--upcoming-w:200px;--inner-pad:40px}
      .content{grid-template-columns:1fr 200px}
    }
    @media (max-width:640px){
      .content{grid-template-columns:1fr}
      .inner-calendar{--upcoming-w:0;--inner-pad:32px}
      .grid{grid-template-columns:repeat(7, calc((100% - (6*var(--gap))) / 7));justify-content:stretch}
      .cell{width:auto;height:auto;aspect-ratio:1/1}
    }
    .outer-panel, .panel, .card { overflow: visible; }
    .inner-calendar { overflow: visible; }
    body { background: transparent; }
    .grid[aria-hidden="false"]{display:grid;grid-template-rows:repeat(6,var(--cell-size));align-content:start}
  </style>
</head>
<body>
  
  <div class="outer-panel">
    <div class="panel">
      <div class="card" role="region" aria-label="Calendar">
      <div class="toolbar">
        <!-- toolbar top left intentionally kept minimal; toggles placed in calendar header -->
            <!-- month label & nav moved inside the white calendar header (see below) -->

          <div class="content">
          <div class="calendar">
            <div class="inner-calendar">
            <div style="display:flex;justify-content:center;align-items:center;gap:12px;margin-bottom:8px">
              <a href="<?php echo $prevParams; ?>" class="toggle muted" style="background:#fff;border:1px solid #eee;padding:6px 10px;border-radius:8px;text-decoration:none;color:#2f3850" aria-label="Previous month">&laquo;</a>
              <div class="title" style="font-weight:700;color:#2f3850;text-align:center"><?php echo htmlspecialchars($monthLabel); ?></div>
              <a href="<?php echo $nextParams; ?>" class="toggle muted" style="background:#fff;border:1px solid #eee;padding:6px 10px;border-radius:8px;text-decoration:none;color:#2f3850" aria-label="Next month">&raquo;</a>
            </div>

            <div class="grid header">
              <div class="dayHead">Sun</div>
              <div class="dayHead">Mon</div>
              <div class="dayHead">Tue</div>
              <div class="dayHead">Wed</div>
              <div class="dayHead">Thu</div>
              <div class="dayHead">Fri</div>
              <div class="dayHead">Sat</div>
            </div>

            <div class="grid" aria-hidden="false">
              <?php
                // render 6 weeks (6*7 = 42 cells)
                $cell = 0;
                // current server date parts for marking today
                $serverY = (int)date('Y');
                $serverM = (int)date('n');
                $serverD = (int)date('j');
                // leading prev-month days
                $lead = $startWeekday; // number of empty cells before 1st
                for ($i = 0; $i < 42; $i++, $cell++) {
                  if ($i < $lead) {
                    // previous month day
                    $num = $daysInPrev - ($lead - 1 - $i);
                    $class = 'other-month';
                    $label = $num;
                    $eventHtml = '';
                  } elseif ($i < $lead + $daysInMonth) {
                    // current month day
                    $d = $i - $lead + 1;
                    $num = $d;
                    $hasEvent = isset($events[$d]);
                    $label = $d;
                    // determine if this cell's date is in the past (server timezone)
                    $cellIso = $first->format('Y-m-') . sprintf('%02d', $d);
                    $isPast = strtotime($cellIso) < strtotime(date('Y-m-d'));
                    $classes = [];
                    if ($hasEvent) $classes[] = 'has-event';
                    if ($hasEvent && $isPast) $classes[] = 'past-event';
                    // mark today (server local date) when calendar is showing current month
                    if ($serverY === $y && $serverM === $m && $d === $serverD) {
                      $classes[] = 'today';
                    }
                    $class = implode(' ', $classes);
                    $eventHtml = $hasEvent ? '<div class="event">' . $events[$d] . '</div>' : '';
                    $dataAttr = ' data-day="' . $d . '"';
                  } else {
                    // next month filler
                    $num = $i - ($lead + $daysInMonth) + 1;
                    $class = 'other-month';
                    $label = $num;
                    $eventHtml = '';
                  }
                  ?>
                  <div class="cell <?php echo $class; ?>"<?php echo ($dataAttr ?? ''); ?> role="button" tabindex="0">
                    <div class="date"><?php echo $label; ?></div>
                    <div class="content"><?php echo $eventHtml; ?></div>
                  </div>
              <?php } ?>
            </div>
          </div>
        </div>

        <aside id="orientationPanel" class="upcoming" aria-label="Orientation">
          <h4>Orientation</h4>
          <div id="orientationContent">
            <div class="meta">Click a day to see orientation details.</div>
          </div>
        </aside>
      </div>
    </div>
  </div>

  <script>
    // expose events data from PHP
    var eventsData = <?php echo json_encode($eventsData ?? []); ?>;

    function renderOrientation(day){
      var container = document.getElementById('orientationContent');
      container.innerHTML = '';
      if (!day || !eventsData[day]){
        container.innerHTML = '<div class="meta">No orientation scheduled.</div>';
        return;
      }
      var sessions = eventsData[day];
      // render sessions with left icons and rows
      sessions.forEach(function(s){
        var sess = document.createElement('div'); sess.className = 'session';

        // time row
        var row1 = document.createElement('div'); row1.className = 'row';
        var icon1 = document.createElement('div'); icon1.className = 'icon';
        icon1.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1a11 11 0 1 0 11 11A11.012 11.012 0 0 0 12 1zm1 12.59V7h-2v6.59l5 3 1-1.66z"></path></svg>';
        var txt1 = document.createElement('div'); txt1.className='text'; txt1.textContent = s.time || '';
        row1.appendChild(icon1); row1.appendChild(txt1);
        sess.appendChild(row1);

        // date row
        var row2 = document.createElement('div'); row2.className = 'row';
        var icon2 = document.createElement('div'); icon2.className = 'icon';
        icon2.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zM5 20V9h14l.002 11H5z"></path></svg>';
        var txt2 = document.createElement('div'); txt2.className='text small'; txt2.textContent = (new Date(s.date)).toLocaleString(undefined, {weekday:'short', year:'numeric', month:'long', day:'numeric'});
        row2.appendChild(icon2); row2.appendChild(txt2);
        sess.appendChild(row2);

        // location row
        var row3 = document.createElement('div'); row3.className = 'row';
        var icon3 = document.createElement('div'); icon3.className = 'icon';
        icon3.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"></path></svg>';
        var txt3 = document.createElement('div'); txt3.className='text'; txt3.textContent = s.location || '';
        row3.appendChild(icon3); row3.appendChild(txt3);
        sess.appendChild(row3);

        // students list with label and print actions
        var studentsWrap = document.createElement('div'); studentsWrap.className = 'students';
        if (s.students && s.students.length > 0) {
          var label = document.createElement('div'); label.className = 'students-label'; label.textContent = 'Students';
          studentsWrap.appendChild(label);
          s.students.forEach(function(st){
            var r = document.createElement('div'); r.className = 'student-row';
            var name = document.createElement('div'); name.className = 'student-name'; name.textContent = st.name || '';
            var pbtn = document.createElement('button'); pbtn.className = 'print-icon'; pbtn.type = 'button';
            pbtn.dataset.studentId = st.id || '';
            pbtn.title = 'Print Endorsement Letter';
            pbtn.setAttribute('aria-label', 'Print Endorsement Letter for ' + (st.name || 'student'));
            pbtn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 7v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7"/><path d="M17 3H7v4h10V3z"/></svg>';
            r.appendChild(name); r.appendChild(pbtn);
            studentsWrap.appendChild(r);
          });
          // Print All button
          var printAll = document.createElement('a'); printAll.className = 'print-all'; printAll.href = '#'; printAll.textContent = 'Print All Endorsement Letter';
          studentsWrap.appendChild(printAll);
        } else {
          var none = document.createElement('div'); none.className = 'meta'; none.textContent = 'No students assigned.';
          studentsWrap.appendChild(none);
        }
        sess.appendChild(studentsWrap);

        container.appendChild(sess);
      });
      // reschedule remains last
      var btn = document.createElement('a'); btn.className='resched'; btn.href='#'; btn.textContent='Reschedule';
      btn.style.marginTop='10px';
      container.appendChild(btn);
    }

    // small keyboard-friendly focus for cells and click handling to populate Orientation panel
    // handle selection outline + rendering
    function clearSelected(){
      var prev = document.querySelector('.cell.selected');
      if (prev) prev.classList.remove('selected');
    }
    document.querySelectorAll('.cell').forEach(function(c){
      c.addEventListener('keydown', function(e){ if(e.key==='Enter' || e.key===' ') { var d = this.dataset.day; clearSelected(); if(d) this.classList.add('selected'); renderOrientation(d); } });
      c.addEventListener('click', function(){ var d = this.dataset.day; clearSelected(); if(d) this.classList.add('selected'); renderOrientation(d); });
    });

    // try to show today's sessions when loading if in current month
    (function(){
      var today = new Date();
      var curMonth = <?php echo (int)$m; ?>;
      var curYear = <?php echo (int)$y; ?>;
      if (today.getMonth()+1 === curMonth && today.getFullYear() === curYear){
        renderOrientation(today.getDate());
      }
    })();
  </script>
</body>
</html>
