<?php
// ---------- DATABASE CONNECTION ----------
$conn = new mysqli("localhost", "root", "", "capstone");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Create table if not exists (auto)
$conn->query("CREATE TABLE IF NOT EXISTS intern_stories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  course VARCHAR(150),
  message TEXT,
  image VARCHAR(255)
)");

// Default data if empty
$check = $conn->query("SELECT COUNT(*) AS total FROM intern_stories")->fetch_assoc();
if ($check['total'] == 0) {
  $conn->query("INSERT INTO intern_stories (name, course, message, image)
    VALUES ('Santiago, Jasmine M.', 'BS in Information Technology, BulSU',
    'My OJT at Malolos City Hall gave me the chance to apply what I learned in school to actual office tasks. I became more confident in handling clerical work and assisting people.',
    'upload/default.png')");
}

// ---------- UPDATE STORY ----------
if (isset($_POST['update'])) {
  $id = $_POST['id'];
  $name = $_POST['name'];
  $course = $_POST['course'];
  $message = $_POST['message'];

  // Upload image
  if (!empty($_FILES['image']['name'])) {
    if (!file_exists("upload")) mkdir("upload");
    $fileName = time() . "_" . basename($_FILES["image"]["name"]);
    $targetFile = "upload/" . $fileName;
    move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile);
    $imagePath = $targetFile;
  } else {
    $imagePath = $_POST['old_image'];
  }

  $sql = "UPDATE intern_stories 
          SET name='$name', course='$course', message='$message', image='$imagePath'
          WHERE id=$id";
  $conn->query($sql);
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// ---------- FETCH STORY ----------
$story = $conn->query("SELECT * FROM intern_stories ORDER BY id DESC LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Intern Stories</title>
  <style>
    body { font-family: Arial, sans-serif; background:#fff; margin:0; padding:20px; text-align:center; }
    h1 { color:#333; }
    span.highlight { color:#3366ff; }
    .story { border:1px solid #ccc; border-radius:10px; width:360px; margin:20px auto; padding:15px; text-align:left; }
    .story img { width:100%; height:220px; object-fit:cover; border-radius:8px; }
    .story h3 { margin:10px 0 0; }
    .story p { font-size:14px; color:#333; }
    .edit-btn { margin-top:10px; padding:8px 12px; background:#3366ff; color:white; border:none; border-radius:5px; cursor:pointer; }
    .form-container { display:none; margin-top:20px; text-align:left; width:360px; margin:auto; border:1px solid #ccc; border-radius:10px; padding:15px; }
    input, textarea { width:100%; padding:8px; margin:5px 0 10px; border:1px solid #ccc; border-radius:5px; }
    button.save-btn { background:#3366ff; color:white; border:none; padding:10px; border-radius:5px; width:100%; cursor:pointer; }
  </style>
</head>
<body>

<h1>Stories from Our <span class="highlight">Interns</span></h1>

<div class="story">
  <img src="<?= htmlspecialchars($story['image']) ?>" alt="Intern Photo">
  <h3><?= htmlspecialchars($story['name']) ?></h3>
  <p><b><?= htmlspecialchars($story['course']) ?></b></p>
  <p><?= nl2br(htmlspecialchars($story['message'])) ?></p>
  <button class="edit-btn" onclick="document.querySelector('.form-container').style.display='block'">⚙️ Edit Story (HR)</button>
</div>

<div class="form-container">
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $story['id'] ?>">
    <input type="hidden" name="old_image" value="<?= htmlspecialchars($story['image']) ?>">

    <label>Name:</label>
    <input type="text" name="name" value="<?= htmlspecialchars($story['name']) ?>" required>

    <label>Course:</label>
    <input type="text" name="course" value="<?= htmlspecialchars($story['course']) ?>" required>

    <label>Message:</label>
    <textarea name="message" rows="4" required><?= htmlspecialchars($story['message']) ?></textarea>

    <label>Change Photo:</label>
    <input type="file" name="image" accept="image/*">

    <button type="submit" name="update" class="save-btn">💾 Save Changes</button>
  </form>
</div>

</body>
</html>
