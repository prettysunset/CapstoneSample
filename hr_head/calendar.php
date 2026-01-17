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
    // build a simple sample events map (day => label)
    $events = [];
    // sample events for demo; replace with real data as needed
    $events[(int)min(28,$daysInMonth, 10)] = '8:30 AM — LOCATION';
    if ($daysInMonth >= 21) $events[21] = 'Event';
    // navigation helpers
    $prevMonth = (clone $first)->modify('-1 month');
    $nextMonth = (clone $first)->modify('+1 month');
    $prevParams = '?m=' . (int)$prevMonth->format('n') . '&y=' . (int)$prevMonth->format('Y');
    $nextParams = '?m=' . (int)$nextMonth->format('n') . '&y=' . (int)$nextMonth->format('Y');
  ?>
  <style>
    *{box-sizing:border-box;font-family:Poppins,system-ui,Segoe UI,Arial,sans-serif}
    html,body{height:100%;margin:0;background:transparent;color:#111;overflow:hidden}
    /* make the iframe content use the full available width (no nested large white panel) */
    /* make the root panels fill the iframe viewport so sizing is self-contained */
    .outer-panel{display:block;padding:0;margin:0;width:100%;height:100%}
    .outer-panel .panel{background:transparent;border-radius:0;padding:0;max-width:none;width:100%;box-shadow:none;height:100%;display:flex;align-items:center;justify-content:center}
    /* inner card sits centered within the large panel and contains the calendar */
    .card{background:#fff;border-radius:18px;padding:24px;max-width:1180px;width:calc(100% - 64px);height:calc(100% - 64px);margin:0 auto;display:flex;align-items:center;justify-content:center;box-shadow:0 18px 40px rgba(16,24,40,0.12)}
    .toolbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
    .toggles{display:flex;gap:8px}
    .toggle{padding:8px 12px;border-radius:12px;background:#f3f3ff;color:#5a3db0;font-weight:700;cursor:pointer;border:0}
    .toggles .muted{background:#fff;color:#667;border:1px solid #eee}
    .title{font-weight:800;color:#2f3850}
    .content{display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start}
    .calendar{padding:12px;border-radius:12px;background:transparent}
    /* the visible calendar box */
    /* let the visible calendar expand to fill container */
    .inner-calendar{background:#fff;border-radius:12px;padding:28px;box-shadow:0 12px 30px rgba(16,24,40,0.08);max-width:none;width:100%;box-sizing:border-box;height:100%;display:flex;flex-direction:column}
    .monthLabel{font-weight:700;color:#2f3850;margin-bottom:8px}
    /* Responsive sizing: compute a safe square cell size so the calendar fits the modal without scroll */
    .inner-calendar{
      --upcoming-w:320px; /* space reserved for right panel */
      --gap:8px;
      --inner-pad:48px; /* left+right padding inside the modal (matches .card padding) */
      /* available width for calendar grid (card width minus upcoming panel and paddings) */
      --avail-w: calc(100% - var(--upcoming-w) - var(--inner-pad));
      /* available height for grid (card height minus toolbar/controls space) */
      --avail-h: calc(100% - 140px);
      /* compute candidate sizes: width-based and height-based */
      --cell-w: calc((var(--avail-w) - (6 * var(--gap))) / 7);
      --cell-h: calc((var(--avail-h) - (5 * var(--gap))) / 6);
      /* ensure at least 100px per cell, but allow growth up to 8rem */
      --cell-size: clamp(100px, min(var(--cell-w), var(--cell-h)), 8rem);
    }

    .grid{display:grid;grid-template-columns:repeat(7,var(--cell-size));gap:var(--gap);justify-content:center}
    .dayHead{font-size:12px;color:#666;text-align:center;padding:8px 0}
    /* Make calendar cells square based on computed --cell-size */
    .cell{background:#fff;border-radius:8px;border:1px solid #f0f0f6;padding:6px;position:relative;transition:all .18s ease;display:flex;flex-direction:column;width:var(--cell-size);height:var(--cell-size);box-sizing:border-box}
    .cell .date{position:absolute;top:6px;right:8px;font-size:12px;color:#9aa;transition:color .12s ease}
    .cell .content{margin-top:22px;flex:1;display:flex;align-items:flex-start;overflow:hidden}
    .cell.has-event .date{color:#6a3db5;font-weight:700}
    /* hover effect for event date (purple accent) */
    .cell.has-event:hover{box-shadow:0 8px 20px rgba(106,61,181,0.08);transform:translateY(-4px)}
    .cell.has-event:hover .date{color:#6a3db5}
    .event{display:inline-block;margin-top:28px;background:#efe6ff;color:#4a148c;padding:6px 8px;border-radius:8px;font-size:12px}
    .controls{display:flex;gap:8px;align-items:center}

    /* Upcoming panel (right side) */
    .upcoming{background:#fff;border-radius:12px;padding:16px;border:1px solid #f3f0fb}
    .upcoming h4{margin:0 0 10px 0;color:#3a2b6a}
    .upcoming .item{display:flex;align-items:flex-start;gap:8px;padding:8px 0;border-bottom:1px dashed #f2eef9}
    .upcoming .item:last-child{border-bottom:none}
    .dot{width:12px;height:12px;border-radius:50%;background:#efe6ff;flex:0 0 12px;margin-top:4px}
    .dot.purple{background:#efe6ff}
    .upcoming .meta{color:#6d6d6d;font-size:13px}
    .resched{display:block;margin-top:12px;padding:8px 12px;border-radius:8px;background:#6a3db5;color:#fff;text-align:center;text-decoration:none;width:100%}

    @media (max-width:1200px){ .outer-panel .panel{width:100%;padding:18px} }
    @media (max-width:980px){ .content{grid-template-columns:1fr 260px} }
    /* On small screens, reduce upcoming panel and cell limits so nothing overflows */
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

    /* ensure no scrollbars appear inside iframe: keep overflow hidden at root levels */
    .outer-panel, .panel, .card, .inner-calendar { overflow: hidden; }
    /* add comfortable white background and internal spacing so the modal resembles the screenshot */
    body { background: transparent; }
    /* make the upcoming panel and grid share available height */
    .content{height:100%;align-items:stretch}
    .calendar{display:flex;flex:1 1 auto;min-width:0}
    .upcoming{height:100%;overflow:hidden}
    .grid[aria-hidden="false"]{display:grid;grid-template-rows:repeat(6,var(--cell-size));align-content:start}
  </style>
</head>
<body>
  <div class="outer-panel">
    <div class="panel">
      <div class="card" role="region" aria-label="Calendar">
      <div class="toolbar">
        <div style="display:flex;align-items:center;gap:12px">
          <div class="toggles">
            <button class="toggle">Month</button>
            <button class="toggle muted" style="background:#fff;border:1px solid #eee">Week</button>
            <button class="toggle muted" style="background:#fff;border:1px solid #eee">Day</button>
          </div>
        </div>
        <div class="controls">
          <div class="title">January 2022</div>
        </div>
      </div>

      <div class="content">
        <div class="calendar">
          <div class="inner-calendar">
            <div style="display:grid;grid-template-columns:1fr 1fr;align-items:center;margin-bottom:8px">
              <div style="display:flex;gap:8px;align-items:center">
                <button style="padding:8px 12px;border-radius:8px;border:0;background:#6a3db5;color:#fff;cursor:pointer">+ Add</button>
              </div>
              <div style="text-align:right;color:#777;font-size:13px">&nbsp;</div>
            </div>

            <div class="grid" style="margin-bottom:10px">
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
                    $class = $hasEvent ? 'has-event' : '';
                    $label = $d;
                    $eventHtml = $hasEvent ? '<div class="event">' . htmlspecialchars($events[$d]) . '</div>' : '';
                  } else {
                    // next month filler
                    $num = $i - ($lead + $daysInMonth) + 1;
                    $class = 'other-month';
                    $label = $num;
                    $eventHtml = '';
                  }
                  ?>
                  <div class="cell <?php echo $class; ?> <?php echo ($hasEvent ?? false) ? 'has-event' : ''; ?>" role="button" tabindex="0">
                    <div class="date"><?php echo $label; ?></div>
                    <div class="content"><?php echo $eventHtml; ?></div>
                  </div>
              <?php } ?>
            </div>
          </div>
        </div>

        <aside class="upcoming" aria-label="Upcoming events">
          <h4>Upcoming</h4>
          <div class="item">
            <div class="dot purple"></div>
            <div>
              <div style="font-weight:700;color:#3a2b6a">8:30 A.M.</div>
              <div class="meta">Mon, January 10, 2026</div>
              <div class="meta">LOCATION</div>
            </div>
          </div>
          <div class="item">
            <div class="dot purple"></div>
            <div>
              <div style="font-weight:700;color:#3a2b6a">Rescheduled</div>
              <div class="meta">Date changed</div>
            </div>
          </div>
          <a href="#" class="resched">Reschedule</a>
        </aside>
      </div>
    </div>
  </div>

  <script>
    // small keyboard-friendly focus for cells
    document.querySelectorAll('.cell').forEach(c=>{
      c.addEventListener('keydown', function(e){ if(e.key==='Enter' || e.key===' ') alert('Open event details'); });
      c.addEventListener('click', function(){ /* placeholder */ });
    });
  </script>
</body>
</html>
