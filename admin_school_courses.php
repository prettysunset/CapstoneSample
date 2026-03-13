<?php
// Simple admin tool: add schools and their courses quickly.
// Place this file in the project and open in browser. Requires existing `conn.php`.
session_start();
require_once __DIR__ . '/conn.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school = trim($_POST['school_name'] ?? '');
    $offers5 = isset($_POST['offers_5th_year']) ? 1 : 0;
    $raw = $_POST['courses'] ?? '';

    if ($school === '') {
        $msg = 'Please provide a school name.';
    } else {
        $conn->begin_transaction();
        try {
            // find or create school (case-insensitive)
            $s = $conn->prepare("SELECT school_id FROM schools WHERE LOWER(school_name) = LOWER(?) LIMIT 1");
            $s->bind_param('s', $school);
            $s->execute();
            $s->store_result();
            $school_id = null;
            if ($s->num_rows > 0) {
                $s->bind_result($school_id);
                $s->fetch();
            }
            $s->close();

            if (!$school_id) {
                $i = $conn->prepare("INSERT INTO schools (school_name) VALUES (?)");
                $i->bind_param('s', $school);
                $i->execute();
                $school_id = $conn->insert_id;
                $i->close();
            }

            // store school's offers_5th_year flag on the schools table
            $updateSchoolOffers = $conn->prepare("UPDATE schools SET offers_5th_year = ? WHERE school_id = ?");
            $updateSchoolOffers->bind_param('ii', $offers5, $school_id);
            $updateSchoolOffers->execute();
            $updateSchoolOffers->close();

            $lines = preg_split('/\r?\n|,/', $raw);
            $inserted = 0;
            $findCourse = $conn->prepare("SELECT course_id FROM courses WHERE LOWER(course_name) = LOWER(?) LIMIT 1");
            $insStmt = $conn->prepare("INSERT INTO school_courses (school_id, course_id, course_name) VALUES (?, ?, ?)");

            foreach ($lines as $rawCourse) {
                $c = trim($rawCourse);
                if ($c === '') continue;

                // try to resolve course_id from courses table
                $findCourse->bind_param('s', $c);
                $findCourse->execute();
                $findCourse->store_result();
                $course_id = null;
                if ($findCourse->num_rows > 0) {
                    $findCourse->bind_result($course_id);
                    $findCourse->fetch();
                }

                // always insert a new mapping regardless of existing rows
                $courseNameForInsert = $course_id ? null : $c;
                $cidParam = $course_id ?: null;
                $insStmt->bind_param('iis', $school_id, $cidParam, $courseNameForInsert);
                $insStmt->execute();
                $inserted++;
            }

            $findCourse->close();
            $insStmt->close();

            $conn->commit();
            $msg = "Done. Inserted: $inserted.";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin: Schools & Courses</title>
  <style>body{font-family:Arial,Helvetica,sans-serif;max-width:900px;margin:24px}textarea{width:100%;height:140px}</style>
</head>
<body>
  <h2>Quick add schools and courses</h2>
  <?php if ($msg): ?><p><strong><?php echo htmlspecialchars($msg); ?></strong></p><?php endif; ?>
  <form method="post">
    <label>School name<br>
      <input type="text" name="school_name" required style="width:100%" value="<?php echo htmlspecialchars($_POST['school_name'] ?? ''); ?>">
    </label>
    <p>
      <label><input type="checkbox" name="offers_5th_year" <?php if (!empty($_POST['offers_5th_year'])) echo 'checked'; ?>> Offers 5th Year level (apply to all listed courses)</label>
    </p>
    <label>Courses (one per line or comma-separated)<br>
      <textarea name="courses" placeholder="e.g. Bachelor of Science in Information Technology\nComputer Science"><?php echo htmlspecialchars($_POST['courses'] ?? ''); ?></textarea>
    </label>
    <p><button type="submit">Save</button></p>
  </form>

  <hr>
  <p>Notes: this tool will try to match course names to your existing `courses` table (case-insensitive). If a match is found the mapping will store `course_id`; otherwise it stores the free-text name in `course_name` so you can add it later to `courses` and update the mapping.</p>
</body>
</html>
