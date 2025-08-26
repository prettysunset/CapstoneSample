<?php
require 'conn.php';

// kuha lahat ng rows sorted by time
$result = $conn->query("SELECT * FROM excel ORDER BY id ASC");

$rows = [];
while ($r = $result->fetch_assoc()){
    $rows[] = $r;
}

$days = ["monday","tuesday","wednesday","thursday","friday","saturday"];
$output = [];

foreach ($days as $day) {
    $j = 0;
    $count = count($rows);

    while ($j < $count) {
        $cell = trim($rows[$j][$day]);

        // check kung CL2 ang cell
        if (stripos($cell, "CL2") === false) {
            $j++;
            continue;
        }

        // hanapin SUBJECT pataas (unang non-empty na hindi CL2)
        $startIndex = $j;
        $subjectText = '';
        while ($startIndex >= 0) {
            $prevCell = trim($rows[$startIndex][$day]);
            if ($prevCell !== "" && stripos($prevCell, "CL2") === false) {
                $subjectText = $prevCell;
                $startTime = $rows[$startIndex]['time'];
                break;
            }
            $startIndex--;
        }

        if (empty($subjectText)) {
            $subjectText = "N/A";
            $startTime = $rows[$j]['time'];
        }

        // hanapin hanggang saan ang highlight (tignan ang color column)
        // ang pangalan ng color column ay <day>_color
        $colorCol = $day . "_color";
        $endIndex = $j;
        while ($endIndex + 1 < $count && !empty($rows[$endIndex + 1][$colorCol])) {
            $endIndex++;
        }

        // end time = last highlighted row + 30 minutes
        $endTime = date("H:i", strtotime($rows[$endIndex]['time'] . " +30 minutes"));

        // instructor (optional) - kung may laman sa next row na hindi CL2
        $instructor = '';
        if ($endIndex + 1 < $count) {
            $nextAfter = trim($rows[$endIndex + 1][$day]);
            if ($nextAfter !== "" && stripos($nextAfter, "CL2") === false) {
                $instructor = $nextAfter;
            }
        }

        $output[] = [
            'time' => $startTime . " - " . $endTime,
            'subject' => $subjectText,
            'room' => $cell,
            'instructor' => $instructor
        ];

        $j = $endIndex + 1;
    }
}
?>

<table border="1" cellpadding="8" cellspacing="0">
    <tr>
        <th>Time</th>
        <th>Subject</th>
        <th>Room</th>
        <th>Instructor</th>
    </tr>
    <?php foreach ($output as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['time']) ?></td>
        <td><?= htmlspecialchars($row['subject']) ?></td>
        <td><?= htmlspecialchars($row['room']) ?></td>
        <td><?= htmlspecialchars($row['instructor']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<a href="dashboard.php" style="padding:6px 12px; background-color:#4CAF50; color:white; text-decoration:none; border-radius:4px;">Back to Dashboard</a>
