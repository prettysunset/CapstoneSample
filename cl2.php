<?php
require 'conn.php';

// Fetch schedule only for CL2
$result = $conn->query("SELECT * FROM excel WHERE room='CL2'");
?>

<table border="1">
<thead>
<tr>
    <th>Time</th>
    <th>Subject</th>
    <th>Instructor</th>
</tr>
</thead>
<tbody>
<?php
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        echo "<tr>";
        echo "<td>".htmlspecialchars($row['time'])."</td>";
        echo "<td>".htmlspecialchars($row['subject'])."</td>";
        echo "<td>".htmlspecialchars($row['instructor'])."</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='3'>No schedule for this room.</td></tr>";
}
?>
</tbody>
</table>
<a href="dashboard.php" style="padding:6px 12px; background-color:#2196F3; color:white; text-decoration:none; border-radius:4px;">Back to Dashboard</a>
