<?php
session_start();

// remove only application-related session data
unset($_SESSION['af1'], $_SESSION['af2'], $_SESSION['student_id']);

// optional: regenerate session id to reduce fixation risk
if (function_exists('session_regenerate_id')) {
    session_regenerate_id(true);
}

// redirect back to the first form
header('Location: application_form1.php');
exit;