<?php
require_once __DIR__ . "/../manager/config/config.php";

$host     = __DB_HOST__;
$port     = __DB_PORT__;
$dbname   = __DB_NAME__;
$user     = __DB_USER__;
$password = __DB_PASS__;

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

try {
    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/**
 * Retourne un nom de table avec le préfixe défini dans config.php
 * Exemple : table_name('users') => "app_users"
 */
function table_name(string $name): string {
    return __DB_PREFIX__ . $name;
}
