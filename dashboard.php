<?php
// dashboard.php
require 'conn.php'; // include your database connection
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
                // First row = headers
                $headers = $uploadedData[0];
                $rows = array_slice($uploadedData, 1);

                // Clear existing records first to avoid duplicates
                $conn->query("TRUNCATE TABLE excel");

                foreach ($rows as $row) {
                    $time = $row[0];
                    $subject = $row[1];
                    $instructor = $row[2];
                    $room = $row[3];

                    // Insert into database
                    $stmt = $conn->prepare("INSERT INTO excel (time, subject, instructor, room) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $time, $subject, $instructor, $room);
                    $stmt->execute();
                }

                // Redirect to prevent form resubmission on refresh
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

// Fetch existing data from database
$result = $conn->query("SELECT * FROM excel");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Excel Upload</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: center;
        }
        th[contenteditable="true"] {
            background-color: #f0f8ff;
        }
    </style>
</head>
<body>
    <h2>Upload Excel File</h2>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="excel_file" accept=".xls,.xlsx" required>
        <button type="submit" name="submit">Upload & Save</button>
    </form>

    <h3>Table Data:</h3>
    <table id="dataTable">
        <thead>
            <tr>
                <?php foreach ($headers as $header): ?>
                    <th contenteditable="true"><?php echo htmlspecialchars($header); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['time']); ?></td>
                        <td><?php echo htmlspecialchars($row['subject']); ?></td>
                        <td><?php echo htmlspecialchars($row['instructor']); ?></td>
                        <td><?php echo htmlspecialchars($row['room']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo count($headers); ?>">No data available</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>
