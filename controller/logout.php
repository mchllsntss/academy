<?php
session_start();

// Remove all session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect to index.php
header("Location: ../pages/login.php");
exit;  // Always call exit after a header redirect to ensure no further code is executed
?>