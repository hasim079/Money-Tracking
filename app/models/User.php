<?php
// User model to handle database interactions related to user accounts
class User {

    private $pdo;

    // Constructor injects PDO database connection dependency
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Find a user record by their matching email address
    public function findByEmail($email) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE email = ?"
        );
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Securely hash the password and insert a newly registered user into the database
    public function create($name, $email, $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->pdo->prepare(
            "INSERT INTO users (name, email, password) 
             VALUES (?, ?, ?)"
        );
        return $stmt->execute([$name, $email, $hashed]);
    }
}