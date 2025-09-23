<?php
session_start();
require __DIR__ . '/../includes/database.php'; 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validation simple
    if (empty($email) || empty($password)) {
        $_SESSION['loginError'] = "Email et mot de passe sont requis !";
        header("Location: ../html/login.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, custom_id, name, email, password, role_id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['custom_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role_id'] = $user['role_id'];

        unset($_SESSION['loginError']); 

        header("Location: ../dashboard.php");
        exit;
    } else {
        $_SESSION['loginError'] = "Identifiants invalides !";
        header("Location: ../html/login.php");
        exit;
    }
} else {
    header("Location: ../html/login.php");
    exit;
}
