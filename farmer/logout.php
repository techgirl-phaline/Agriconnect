
<?php
session_start();

// Remove all session variables
$_SESSION = [];

// Destroy the current session
session_destroy();

// Redirect the farmer to the login page
header("Location: login.php?logged_out=success");
exit;
?>
