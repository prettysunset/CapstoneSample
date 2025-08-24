<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

// Connect to DB
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $time_slot = $_POST['time_slot'];
    $cys = $_POST['course_year_section'];
    $subject = $_POST['subject'];
    $instructor = $_POST['instructor'];

    $sql_insert = "INSERT INTO schedules (time_slot, course_year_section, subject, instructor) 
                   VALUES ('$time_slot', '$cys', '$subject', '$instructor')";
    $conn->query($sql_insert);
}

// Fetch schedules sorted by time
$sql = "SELECT * FROM schedules ORDER BY STR_TO_DATE(time_slot, '%H:%i') ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Schedule</title>
<style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
    .top-bar {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 20px;
    }
    button {
        padding: 8px 15px;
        background: #4CAF50;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    table {
        width: 80%;
        margin: auto;
        border-collapse: collapse;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: center;
    }
    th {
        background: #4CAF50;
        color: white;
    }
    /* Popup form styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    .modal-content {
        background: white;
        padding: 20px;
        margin: 10% auto;
        width: 300px;
        border-radius: 8px;
    }
    input[type=text] {
        width: 100%;
        padding: 8px;
        margin: 5px 0 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .close {
        float: right;
        font-size: 18px;
        cursor: pointer;
    }
</style>
</head>
<body>

<div class="top-bar">
    <button onclick="document.getElementById('modal').style.display='block'">Create</button>
</div>

<table>
    <tr>
        <th>Time</th>
        <th>Course Year & Section</th>
        <th>Subject</th>
        <th>Instructor</th>
    </tr>
    <?php
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>".$row["time_slot"]."</td>
                    <td>".$row["course_year_section"]."</td>
                    <td>".$row["subject"]."</td>
                    <td>".$row["instructor"]."</td>
                  </tr>";
        }
    } else {
        echo "<tr><td colspan='4'>No schedules yet</td></tr>";
    }
    ?>
</table>

<!-- Modal Form -->
<div id="modal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('modal').style.display='none'">&times;</span>
        <h3>Add Schedule</h3>
        <form method="POST">
            <label>Time Slot</label>
            <input type="text" name="time_slot" placeholder="07:00 or 07:00-08:00" required>
            <label>Course Year & Section</label>
            <input type="text" name="course_year_section" required>
            <label>Subject</label>
            <input type="text" name="subject" required>
            <label>Instructor</label>
            <input type="text" name="instructor" required>
            <button type="submit">Save</button>
        </form>
    </div>
</div>

<script>
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target == document.getElementById('modal')) {
            document.getElementById('modal').style.display = "none";
        }
    }
</script>

</body>
</html>
<?php $conn->close(); ?>
