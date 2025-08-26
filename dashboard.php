<?php
require 'conn.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// Initialize table headers
$headers = ["Time", "Subject", "Instructor", "Room"];

// Handle Excel upload
if (isset($_POST['submit'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $filePath = $_FILES['excel_file']['tmp_name'];
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $uploadedData = $sheet->toArray();

            if (!empty($uploadedData)) {
                $headers = $uploadedData[0];
                $rows = array_slice($uploadedData, 1);

                // Clear old data
                $conn->query("TRUNCATE TABLE excel");

                foreach ($rows as $row) {
                    $time = $row[0];
                    $subject = $row[1];
                    $instructor = $row[2];
                    $room = $row[3];

                    $stmt = $conn->prepare("INSERT INTO excel (time, subject, instructor, room) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $time, $subject, $instructor, $room);
                    $stmt->execute();
                }

                header("Location: dashboard.php");
                exit;
            }
        } catch (Exception $e) {
            echo "<p style='color:red;'>Error reading Excel file: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:red;'>Please upload a valid Excel file.</p>";
    }
}

// Handle AJAX Save/Update
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'save') {
        $time = $_POST['time'];
        $subject = $_POST['subject'];
        $instructor = $_POST['instructor'];
        $room = $_POST['room'];
        $id = $_POST['id'];

        if ($id == 0) { // new row
            $stmt = $conn->prepare("INSERT INTO excel (time, subject, instructor, room) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $time, $subject, $instructor, $room);
            $stmt->execute();
            echo $conn->insert_id;
        } else { // existing row
            $stmt = $conn->prepare("UPDATE excel SET time=?, subject=?, instructor=?, room=? WHERE id=?");
            $stmt->bind_param("ssssi", $time, $subject, $instructor, $room, $id);
            $stmt->execute();
            echo "updated";
        }
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM excel WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "success";
        exit;
    }
}

// Fetch existing data
$result = $conn->query("SELECT * FROM excel");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard - Excel Upload</title>
<style>
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
table, th, td { border: 1px solid black; padding: 8px; text-align: center; }
th { background-color: #f0f8ff; }
button { padding: 4px 8px; margin: 2px; }
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<h2>Upload Excel File</h2>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="excel_file" accept=".xls,.xlsx" required>
    <button type="submit" name="submit">Upload & Save</button>
</form>

<h3>Table Data:</h3>
<button onclick="addRow()">Add Row</button>
<table id="dataTable">
<thead>
    <tr>
        <?php foreach ($headers as $header): ?>
            <th><?php echo htmlspecialchars($header); ?></th>
        <?php endforeach; ?>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
<?php if ($result && $result->num_rows > 0): ?>
    <?php while($row = $result->fetch_assoc()): ?>
        <tr data-id="<?php echo $row['id']; ?>">
            <td contenteditable="true" class="editable" data-column="time"><?php echo htmlspecialchars($row['time']); ?></td>
            <td contenteditable="true" class="editable" data-column="subject"><?php echo htmlspecialchars($row['subject']); ?></td>
            <td contenteditable="true" class="editable" data-column="instructor"><?php echo htmlspecialchars($row['instructor']); ?></td>
            <td contenteditable="true" class="editable" data-column="room"><?php echo htmlspecialchars($row['room']); ?></td>
            <td>
                <button class="saveBtn">Save</button>
                <button class="deleteBtn">Delete</button>
            </td>
        </tr>
    <?php endwhile; ?>
<?php else: ?>
<tr>
    <td colspan="<?php echo count($headers)+1; ?>">No data available</td>
</tr>
<?php endif; ?>
</tbody>
</table>

<script>
let headers = <?php echo json_encode($headers); ?>;

function addRow() {
    let table = document.getElementById('dataTable').getElementsByTagName('tbody')[0];
    let row = table.insertRow();
    row.setAttribute('data-id', 0);

    headers.forEach(header => {
        let cell = row.insertCell();
        cell.contentEditable = "true";
        cell.className = "editable";
        cell.setAttribute("data-column", header.toLowerCase());
        cell.innerText = "";
    });

    let actionCell = row.insertCell();
    let saveBtn = document.createElement('button');
    saveBtn.innerText = "Save";
    saveBtn.className = "saveBtn";
    let delBtn = document.createElement('button');
    delBtn.innerText = "Delete";
    delBtn.className = "deleteBtn";
    actionCell.appendChild(saveBtn);
    actionCell.appendChild(delBtn);
}

$(document).ready(function(){
    // Save row (both new and existing)
    $(document).on('click', '.saveBtn', function(){
        let tr = $(this).closest('tr');
        let id = tr.data('id');
        let time = tr.find('td[data-column="time"]').text();
        let subject = tr.find('td[data-column="subject"]').text();
        let instructor = tr.find('td[data-column="instructor"]').text();
        let room = tr.find('td[data-column="room"]').text();

        $.post('dashboard.php', {action:'save', id:id, time:time, subject:subject, instructor:instructor, room:room}, function(response){
            if(id == 0) {
                tr.attr('data-id', response); // assign new ID
                alert('Row added!');
            } else {
                alert('Row updated!');
            }
        });
    });

    // Delete row
    $(document).on('click', '.deleteBtn', function(){
        if(!confirm('Are you sure you want to delete this row?')) return;
        let tr = $(this).closest('tr');
        let id = tr.data('id');
        if(id == 0) {
            tr.remove(); // unsaved row
            return;
        }
        $.post('dashboard.php', {action:'delete', id:id}, function(response){
            if(response === 'success') tr.remove();
        });
    });
});
</script>

</body>
</html>
