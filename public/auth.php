<?php
// Initialize session and handle language selection logic
session_start();

if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}

// Fallback to English if no language is established
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

$current_lang = $_SESSION['lang'];
// Set text direction (RTL for Arabic, LTR for others)
$dir = ($current_lang == "ar") ? "rtl" : "ltr";

// Load necessary language translation and database dependencies
$lang = require "../lang/" . $current_lang . ".php";
require_once "../config/database.php";
require_once "../app/controllers/AuthController.php";

$auth = new AuthController($pdo);
$message = "";

// Process Login or Registration attempt via POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Handle New User Registration
    if ($_POST["action"] == "register") {
        $message = $auth->register(
            $_POST["name"],
            $_POST["email"],
            $_POST["password"]
        );
    }

    // Handle User Login
    if ($_POST["action"] == "login") {
        $result = $auth->login(
            $_POST["email"],
            $_POST["password"]
        );

        if ($result === true) {
            header("Location: dashboard.php");
            exit();
        } else {
            $message = $result;
        }
    }
}

// Load the authentication view interface
require_once "../views/auth.view.php";