<?php
// quick helper: compare remote and local ojt_applications
$local = @new mysqli('127.0.0.1', 'root', '', 'u389936701_capstone');
if (!$local || $local->connect_errno) {
    echo json_encode(['ok'=>false,'error'=>'local_connect_failed','msg'=>($local? $local->connect_error : '')]);
    exit;
}
$local->set_charset('utf8mb4');

$remote = @new mysqli('auth-db2090.hstgr.io', 'u389936701_user', 'CapstoneDefended1', 'u389936701_capstone', 3306);
if (!$remote || $remote->connect_errno) {
    echo json_encode(['ok'=>false,'error'=>'remote_connect_failed','msg'=>($remote? $remote->connect_error : '')]);
    exit;
}
$remote->set_charset('utf8mb4');

$rows = $remote->query("SELECT application_id, student_id, office_preference1, office_preference2, status, date_submitted FROM ojt_applications ORDER BY application_id DESC");
if (!$rows) {
    echo json_encode(['ok'=>false,'error'=>'remote_query_failed','msg'=>$remote->error]);
    exit;
}
$remoteIds = [];
$missing = [];
while ($r = $rows->fetch_assoc()) {
    $aid = isset($r['application_id']) ? (int)$r['application_id'] : null;
    if ($aid === null) continue;
    $remoteIds[] = $aid;
    $chk = $local->prepare('SELECT application_id FROM ojt_applications WHERE application_id = ? LIMIT 1');
    if ($chk) {
        $chk->bind_param('i', $aid);
        $chk->execute();
        $res = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$res) $missing[] = $aid;
    } else {
        // if prepare failed, bail
        echo json_encode(['ok'=>false,'error'=>'local_prepare_failed','msg'=>$local->error]);
        exit;
    }
}

$out = ['ok'=>true,'remote_total'=>count($remoteIds),'missing_count'=>count($missing),'missing_ids'=>$missing];
if (count($missing) > 0) {
    $first = (int)$missing[0];
    $r = $remote->prepare('SELECT * FROM ojt_applications WHERE application_id = ? LIMIT 1');
    if ($r) { $r->bind_param('i',$first); $r->execute(); $detail = $r->get_result()->fetch_assoc(); $r->close(); $out['sample_missing_remote_row']=$detail; }
}

echo json_encode($out, JSON_PRETTY_PRINT);
$remote->close(); $local->close();
