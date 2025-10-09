<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $to = $_POST['email']; // email ng mag fill-up
    $subject = "Form Submission Confirmation";
    $message = "Hi " . htmlspecialchars($_POST['name']) . ",\n\nThank you for your submission!\n\nMessage: " . htmlspecialchars($_POST['message']);
    $headers = "From: noreply@yourdomain.com";

    if (mail($to, $subject, $message, $headers)) {
        echo "Email sent successfully!";
    } else {
        echo "Failed to send email.";
    }
}
?>