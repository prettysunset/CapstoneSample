<?php
session_start();
require_once __DIR__ . '/../conn.php';

// require login
if (!isset($_SESSION['user_id'])) {
    echo '<p>Not logged in</p>'; exit;
}

$user_id = (int)($_SESSION['user_id'] ?? 0);

// resolve student_id (prefer students.user_id link)
$student_id = 0;
$tmp = $conn->prepare("SELECT student_id FROM students WHERE user_id = ? LIMIT 1");
if ($tmp) {
    $tmp->bind_param('i', $user_id);
    $tmp->execute();
    $r = $tmp->get_result()->fetch_assoc();
    $tmp->close();
    if ($r) $student_id = (int)$r['student_id'];
}

$error = '';
// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $week = trim((string)($_POST['week_coverage'] ?? ''));
    $week_from = trim((string)($_POST['week_from'] ?? ''));
    $week_to = trim((string)($_POST['week_to'] ?? ''));
    $content_html = $_POST['content_html'] ?? '';

    if (empty($student_id)) {
        $error = 'Student record not found.';
    } elseif ($week === '') {
        $error = 'Please enter week label.';
    } else {
        // create uploads dir
        $uploadDir = __DIR__ . '/../uploads/journals/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        $savedAttachment = '';
        // Note: DOCX/PDF attachment support removed. Images should be inserted directly into the editor
        // (they are embedded as data-URLs client-side). We keep $savedAttachment empty.

        // if content provided, save to an HTML file (if no pdf/docx was attached or regardless so content is preserved)
        if ($error === '' && trim($content_html) !== '') {
            $docFilename = 'journal_html_' . time() . '_' . (($user_id>0)?$user_id:'x') . '.html';
            $docPath = $uploadDir . $docFilename;
            // basic sanitize: allow the provided HTML but wrap in minimal template
            $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($week, ENT_QUOTES) . '</title></head><body>' . $content_html . '</body></html>';
            if (@file_put_contents($docPath, $html) !== false) {
                if ($savedAttachment === '') $savedAttachment = 'uploads/journals/' . $docFilename;
            }
        }

        if ($error === '') {
            $today = date('Y-m-d');
            // include date range in week_coverage label if provided
            $week_to_store = $week;
            if ($week_from !== '' && $week_to !== '') {
                // store dates in parentheses for reliable parsing later
                $week_to_store .= ' (' . $week_from . '|' . $week_to . ')';
            }

            $stmt = $conn->prepare("INSERT INTO weekly_journal (user_id, week_coverage, date_uploaded, attachment, from_date, to_date) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                // bind from/to if present, else null
                $fromParam = $week_from !== '' ? $week_from : null;
                $toParam = $week_to !== '' ? $week_to : null;
                $bindAttach = $savedAttachment ?: null;
                $stmt->bind_param('isssss', $student_id, $week_to_store, $today, $bindAttach, $fromParam, $toParam);
                if ($stmt->execute()) {
                    $stmt->close();
                    // respond with JS that tells parent to close the overlay and activate journals tab
                    echo '<!doctype html><html><head><meta charset="utf-8"></head><body>';
                    echo '<script>if (window.parent && typeof window.parent.closeCreateJournalOverlay === "function"){ try{ window.parent.closeCreateJournalOverlay(); }catch(e){} } if (window.parent){ try{ var url = window.parent.location.pathname + "?uploaded=1#tab-journals"; window.parent.location.href = url; } catch(e){} }</script>';
                    echo '</body></html>';
                    exit();
                } else {
                    $error = 'Database error: ' . htmlspecialchars($conn->error);
                }
            } else {
                $error = 'Failed to prepare insert: ' . htmlspecialchars($conn->error);
            }
        }
    }
}

