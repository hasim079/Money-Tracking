<?php 
// Establish a Secure PDO Connection to the local MySQL database
$host="localhost";
$dbname="para_takip";
$username="hasimmoh";
$password="";

try {
    // Attempt connecting and configure PDO to throw exceptions on database errors
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}