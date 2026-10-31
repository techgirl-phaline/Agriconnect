
<?php
session_start();

// Remove all session variables.
$_SESSION = [];

// Destroy the current session.
session_destroy();

// Redirect administrator to the login page.
header("Location: login.php?logout=success");
exit;
?>