// Display composer UI (will be loaded inside overlay iframe)
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create Journal</title>
    <style>
        body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#f6f7fb;color:#222}
        .card{width:100%;max-width:980px;margin:18px auto;background:#fff;border-radius:6px;padding:12px;box-shadow:0 8px 30px rgba(15,20,40,0.15)}
        .toolbar{display:flex;gap:8px;margin-bottom:8px}
        .toolbar button{padding:6px 8px;border:1px solid #e5e7f0;border-radius:4px;background:#fff;cursor:pointer}
        #editor{min-height:320px;border:1px solid #e6e9f2;padding:12px;border-radius:6px;background:#fff;overflow:auto}
        /* ensure images are constrained but adjustable */
        #editor img { max-width:100%; height:auto; cursor:default }
        /* resizer box & handle */
        .img-resizer { position:absolute;border:2px dashed rgba(47,52,89,0.6); box-sizing:border-box; z-index:40; }
        .img-resizer .handle { position:absolute;width:12px;height:12px;background:#fff;border:2px solid #2f3459;border-radius:2px;right:-8px;bottom:-8px;cursor:nwse-resize; }

        .row{display:flex;gap:8px;align-items:center;margin-bottom:8px}
        .row input[type=text]{padding:8px;border:1px solid #e6e9f2;border-radius:4px}
        .actions{display:flex;gap:8px;justify-content:flex-end;margin-top:12px}
        .actions button{padding:8px 12px;border-radius:6px;border:0;background:#2f3459;color:#fff;cursor:pointer}
        .small{font-size:13px;color:#666}
        .error{color:#b00020}
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin:0 0 8px 0;color:#2f3459">Create Weekly Journal</h2>
        <?php if ($error !== ''): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php
        // compute next week number and default date range (Mon-Fri)
        $nextWeekNumber = 1;
        $defaultFrom = '';
        $defaultTo = '';
        $minSelectable = '';
        if (!empty($student_id)) {
            // count existing journals and fetch latest stored to-date
            $q = $conn->prepare("SELECT COUNT(*) AS cnt, MAX(date_uploaded) AS last_uploaded, MAX(COALESCE(from_date,'')) AS last_from, MAX(COALESCE(to_date,'')) AS last_to FROM weekly_journal WHERE user_id = ?");
            if ($q) {
                $q->bind_param('i', $student_id);
                $q->execute();
                $r = $q->get_result()->fetch_assoc();
                $q->close();
                $nextWeekNumber = (int)($r['cnt'] ?? 0) + 1;
                $lastTo = trim((string)($r['last_to'] ?? ''));
                // if there are existing journals, restrict selectable start date to computed next week's from-date
                if (!empty($r['cnt']) && (int)$r['cnt'] > 0) {
                    $minSelectable = '';
                }
                // prefer last_to if valid; otherwise use today
                try {
                    if ($lastTo !== '') {
                        $d = new DateTime($lastTo);
                        // compute next monday after lastTo
                        $dow = (int)$d->format('N');
                        $daysUntilNextMon = (8 - $dow) % 7; if ($daysUntilNextMon === 0) $daysUntilNextMon = 7;
                        $d->modify("+{$daysUntilNextMon} days");
                    } else {
                        $d = new DateTime();
                        $dow = (int)$d->format('N');
                        $daysUntilNextMon = (8 - $dow) % 7; if ($daysUntilNextMon === 0) $daysUntilNextMon = 7;
                        $d->modify("+{$daysUntilNextMon} days");
                    }
                    $defaultFrom = $d->format('Y-m-d');
                    $d->modify('+4 days');
                    $defaultTo = $d->format('Y-m-d');
                } catch (Exception $ex) {
                    $dt = new DateTime();
                    $dt->modify('+1 week');
                    // next monday from today
                    $dow = (int)$dt->format('N');
                    $daysUntilNextMon = (8 - $dow) % 7; if ($daysUntilNextMon === 0) $daysUntilNextMon = 7;
                    $dt->modify("+{$daysUntilNextMon} days");
                    $defaultFrom = $dt->format('Y-m-d');
                    $dt->modify('+4 days');
                    $defaultTo = $dt->format('Y-m-d');
                }
            }
        }
        // if there were existing journals, set minSelectable to computed defaultFrom; otherwise leave blank
        if (!empty($student_id)) {
            try {
                $cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM weekly_journal WHERE user_id = ?");
                if ($cntStmt) {
                    $cntStmt->bind_param('i', $student_id);
                    $cntStmt->execute();
                    $cntR = $cntStmt->get_result()->fetch_assoc();
                    $cntStmt->close();
                    if (!empty($cntR['cnt']) && (int)$cntR['cnt'] > 0) {
                        $minSelectable = $defaultFrom;
                    }
                }
            } catch (Exception $ex) {}
        }
        ?>

        <form id="frm" method="post" enctype="multipart/form-data">
            <div class="row">
                <label class="small">Week label</label>
                <input name="week_coverage" id="week_coverage" type="text" value="<?php echo htmlspecialchars('Week ' . $nextWeekNumber); ?>" readonly required style="flex:1;background:#f3f4f8;border:1px solid #e6e9f2;padding:8px;border-radius:4px">
                <label class="small">From</label>
                <input name="week_from" id="week_from" type="date" value="<?php echo htmlspecialchars($defaultFrom); ?>" <?php if(!empty($minSelectable)) echo 'min="'.htmlspecialchars($minSelectable).'"'; ?> max="<?php echo date('Y-m-d'); ?>">
                <label class="small">To</label>
                <input name="week_to" id="week_to" type="date" value="<?php echo htmlspecialchars($defaultTo); ?>" <?php if(!empty($minSelectable)) echo 'min="'.htmlspecialchars($minSelectable).'"'; ?> max="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="toolbar" role="toolbar">
                <button type="button" data-cmd="bold">B</button>
                <button type="button" data-cmd="italic">I</button>
                <button type="button" data-cmd="underline">U</button>
                <button type="button" id="btn-insert-table">Table</button>
                <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 8px;border:1px solid #e6e9f2;border-radius:4px;cursor:pointer">
                    Insert image <input id="imgfile" type="file" accept="image/*" style="display:none">
                </label>
            </div>

            <div id="editor" contenteditable="true" aria-label="Journal editor"></div>

            <!-- image input is inside the toolbar label above; removed duplicate visible file input -->

            <input type="hidden" name="content_html" id="content_html">
            <div class="actions">
                <button type="button" id="btn-cancel" style="background:#e6e9f2;color:#2f3459">Cancel</button>
                <button type="submit" id="btn-save">Save</button>
            </div>
        </form>
    </div>

    <script>
        // simple formatting
        document.querySelectorAll('.toolbar button[data-cmd]').forEach(function(b){
            b.addEventListener('click', function(){ document.execCommand(this.dataset.cmd, false, null); });
        });

        document.getElementById('btn-insert-table').addEventListener('click', function(){
            var rows = prompt('Rows', '2');
            var cols = prompt('Columns', '2');
            rows = parseInt(rows,10)||0; cols = parseInt(cols,10)||0;
            if (rows>0 && cols>0){
                var t = '<table style="border-collapse:collapse;border:1px solid #ddd">';
                for(var r=0;r<rows;r++){ t += '<tr>'; for(var c=0;c<cols;c++){ t += '<td style="border:1px solid #ddd;padding:6px">&nbsp;</td>'; } t += '</tr>'; }
                t += '</table><p></p>';
                document.execCommand('insertHTML', false, t);
            }
        });

        // insert image as data URL (toolbar hidden input)
        var visibleImg = document.getElementById('imgfile');
        var savedRange = null;

        function saveSelection() {
            try {
                var sel = window.getSelection();
                if (sel && sel.rangeCount > 0) savedRange = sel.getRangeAt(0).cloneRange();
            } catch(e) { savedRange = null; }
        }

        function restoreSelection() {
            try {
                var sel = window.getSelection();
                sel.removeAllRanges();
                if (savedRange) sel.addRange(savedRange);
                return !!savedRange;
            } catch(e) { return false; }
        }

        if (visibleImg) {
            // ensure we capture the editor caret before the file chooser steals focus
            try {
                var imgLabel = visibleImg.closest('label');
                if (imgLabel) imgLabel.addEventListener('click', function(e){ saveSelection(); });
            } catch(e) {}

            // track selection updates inside the editor
            var edWatch = document.getElementById('editor');
            if (edWatch) {
                ['keyup','mouseup','focus','input'].forEach(function(ev){ edWatch.addEventListener(ev, saveSelection); });
            }

            visibleImg.addEventListener('change', function(e){
                var f = this.files && this.files[0];
                if (!f) return;
                if (!f.type.match('image.*')) { alert('Please choose an image file.'); this.value = ''; return; }
                var reader = new FileReader();
                reader.onload = function(ev){
                    var src = ev.target.result;
                    try {
                        var ed = document.getElementById('editor');
                        // restore selection and focus the editor so insertion happens at caret
                        var had = restoreSelection();
                        if (ed) {
                            ed.focus();
                            if (!had) {
                                // move caret to end if no saved selection
                                var sel = window.getSelection();
                                var range = document.createRange();
                                range.selectNodeContents(ed);
                                range.collapse(false);
                                sel.removeAllRanges();
                                sel.addRange(range);
                            }
                            var safeSrc = String(src).replace(/"/g, '\\"');
                            var imgHtml = '<img src="' + safeSrc + '" style="max-width:100%;height:auto;display:block;margin:8px 0;">';
                            document.execCommand('insertHTML', false, imgHtml);
                        } else {
                            document.execCommand('insertImage', false, src);
                        }
                    } catch (ex) {
                        try { document.execCommand('insertImage', false, src); } catch(e) {}
                    }
                };
                reader.readAsDataURL(f);
                this.value = '';
            });
        }

        // cancel
        document.getElementById('btn-cancel').addEventListener('click', function(){
            try{ if (window.parent && typeof window.parent.closeCreateJournalOverlay === 'function') window.parent.closeCreateJournalOverlay(); }catch(e){}
        });

        // Image resizer: show draggable corner to resize images inside editor
        (function(){
            var currentResizer = null;
            function removeResizer(){ if (currentResizer){ try{ currentResizer.parentNode.removeChild(currentResizer); }catch(e){} currentResizer = null; } }

            function updateResizerPos(img, box){
                var r = img.getBoundingClientRect();
                var left = r.left + window.scrollX;
                var top = r.top + window.scrollY;
                box.style.width = Math.round(r.width) + 'px';
                box.style.height = Math.round(r.height) + 'px';
                box.style.left = Math.round(left) + 'px';
                box.style.top = Math.round(top) + 'px';
            }

            function makeResizer(img){
                removeResizer();
                var box = document.createElement('div');
                box.className = 'img-resizer';
                box.innerHTML = '<div class="handle"></div>';
                document.body.appendChild(box);
                updateResizerPos(img, box);
                currentResizer = box;

                var handle = box.querySelector('.handle');
                var startW = 0, startH = 0, startX = 0, startY = 0, aspect = 1;
                function onDown(e){
                    e.preventDefault(); e.stopPropagation();
                    startX = e.clientX; startY = e.clientY;
                    var rect = img.getBoundingClientRect();
                    startW = rect.width; startH = rect.height; aspect = startW / startH;
                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                }
                function onMove(e){
                    var dx = e.clientX - startX;
                    var newW = Math.max(40, Math.round(startW + dx));
                    var newH = Math.round(newW / aspect);
                    img.style.width = newW + 'px';
                    img.style.height = 'auto';
                    updateResizerPos(img, box);
                }
                function onUp(e){
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                }
                handle.addEventListener('mousedown', onDown);

                // reposition on window events
                var obs = new MutationObserver(function(){ updateResizerPos(img, box); });
                obs.observe(img, { attributes: true, attributeFilter: ['style', 'class'] });
                window.addEventListener('scroll', function(){ if (currentResizer) updateResizerPos(img, currentResizer); });

                // remove resizer if click outside
                setTimeout(function(){
                    document.addEventListener('click', function onDocClick(ev){
                        if (!currentResizer) { document.removeEventListener('click', onDocClick); return; }
                        if (ev.target === img || currentResizer.contains(ev.target)) return;
                        removeResizer();
                        document.removeEventListener('click', onDocClick);
                    });
                }, 10);
            }

            // delegate clicks inside editor to images
            var editorNode = document.getElementById('editor');
            if (editorNode) {
                editorNode.addEventListener('click', function(e){
                    var t = e.target;
                    if (t && t.tagName === 'IMG') {
                        try { makeResizer(t); } catch(ex) {}
                    } else {
                        // clicking outside any image removes resizer
                        removeResizer();
                    }
                });
            }

            // also when we insert an image programmatically, attach a resizer to it
            var origInsert = document.execCommand;
            // we don't override execCommand; instead provide a helper to attach after insertion
            // observe added images inside editor and add resizer on click (delegate above handles it)
        })();

        // date helpers
        function addDays(dateStr, days) {
            if (!dateStr) return '';
            var d = new Date(dateStr + 'T00:00:00');
            d.setDate(d.getDate() + days);
            return d.toISOString().slice(0,10);
        }

        var inpFrom = document.getElementById('week_from');
        var inpTo = document.getElementById('week_to');
        // initialize min constraint from server-side computation (matches ojt_profile.php)
        try {
            var serverMin = '<?php echo htmlspecialchars($minSelectable); ?>';
            if (inpFrom && serverMin) {
                inpFrom.min = serverMin;
                if (inpFrom.value && inpFrom.value < inpFrom.min) inpFrom.value = inpFrom.min;
            }
            if (inpTo && serverMin) {
                inpTo.min = serverMin;
                if (inpTo.value && inpTo.value < inpTo.min) inpTo.value = inpTo.min;
            }
        } catch(e) {}
        if (inpFrom && inpTo) {
            // ensure To is always at least From + 4 days (Mon-Fri)
            inpFrom.addEventListener('change', function(){
                if (!this.value) return;
                try {
                    var suggested = addDays(this.value, 4);
                    // if current To is empty or earlier than suggested, set it
                    if (!inpTo.value || inpTo.value < this.value || inpTo.value < suggested) {
                        inpTo.value = suggested;
                    }
                    inpTo.min = this.value;
                } catch(e) {}
            });

            inpTo.addEventListener('change', function(){
                if (!this.value || !inpFrom.value) return;
                if (this.value < inpFrom.value) {
                    alert('Invalid range: "To" must be the same or after "From".');
                    // restore suggested
                    this.value = addDays(inpFrom.value, 4);
                }
            });
        }

        // submit: validate dates and copy content
        document.getElementById('frm').addEventListener('submit', function(e){
            var ed = document.getElementById('editor');
            document.getElementById('content_html').value = ed.innerHTML;

            // validate date inputs
            var fromVal = inpFrom ? inpFrom.value : '';
            var toVal = inpTo ? inpTo.value : '';
            if (!fromVal || !toVal) {
                e.preventDefault();
                alert('Please select both From and To dates.');
                return false;
            }
            if (toVal < fromVal) {
                e.preventDefault();
                alert('Invalid range: "To" must be the same or after "From".');
                return false;
            }

            // allow native submit
        });
    </script>
</body>
</html>
