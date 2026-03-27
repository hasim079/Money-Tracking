<?php
// Session Timeout Configuration (e.g., 30 minutes = 1800 seconds)
$timeout_duration = 1800;

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    // Last request was more than 30 minutes ago
    session_unset();     // unset $_SESSION variable for the run-time 
    session_destroy();   // destroy session data in storage
    header("Location: auth.php?timeout=1");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time(); // update last activity time stamp
