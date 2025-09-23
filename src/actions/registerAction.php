<?php
session_start();
require __DIR__ . '/../includes/database.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $_SESSION['registerError'] = "Tous les champs sont obligatoires !";
        header("Location: ../html/register.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['registerError'] = "Email invalide !";
        header("Location: ../html/register.php");
        exit;
    }

    
    $statement = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $statement->execute([':email' => $email]);

    if ($statement->fetch()) {
        $_SESSION['registerError'] = "Cet email est déjà utilisé !";
        header("Location: ../html/register.php");
        exit;
    }

    
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    
    $statement = $pdo->prepare("
        INSERT INTO users (name, email, password, role_id)
        VALUES (:name, :email, :password, :role_id)
    ");

    try {
        $statement->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $hashed_password,
            ':role_id' => $role,
        ]);

        $lastInsertId = $pdo->lastInsertId();
        $customId = 'USR' . str_pad($lastInsertId, 5, '0', STR_PAD_LEFT);

        $updateStmt = $pdo->prepare("UPDATE users SET custom_id = :custom_id WHERE id = :id");
        $updateStmt->execute([
            ':custom_id' => $customId,
            ':id' => $lastInsertId
        ]);

        // $_SESSION['registerSuccess'] = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
        header("Location: ../html/login.php");
        exit;

    } catch (PDOException $e) {
        $_SESSION['registerError'] = "Erreur lors de l'inscription : " . $e->getMessage();
        header("Location: ../html/register.php");
        exit;
    }
} else {
    header("Location: ../html/register.php");
    exit;
}
