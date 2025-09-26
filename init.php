<?php
// ==========================
// AUTOLOAD GLOBAL
// ==========================
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Charger la config principale
require_once __DIR__ . "/manager/config/config.php";

// Charger la connexion à la base
require_once __DIR__ . "/includes/database.php";

// Charger les fonctions 
require_once __DIR__ . "/manager/config/functions.php";

// Charger le système d'alertes
require_once __DIR__ . "/manager/config/alerts.php";


require_once __DIR__ . "/manager/config/mailer.php";
