<?php
// ==========================
// CONFIGURATION GLOBALE
// ==========================

// Nom du site et titre par défaut
define('__SITE_NAME__', 'MyApp');
define('__SITE_TITLE__', 'My Application - Gestion interne');

// Icônes et favicon
define('__SITE_ICON__', '/images/logo.png');
define('__SITE_FAVICON__', '/images/favicon.png');

// Préfixe des tables SQL
define('__DB_PREFIX__', 'app_');

// ==========================
// BASE DE DONNÉES
// ==========================
define('__DB_HOST__', 'localhost');
define('__DB_PORT__', 3306);
define('__DB_NAME__', 'sysgest');
define('__DB_USER__', 'root');
define('__DB_PASS__', '');
// ==========================
// MAIL
// ==========================
// Type d’envoi : "mail" (fonction PHP mail) ou "smtp"
define('__MAIL_TYPE__', 'smtp');

// Adresse par défaut de l’expéditeur
define('__MAIL_FROM__', 'no-reply@sysgest.com');
define('__MAIL_FROM_NAME__', 'Support Sysgest');

// Config SMTP (utilisé seulement si __MAIL_TYPE__ = 'smtp')
// Exemple Gmail :
define('__SMTP_HOST__', 'smtp.gmail.com');
define('__SMTP_PORT__', 587);
define('__SMTP_USER__', 'email@gmail.com');
define('__SMTP_PASS__', 'pwd');
define('__SMTP_SECURE__', 'tls'); 

// ==========================
// AUTRES OPTIONS
// ==========================
// Mode debug (true = erreurs affichées, false = production)
define('__DEBUG__', true);

// URL racine du site
define('__BASE_URL__', 'http://localhost/sysgest/');
