<?php
// Clear and completely destroy the active user session
session_start();
session_unset();
session_destroy();

// Redirect back to the auth/login portal
header("Location: auth.php");
exit();