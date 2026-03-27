<?php
require_once "../app/models/User.php";

class AuthController {

    private $pdo;
    private $userModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
    }

    public function register($name, $email, $password) {

        if (empty($name) || empty($email) || empty($password)) {
            return "All fields are required!";
        }

        if ($this->userModel->findByEmail($email)) {
            return "Email already exists!";
        }

        try {
            $this->userModel->create($name, $email, $password);
            return "Account created successfully!";
        } catch (PDOException $e) {
            return "Email already exists!";
        }
    }

    public function login($email, $password) {

        $user = $this->userModel->findByEmail($email);

        if ($user && password_verify($password, $user["password"])) {

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["role"] = $user["role"];

            return true;
        }

        return "Invalid email or password!";
    }

}