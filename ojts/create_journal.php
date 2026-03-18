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

$student_name = '';
$assigned_office = '';
$supervisor_name = '';

if (!empty($student_id)) {
    $st = $conn->prepare("SELECT first_name, last_name FROM students WHERE student_id = ? LIMIT 1");
    if ($st) {
        $st->bind_param('i', $student_id);
        $st->execute();
        $sr = $st->get_result()->fetch_assoc();
        $st->close();
        if ($sr) {
            $student_name = trim(($sr['first_name'] ?? '') . ' ' . ($sr['last_name'] ?? ''));
        }
    }

    $qa = $conn->prepare("SELECT remarks FROM ojt_applications WHERE student_id = ? ORDER BY date_updated DESC, application_id DESC LIMIT 1");
    if ($qa) {
        $qa->bind_param('i', $student_id);
        $qa->execute();
        $ar = $qa->get_result()->fetch_assoc();
        $qa->close();
        if ($ar && !empty($ar['remarks']) && preg_match('/Assigned Office:\s*([^|]+)/i', (string)$ar['remarks'], $mOffice)) {
            $assigned_office = trim((string)$mOffice[1]);
        }
    }

    if ($assigned_office !== '') {
        $ao = trim((string)preg_replace('/\bOffice\b/i', '', $assigned_office));
        $aoLower = strtolower($ao);
        $like = '%' . $aoLower . '%';

        $oh = $conn->prepare("SELECT first_name, last_name FROM users WHERE role = 'office_head' AND (LOWER(office_name) LIKE ? OR LOWER(office_name) = ?) LIMIT 1");
        if ($oh) {
            $oh->bind_param('ss', $like, $aoLower);
            $oh->execute();
            $ohr = $oh->get_result()->fetch_assoc();
            $oh->close();
            if ($ohr) {
                $supervisor_name = trim(($ohr['first_name'] ?? '') . ' ' . ($ohr['last_name'] ?? ''));
            }
        }
    }
}
// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $week = trim((string)($_POST['week_coverage'] ?? ''));
    $week_from = trim((string)($_POST['week_from'] ?? ''));
    $week_to = trim((string)($_POST['week_to'] ?? ''));
    $content_html = $_POST['content_html'] ?? '';

    // if content_html is empty, block save
    if (trim((string)$content_html) === '') {
        $error = 'Journal content is empty.';
    }

    // Server-side validation: ensure every Work Description (2nd column) in the journal table has text
    if ($content_html !== '') {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $loaded = $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content_html);
        if ($loaded) {
            $xpath = new DOMXPath($doc);
            // select rows inside .journal-table tbody
            $rows = $xpath->query("//table[contains(@class,'journal-table')]/tbody/tr");
            if ($rows && $rows->length > 0) {
                foreach ($rows as $r) {
                    // skip total row (has a td with colspan or fewer than 2 tds)
                    $tds = [];
                    foreach ($r->getElementsByTagName('td') as $td) $tds[] = $td;
                    if (count($tds) < 2) continue;
                    $firstTd = $tds[0];
                    // if first td has colspan attribute, likely total row
                    if ($firstTd->hasAttribute('colspan')) continue;
                    $workTd = $tds[1];
                    $text = trim(preg_replace('/\s+/', ' ', $workTd->textContent));
                    if ($text === '') {
                        $label = trim(preg_replace('/\s+/', ' ', $firstTd->textContent));
                        $error = 'Please fill Work Description for: ' . htmlspecialchars($label);
                        break;
                    }
                }
            }
        }
        libxml_clear_errors();
    }

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
            $docFilename = 'journal_' . time() . '_' . (($user_id>0)?$user_id:'x') . '.html';
            $docPath = $uploadDir . $docFilename;

            // Prepare a saved HTML that preserves the editor appearance for printing/PDF.
            // Neutralize contenteditable to avoid editable controls in the saved file.
            $content_for_save = preg_replace('/\scontenteditable=("|\')?true("|\')?/i', ' contenteditable="false"', $content_html);

            // Include the page styles from the editor so the saved HTML looks the same when printed or converted to PDF.
            $saveStyles = <<<'CSS'
        /* Match on-screen editor layout to preserve positions when saving */
        html,body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:0;background:#fff;color:#111;-webkit-print-color-adjust:exact}
        body{background:#fff}
        /* page sizing mirrors the editor's page: px-based to keep exact visual flow */
        .page { width:840px; min-height:1100px; margin:18px auto; padding:28px; box-sizing:border-box; background:#fff }
        .page-content { min-height:100px }
        .journal-template { max-width: 784px; margin: 0 auto; font-family: "Times New Roman", serif; color: #111; font-size: 11pt; line-height: 1.3; }
        .journal-template .header-line { border-top: 2px solid #4b6b4d; margin: 8px 0 28px; }
        .journal-template .title-main { text-align: center; font-weight: 700; font-size: 42px; margin-bottom: 22px }
        .journal-template .meta-line { margin: 3px 0; font-size: 11pt }
        .journal-table { width: 100%; border-collapse: collapse; margin-top: 18px; table-layout: fixed; }
        .journal-table th, .journal-table td { border: 1px solid #222; padding: 4px 6px; vertical-align: top; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; }
        .journal-table th { text-align: center; font-weight: 400; padding: 4px 6px; }
        .editable-inline { display:inline-block; vertical-align: middle; max-width:72%; }
        .editable-cell { min-height: 52px; vertical-align: top; }
        /* keep inserted images inline and avoid page breaks inside them */
        img.inserted-image, .inserted-image { page-break-inside: avoid; display:inline-block; margin:6px; vertical-align:middle; max-width:100%; height:auto }
        img { max-width:100%; height:auto }
        /* remove visual-only effects that don't print reliably */
        .card, .toolbar, .img-resizer { box-shadow: none }
        @media print { .page { page-break-after: always; box-shadow:none; margin:0 auto } }
        /* prevent tables from moving content unexpectedly when rendered to PDF */
        table { border-collapse: collapse }
        td, th { box-sizing: border-box }
CSS;

            // basic sanitize: allow the provided HTML but wrap in minimal template that includes styles
            $html = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . htmlspecialchars($week, ENT_QUOTES) . '</title><style>' . $saveStyles . '</style></head><body>' . $content_for_save . '</body></html>';
            if (@file_put_contents($docPath, $html) !== false) {
                // Do not keep the intermediate HTML file as an attachment.
                // Attempt conversion below; we'll remove the HTML file regardless
                // and only save the PDF when conversion succeeds.
                // Try to convert the saved HTML to PDF.
                $pdfFilename = preg_replace('/\.html$/i', '.pdf', $docFilename);
                $pdfPath = $uploadDir . $pdfFilename;
                $converted = false;

                // If Composer autoload exists and Dompdf is installed, prefer Dompdf (pure PHP).
                $autoload = __DIR__ . '/../vendor/autoload.php';
                if (file_exists($autoload)) {
                    try {
                        require_once $autoload;
                        if (class_exists('\Dompdf\Dompdf')) {
                            try {
                                $dompdf = new \Dompdf\Dompdf();
                                $dompdf->set_option('isRemoteEnabled', true);
                                $dompdf->set_option('isHtml5ParserEnabled', true);
                                $dompdf->set_option('dpi', 150);
                                $dompdf->loadHtml($html);
                                $dompdf->setPaper('A4', 'portrait');
                                $dompdf->render();
                                $pdfContent = $dompdf->output();
                                if (@file_put_contents($pdfPath, $pdfContent) !== false) {
                                    $converted = true;
                                }
                            } catch (Exception $e) {
                                error_log('Dompdf conversion failed: ' . $e->getMessage());
                            }
                        }
                    } catch (Exception $e) {
                        // ignore autoload require errors
                    }
                }

                // Fallback: try wkhtmltopdf if Dompdf not available/failed
                if (!$converted) {
                    $wkCandidates = array('wkhtmltopdf', '/usr/local/bin/wkhtmltopdf', '/usr/bin/wkhtmltopdf');
                    $out = array();
                    foreach ($wkCandidates as $wk) {
                        if ($converted) break;
                        $cmd = escapeshellcmd($wk) . ' --enable-local-file-access ' . escapeshellarg($docPath) . ' ' . escapeshellarg($pdfPath) . ' 2>&1';
                        $rc = null; $out = array();
                        if (function_exists('exec')) {
                            @exec($cmd, $out, $rc);
                            if ($rc === 0 && file_exists($pdfPath)) $converted = true;
                        } elseif (function_exists('shell_exec')) {
                            $res = @shell_exec($cmd);
                            if ($res !== null) {
                                $out = explode("\n", trim((string)$res));
                                if (file_exists($pdfPath)) $converted = true;
                            }
                        } else {
                            // exec functions unavailable; can't run wkhtmltopdf
                            break;
                        }
                    }
                    if (!$converted && !empty($out)) error_log('wkhtmltopdf output: ' . implode("\n", (array)$out));
                }

                if ($converted) {
                    $savedAttachment = 'uploads/journals/' . $pdfFilename;
                }

                // Always remove the intermediate HTML file to avoid leaving journal_html_ pages.
                if (file_exists($docPath)) {
                    @unlink($docPath);
                }
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
        /* inserted images default: inline, keep original size (max-width constrained) */
        .inserted-image { display:inline-block; margin:6px; vertical-align:middle; max-width:100%; height:auto; }
        /* paged editor: each page simulates a paper. When content exceeds page height a new page is created */
        #editor { display:block }
        .page { width:840px; min-height:1100px; margin:18px auto; background:#fff; box-shadow:0 6px 18px rgba(0,0,0,0.06); padding:28px; box-sizing:border-box; }
        .page-content { min-height:100px; outline:none }
        @media print { .page { page-break-after: always; box-shadow:none; margin:0 auto } }
        #editor .journal-template { max-width: 784px; margin: 0 auto; font-family: "Times New Roman", serif; color: #111; font-size: 18px; line-height: 1.25; position: relative; }
        #editor .journal-template .header-line { border-top: 2px solid #4b6b4d; margin: 8px 0 28px; }
        #editor .journal-template .title { text-align: center; font-weight: 700; font-size: 19px; }
        #editor .journal-template .title-main { text-align: center; font-weight: 700; font-size: 42px; margin-bottom: 22px; }
        #editor .journal-template .meta-line { margin: 3px 0; }
        #editor .journal-template .journal-table { width: 100%; border-collapse: collapse; margin-top: 18px; table-layout: fixed; }
        #editor .journal-template .journal-table th,
        #editor .journal-template .journal-table td { border: 1px solid #222; padding: 4px 6px; vertical-align: top; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; }
        #editor .journal-template .journal-table th { border: 1px solid #222; padding: 4px 6px; }
        #editor .journal-template .journal-table th { text-align: center; font-weight: 400; }
        #editor .journal-template .learning { margin-top: 18px; }
        #editor .journal-template .sign-row { display: flex; justify-content: space-between; margin-top: 24px; }
        #editor .journal-template .sign-col { width: 46%; }
        #editor .journal-template .sign-name { margin-top: 38px; }
        #editor .journal-template .doc-title { margin-top: 20px; text-align: center; font-size: 40px; font-weight: 700; }
        #editor .journal-template .editable-inline {
            display: inline-block;
            max-width: 72%;
            min-height: 1.2em;
            vertical-align: top;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        #editor .journal-template .editable-cell {
            min-height: 52px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
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
        // fetch all distinct DTR log dates for this user (used client-side to build rows)
        $allDtrDates = [];
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
                // Prefer to compute default From/To based on actual DTR logs (first uncovered log -> same-week last logged day)
                $defaultFrom = '';
                $defaultTo = '';
                try {
                    $dtrUserId = !empty($user_id) ? (int)$user_id : (!empty($student_id) ? (int)$student_id : null);
                    if (!empty($dtrUserId)) {
                        // fetch per-date total hours for this user (hours + minutes/60)
                        $dates = [];
                        $dtrHours = [];
                        $qDates = $conn->prepare("SELECT log_date, IFNULL(SUM(hours + minutes/60),0) AS hrs FROM dtr WHERE student_id = ? AND COALESCE(log_date,'') <> '' GROUP BY log_date ORDER BY log_date ASC");
                        if ($qDates) {
                            $qDates->bind_param('i', $dtrUserId);
                            $qDates->execute();
                            $res = $qDates->get_result();
                            while ($rrow = $res->fetch_assoc()) {
                                $d = $rrow['log_date'];
                                $h = (float)$rrow['hrs'];
                                $dates[] = $d;
                                $dtrHours[$d] = $h;
                            }
                            $qDates->close();
                        }
                        // expose all DTR dates and per-date hours for client-side logic
                        $allDtrDates = $dates;

                        // collect existing covered ranges from weekly_journal (only rows with both from_date and to_date)
                        $covered = [];
                        $qCov = $conn->prepare("SELECT from_date,to_date FROM weekly_journal WHERE user_id = ? AND COALESCE(from_date,'') <> '' AND COALESCE(to_date,'') <> ''");
                        if ($qCov) {
                            $qCov->bind_param('i', $student_id);
                            $qCov->execute();
                            $rc = $qCov->get_result();
                            while ($rr = $rc->fetch_assoc()) $covered[] = [$rr['from_date'], $rr['to_date']];
                            $qCov->close();
                        }

                        // find first log date not covered by existing journal ranges
                        $firstUncovered = null;
                        foreach ($dates as $dstr) {
                            $isCovered = false;
                            foreach ($covered as $c) {
                                if ($dstr >= $c[0] && $dstr <= $c[1]) { $isCovered = true; break; }
                            }
                            if (!$isCovered) { $firstUncovered = $dstr; break; }
                        }

                        if (!empty($firstUncovered)) {
                            $defaultFrom = $firstUncovered;
                            $dt = new DateTime($firstUncovered);
                            // compute monday..friday of that week
                            $monday = clone $dt; $monday->modify('this week monday');
                            $friday = clone $monday; $friday->modify('+4 days');
                            $qMax = $conn->prepare("SELECT MAX(log_date) AS last_in_week FROM dtr WHERE student_id = ? AND log_date BETWEEN ? AND ?");
                            if ($qMax) {
                                $m1 = $monday->format('Y-m-d');
                                $m2 = $friday->format('Y-m-d');
                                $qMax->bind_param('iss', $dtrUserId, $m1, $m2);
                                $qMax->execute();
                                $rmax = $qMax->get_result()->fetch_assoc();
                                $qMax->close();
                                if (!empty($rmax['last_in_week'])) $defaultTo = $rmax['last_in_week'];
                                else $defaultTo = $firstUncovered;
                            } else {
                                $defaultTo = $firstUncovered;
                            }
                        }
                    }
                } catch (Exception $ex) {
                    // fallback to previous behavior: next monday
                    $dt = new DateTime();
                    $dt->modify('+1 week');
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

        <?php
        $templateStudentName = $student_name !== '' ? $student_name : '';
        $templateCompany = $assigned_office !== '' ? $assigned_office : '';
        $templateSupervisor = $supervisor_name !== '' ? $supervisor_name : '';

        $date1 = '';
        $date2 = '';
        $date3 = '';
        $date4 = '';
        $date5 = '';
        // Build initial rows based on DTR log dates within defaultFrom..defaultTo
        $initialLogDates = [];
        if (!empty($defaultFrom) && !empty($defaultTo) && !empty($allDtrDates)) {
            foreach ($allDtrDates as $dd) {
                if ($dd >= $defaultFrom && $dd <= $defaultTo) $initialLogDates[] = $dd;
            }
        }
        // fallback to Mon..Fri if no log dates found
        if (empty($initialLogDates) && !empty($defaultFrom)) {
            try {
                $d1 = new DateTime($defaultFrom);
                $d2 = (clone $d1)->modify('+1 day');
                $d3 = (clone $d1)->modify('+2 day');
                $d4 = (clone $d1)->modify('+3 day');
                $d5 = (clone $d1)->modify('+4 day');
                $date1 = $d1->format('F j, Y');
                $date2 = $d2->format('F j, Y');
                $date3 = $d3->format('F j, Y');
                $date4 = $d4->format('F j, Y');
                $date5 = $d5->format('F j, Y');
            } catch (Exception $e) {
                $date1 = '';
                $date2 = '';
                $date3 = '';
                $date4 = '';
                $date5 = '';
            }
        }

        // Build rows HTML: prefer initialLogDates; if empty use the five weekdays computed above
        $rowsHtml = '';
        $totalInitial = 0.0;
        if (!empty($initialLogDates)) {
            foreach ($initialLogDates as $ld) {
                $label = date('l', strtotime($ld));
                $fmt = date('F j, Y', strtotime($ld));
                $hrs = isset($dtrHours[$ld]) ? $dtrHours[$ld] : '';
                $hrsDisplay = $hrs === '' ? '' : (string)((int)floor($hrs));
                if ($hrs !== '') $totalInitial += (int)floor($hrs);
                $rowsHtml .= "<tr>\n<td>" . htmlspecialchars($label) . "<br>" . htmlspecialchars($fmt) . "</td>\n<td class=\"editable-cell\" contenteditable=\"true\"></td>\n<td class=\"editable-cell\" contenteditable=\"true\" style=\"text-align:center;\">" . htmlspecialchars($hrsDisplay) . "</td>\n</tr>\n";
            }
        } else {
            // fallback rows (Mon-Fri) — use $d1..$d5 DateTime objects computed earlier
            $d1k = isset($d1) ? $d1->format('Y-m-d') : '';
            $d2k = isset($d2) ? $d2->format('Y-m-d') : '';
            $d3k = isset($d3) ? $d3->format('Y-m-d') : '';
            $d4k = isset($d4) ? $d4->format('Y-m-d') : '';
            $d5k = isset($d5) ? $d5->format('Y-m-d') : '';
            $d1h = ($d1k && isset($dtrHours[$d1k])) ? $dtrHours[$d1k] : '';
            $d2h = ($d2k && isset($dtrHours[$d2k])) ? $dtrHours[$d2k] : '';
            $d3h = ($d3k && isset($dtrHours[$d3k])) ? $dtrHours[$d3k] : '';
            $d4h = ($d4k && isset($dtrHours[$d4k])) ? $dtrHours[$d4k] : '';
            $d5h = ($d5k && isset($dtrHours[$d5k])) ? $dtrHours[$d5k] : '';
            $d1hDisplay = $d1h === '' ? '' : (string)((int)floor($d1h));
            $d2hDisplay = $d2h === '' ? '' : (string)((int)floor($d2h));
            $d3hDisplay = $d3h === '' ? '' : (string)((int)floor($d3h));
            $d4hDisplay = $d4h === '' ? '' : (string)((int)floor($d4h));
            $d5hDisplay = $d5h === '' ? '' : (string)((int)floor($d5h));
            if ($d1h !== '') $totalInitial += (int)floor($d1h);
            if ($d2h !== '') $totalInitial += (int)floor($d2h);
            if ($d3h !== '') $totalInitial += (int)floor($d3h);
            if ($d4h !== '') $totalInitial += (int)floor($d4h);
            if ($d5h !== '') $totalInitial += (int)floor($d5h);
            $rowsHtml .= "<tr>\n<td>Monday<br>" . htmlspecialchars($date1) . "</td>\n<td class=\"editable-cell\" contenteditable=\"true\"></td>\n<td class=\"editable-cell\" contenteditable=\"true\" style=\"text-align:center;\">" . htmlspecialchars($d1hDisplay) . "</td>\n</tr>\n";
            $rowsHtml .= "<tr>\n<td>Tuesday<br>" . htmlspecialchars($date2) . "</td>\n<td class=\"editable-cell\" contenteditable=\"true\"></td>\n<td class=\"editable-cell\" contenteditable=\"true\" style=\"text-align:center;\">" . htmlspecialchars($d2hDisplay) . "</td>\n</tr>\n";
            $rowsHtml .= "<tr>\n<td>Wednesday<br>" . htmlspecialchars($date3) . "</td>\n<td class=\"editable-cell\" contenteditable=\"true\"></td>\n<td class=\"editable-cell\" contenteditable=\"true\" style=\"text-align:center;\">" . htmlspecialchars($d3hDisplay) . "</td>\n</tr>\n";
            $rowsHtml .= "<tr>\n<td>Thursday<br>" . htmlspecialchars($date4) . "</td>\n<td class=\"editable-cell\" contenteditable=\"true\"></td>\n<td class=\"editable-cell\" contenteditable=\"true\" style=\"text-align:center;\">" . htmlspecialchars($d4hDisplay) . "</td>\n</tr>\n";
            $rowsHtml .= "<tr>\n<td>Friday<br>" . htmlspecialchars($date5) . "</td>\n<td class=\"editable-cell\" contenteditable=\"true\"></td>\n<td class=\"editable-cell\" contenteditable=\"true\" style=\"text-align:center;\">" . htmlspecialchars($d5hDisplay) . "</td>\n</tr>\n";
        }

        // always add Total Hours row (centered)
        $totalDisplay = $totalInitial === 0 ? '' : rtrim(rtrim(number_format((float)$totalInitial, 2, '.', ''), '0'), '.');
        $rowsHtml .= "<tr>\n<td colspan=\"2\" style=\"text-align:right;\">Total Hours:</td>\n<td id=\"total-hours-cell\" class=\"editable-cell\" contenteditable=\"true\" style=\"text-align:center;\">" . htmlspecialchars($totalDisplay) . "</td>\n</tr>\n";

        // format inclusive date for display (e.g., "March 3, 2026")
        $inclusiveDisplay = '';
        if (!empty($defaultFrom) && !empty($defaultTo)) {
            try {
                $dfmt = new DateTime($defaultFrom);
                $dtmt = new DateTime($defaultTo);
                if ($defaultFrom === $defaultTo) {
                    // same single date
                    $inclusiveDisplay = $dfmt->format('F j, Y');
                } else {
                    $inclusiveDisplay = $dfmt->format('F j, Y') . ' to ' . $dtmt->format('F j, Y');
                }
            } catch (Exception $ex) {
                if ($defaultFrom === $defaultTo) $inclusiveDisplay = $defaultFrom; else $inclusiveDisplay = $defaultFrom . ' to ' . $defaultTo;
            }
        }

        $defaultTemplateHtml =
            '<div class="page">'
            . '<div class="page-content" contenteditable="true">'
            . '<div class="journal-template">'
            . '<div class="title-main">OJT - Weekly Journal</div>'
            . '<div class="meta-line">Student\'s Name: <span class="editable-inline" contenteditable="true">' . htmlspecialchars($templateStudentName, ENT_QUOTES) . '</span></div>'
            . '<div class="meta-line">Supervisor\'s Name: <span class="editable-inline" contenteditable="true">' . htmlspecialchars($templateSupervisor, ENT_QUOTES) . '</span></div>'
            . '<div class="meta-line">Inclusive Date: <span class="editable-inline" contenteditable="true">' . htmlspecialchars($inclusiveDisplay, ENT_QUOTES) . '</span></div>'
            . '<table class="journal-table">'
            . '<thead><tr><th style="width:20%;">Date</th><th style="width:60%;">Work Description</th><th style="width:20%;">No. of Hours</th></tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '<div class="learning">Point of Learning: <span class="editable-inline" contenteditable="true"></span></div>'
            . '<div class="doc-title">Documentation</div><div style="min-height:260px;"></div>'
            . '</div></div></div>';
        ?>

        <form id="frm" method="post" enctype="multipart/form-data">
            <div class="row">
                <div style="flex:1;padding:8px;color:#222">Week <strong><?php echo htmlspecialchars($nextWeekNumber); ?></strong></div>
                <input type="hidden" name="week_coverage" id="week_coverage" value="<?php echo htmlspecialchars('Week ' . $nextWeekNumber); ?>">
                <label class="small">From</label>
                <input name="week_from" id="week_from" type="date" value="<?php echo htmlspecialchars($defaultFrom); ?>" <?php if(!empty($minSelectable)) echo 'min="'.htmlspecialchars($minSelectable).'"'; ?> max="<?php echo date('Y-m-d'); ?>">
                <label class="small">To</label>
                <input name="week_to" id="week_to" type="date" value="<?php echo htmlspecialchars($defaultTo); ?>" <?php if(!empty($minSelectable)) echo 'min="'.htmlspecialchars($minSelectable).'"'; ?> max="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="toolbar" role="toolbar">
                <button type="button" data-cmd="bold">B</button>
                <button type="button" data-cmd="italic">I</button>
                <button type="button" data-cmd="underline">U</button>
                
                <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 8px;border:1px solid #e6e9f2;border-radius:4px;cursor:pointer">
                    Insert image <input id="imgfile" type="file" accept="image/*" style="display:none">
                </label>
            </div>

            <div id="editor" contenteditable="true" aria-label="Journal editor"><?php echo $defaultTemplateHtml; ?></div>

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
                if (imgLabel) {
                    // save selection before file chooser opens; use mousedown/touchstart to capture caret
                    imgLabel.addEventListener('mousedown', function(e){ saveSelection(); });
                    imgLabel.addEventListener('touchstart', function(e){ saveSelection(); });
                }
            } catch(e) {}

            // track selection updates inside the editor
            var edWatch = document.getElementById('editor');
            if (edWatch) {
                // delegate editable area events: listen on editor container and handle events from .page-content
                ['keyup','mouseup','focus','input','paste'].forEach(function(ev){ edWatch.addEventListener(ev, function(e){ saveSelection(); ensurePagesDebounced(); }); });
            }

            visibleImg.addEventListener('change', function(e){
                var f = this.files && this.files[0];
                if (!f) return;
                if (!f.type.match('image.*')) { alert('Please choose an image file.'); this.value = ''; return; }
                var reader = new FileReader();
                reader.onload = function(ev){
                    var src = ev.target.result;
                    var ed = document.getElementById('editor');
                    try {
                        // create image element
                        var img = document.createElement('img');
                        img.src = src;
                        img.className = 'inserted-image';
                        img.setAttribute('draggable', 'true');

                        // add dragstart handler to mark dragged element
                        img.addEventListener('dragstart', function(ev){
                            try { window._draggedImage = this; ev.dataTransfer.setData('text/plain',''); } catch(e){}
                        });

                        // no click toolbar — images stay inline and keep original size

                        // restore selection and insert at saved caret (or current selection)
                        var had = restoreSelection();
                        if (ed) {
                            ed.focus();
                            var sel = window.getSelection();
                            try {
                                var range = null;
                                if (had && savedRange) {
                                    range = savedRange.cloneRange();
                                } else if (sel && sel.rangeCount) {
                                    range = sel.getRangeAt(0).cloneRange();
                                }

                                function closestPageContent(node){
                                    while(node && node !== document){
                                        if (node.nodeType === 1 && node.classList && node.classList.contains('page-content')) return node;
                                        node = node.parentNode;
                                    }
                                    return null;
                                }

                                if (range) {
                                    range.collapse(false);
                                    range.insertNode(img);
                                    // move caret after image
                                    var after = document.createRange();
                                    after.setStartAfter(img);
                                    after.collapse(true);
                                    sel.removeAllRanges();
                                    sel.addRange(after);
                                } else {
                                    // try to insert into the currently focused page-content if possible
                                    var focusNode = (sel && sel.rangeCount) ? sel.getRangeAt(0).startContainer : (savedRange ? savedRange.startContainer : null);
                                    var targetPage = closestPageContent(focusNode) || closestPageContent(ed) || ed.querySelector('.page-content');
                                    if (targetPage) {
                                        // insert at caret-like position: append but try to keep proximity
                                        targetPage.appendChild(img);
                                        // place caret after image
                                        var rng = document.createRange(); rng.setStartAfter(img); rng.collapse(true);
                                        sel.removeAllRanges(); sel.addRange(rng);
                                    } else {
                                        // final fallback: append to editor
                                        ed.appendChild(img);
                                    }
                                }

                                // defer pagination adjustments slightly so the inserted image stays
                                // at the intended location before page-splitting runs
                                setTimeout(function(){ try{ if (typeof ensurePages === 'function') ensurePages(); }catch(e){} }, 120);
                            } catch(e) {
                                ed.appendChild(img);
                            }
                        }
                    } catch (ex) {
                        console.error(ex);
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

            // enable drag-and-drop repositioning of images inside the editor
            if (editorNode) {
                editorNode.addEventListener('dragover', function(ev){ ev.preventDefault(); });
                editorNode.addEventListener('drop', function(ev){
                    ev.preventDefault();
                    try {
                        var dragged = window._draggedImage;
                        if (!dragged) return;
                        // determine drop range
                        var range = null;
                        if (document.caretRangeFromPoint) {
                            range = document.caretRangeFromPoint(ev.clientX, ev.clientY);
                        } else if (document.caretPositionFromPoint) {
                            var pos = document.caretPositionFromPoint(ev.clientX, ev.clientY);
                            range = document.createRange();
                            range.setStart(pos.offsetNode, pos.offset);
                            range.collapse(true);
                        }
                        if (range) {
                            // insert dragged image at drop point
                            range.insertNode(dragged);
                        }
                        // clear dragged ref
                        window._draggedImage = null;
                        // ensure pagination after drop
                        ensurePages();
                    } catch(e) { console.error(e); }
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

        function getWeekdayIndex(dateStr) {
            if (!dateStr) return -1;
            return new Date(dateStr + 'T00:00:00').getDay(); // 0=Sun..6=Sat
        }

        function getSameWeekFriday(dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr + 'T00:00:00');
            var day = d.getDay();
            var diffToFriday = 5 - day;
            d.setDate(d.getDate() + diffToFriday);
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
            // ensure To is always strictly after From and never beyond Friday of same week
            // expose server-side DTR dates and per-date hours to client
            var userDtrMap = <?php echo json_encode(isset($dtrHours) ? $dtrHours : []); ?> || {};
            var userDtrDates = <?php echo json_encode($allDtrDates); ?> || [];

            function formatPretty(dateStr){
                try { var d = new Date(dateStr + 'T00:00:00'); return d.toLocaleDateString('en-US', { month:'long', day:'numeric', year:'numeric' }); } catch(e) { return dateStr; }
            }

            function buildRowsFromDateList(dates){
                var out = '';
                var total = 0;
                for (var i=0;i<dates.length;i++){
                    var d = dates[i];
                    var weekday = new Date(d + 'T00:00:00').toLocaleDateString('en-US',{ weekday: 'long' });
                    var hrs = (typeof userDtrMap[d] !== 'undefined') ? parseFloat(userDtrMap[d]) : '';
                    var hrsDisplay = '';
                    if (hrs !== '') { hrsDisplay = String(Math.floor(hrs)); total += Math.floor(hrs); }
                    out += '<tr><td>'+weekday+'<br>'+formatPretty(d)+'</td><td class="editable-cell" contenteditable="true"></td><td class="editable-cell" contenteditable="true" style="text-align:center;">'+ (hrsDisplay || '') +'</td></tr>';
                }
                // total hours row (id used for updates)
                out += '<tr><td colspan="2" style="text-align:right;">Total Hours:</td><td id="total-hours-cell" class="editable-cell" contenteditable="true" style="text-align:center;">'+ (total ? (Math.round(total*100)/100) : '') +'</td></tr>';
                return out;
            }

            function datesBetweenWeekdays(from, to){
                var res = [];
                if (!from || !to) return res;
                var cur = new Date(from + 'T00:00:00');
                var end = new Date(to + 'T00:00:00');
                while (cur <= end){
                    var day = cur.getDay(); // 0..6
                    if (day !== 0 && day !== 6) res.push(cur.toISOString().slice(0,10));
                    cur.setDate(cur.getDate()+1);
                }
                return res;
            }

            function renderJournalRowsForRange(fromVal, toVal){
                var ed = document.getElementById('editor');
                if (!ed) return;
                var tbody = ed.querySelector('.journal-table tbody');
                if (!tbody) return;
                if (!fromVal) return;
                if (!toVal) toVal = getSameWeekFriday(fromVal);
                // find log dates from DTR within range
                var matched = userDtrDates.filter(function(x){ return x >= fromVal && x <= toVal; });
                if (matched.length === 0){
                    // fallback: use weekdays between from..to
                    matched = datesBetweenWeekdays(fromVal, toVal);
                }
                tbody.innerHTML = buildRowsFromDateList(matched);
            }

            function applyToConstraintsFromFromDate(fromVal) {
                if (!fromVal) {
                    inpTo.disabled = false;
                    return { minTo: '', maxTo: '' };
                }

                var fromDay = getWeekdayIndex(fromVal);
                if (fromDay === 0 || fromDay === 6) {
                    alert('Please select a weekday (Monday to Friday) for From date.');
                    inpFrom.value = '';
                    inpTo.value = '';
                    inpTo.disabled = false;
                    return { minTo: '', maxTo: '' };
                }

                var minTo = addDays(fromVal, 1); // disallow same day and earlier
                var maxTo = getSameWeekFriday(fromVal); // same-week Friday cap

                inpTo.min = minTo;
                inpTo.max = maxTo;

                // If From is Friday, there is no valid To date in the same week.
                if (minTo > maxTo) {
                    inpTo.value = '';
                    inpTo.disabled = true;
                    alert('From date is already Friday. Please select Monday to Thursday so To date can be selected within the same week.');
                } else {
                    inpTo.disabled = false;
                    if (!inpTo.value || inpTo.value < minTo || inpTo.value > maxTo) {
                        inpTo.value = minTo;
                    }
                }

                return { minTo: minTo, maxTo: maxTo };
            }

            inpFrom.addEventListener('change', function(){
                if (!this.value) return;
                try {
                    var limits = applyToConstraintsFromFromDate(this.value);
                    if (!limits.minTo || !limits.maxTo) return;
                } catch(e) {}
                // render rows based on selected range
                try { renderJournalRowsForRange(this.value, inpTo.value); } catch(e) {}
            });

            inpTo.addEventListener('change', function(){
                if (!this.value || !inpFrom.value) return;
                var minTo = addDays(inpFrom.value, 1);
                var maxTo = getSameWeekFriday(inpFrom.value);
                if (this.value < minTo || this.value > maxTo) {
                    alert('Invalid range: "To" must be after "From" and within the same week up to Friday.');
                    this.value = minTo <= maxTo ? minTo : '';
                }
                try { renderJournalRowsForRange(inpFrom.value, this.value); } catch(e) {}
            });

            // initial render on load
            try {
                if (inpFrom && inpFrom.value) {
                    applyToConstraintsFromFromDate(inpFrom.value);
                    renderJournalRowsForRange(inpFrom.value, inpTo.value);
                }
            } catch(e) {}
        }

        // submit: validate rows and copy content
        document.getElementById('frm').addEventListener('submit', function(e){
            var ed = document.getElementById('editor');
            try {
                // validate Work Description cells (2nd column) are non-empty
                var tbody = ed.querySelector('.journal-table tbody');
                if (tbody) {
                    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                    for (var i = 0; i < rows.length; i++) {
                        var r = rows[i];
                        var tds = r.querySelectorAll('td');
                        if (tds.length < 2) continue; // skip total or malformed rows
                        var first = tds[0];
                        if (first.getAttribute && first.getAttribute('colspan')) continue; // total row
                        var work = (tds[1].textContent || '').trim();
                        if (work === '') {
                            e.preventDefault();
                            var label = (first.textContent || '').replace(/\s+/g, ' ').trim();
                            alert('Please fill Work Description for: ' + (label || 'one of the rows'));
                            try { tds[1].focus(); } catch(err) {}
                            return false;
                        }
                    }
                }

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

                // copy current editor HTML into hidden field before submit
                var ch = document.getElementById('content_html');
                if (ch) ch.value = ed.innerHTML;

                // allow native submit
                return true;
            } catch (err) {
                e.preventDefault();
                console.error(err);
                alert('An unexpected error occurred. Please try again.');
                return false;
            }
        });
    </script>
</body>
</html>
