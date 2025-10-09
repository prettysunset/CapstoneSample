<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST['name']);
    $student_email = htmlspecialchars($_POST['email']);
    $course = htmlspecialchars($_POST['course']);

    // Send email gamit PHPMailer
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sample.mail00000000@gmail.com'; 
        $mail->Password   = 'qitthwgfhtogjczq'; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Debug para makita error
        //$mail->SMTPDebug = 2;
        $mail->Debugoutput = 'html';

        $mail->setFrom('sample.mail00000000@gmail.com', 'OJTMS Registration');
        $mail->addAddress($student_email, $name);

        $mail->isHTML(true);
        $mail->Subject = "Registration Received - Pending Approval";
        $mail->Body    = "
            Hi <b>$name</b>,<br><br>
            Thank you for registering for the OJT program.<br>
            Your registration is now <b>pending approval</b> by the HR Department.<br><br>
            <b>Course:</b> $course <br><br>
            You will receive another email once your application is reviewed.<br><br>
            - OJTMS Team
        ";

        $mail->send();
        echo "✅ Registration complete! Email sent to <b>$student_email</b>";
    } catch (Exception $e) {
        echo "❌ Email could not be sent. Error: {$mail->ErrorInfo}";
    }
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Student Registration</title>
    </head>
    <body>
      <h2>OJT Student Registration</h2>
      <form action="" method="POST">
        <label for="name">Full Name:</label><br>
        <input type="text" id="name" name="name" required><br><br>

        <label for="email">Email Address:</label><br>
        <input type="email" id="email" name="email" required><br><br>

        <label for="course">Course:</label><br>
        <input type="text" id="course" name="course" required><br><br>

        <button type="submit">Register</button>
      </form>
    </body>
    </html>
    <?php
}
?>
