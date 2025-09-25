<?php
/**
 * sysgest/manager/config/alerts.php
 * Système centralisé d'alertes, logs et gestion d'erreurs/exceptions.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ========= Mode & chemins ========= */
$APP_DEBUG = defined('__DEBUG__') ? (bool)__DEBUG__ : false;
$BASE_URL  = defined('__BASE_URL__') ? __BASE_URL__ : '/';

$LOG_DIR   = __DIR__ . '/../../storage/logs';
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0775, true);
}
$LOG_FILE  = $LOG_DIR . '/app-' . date('Y-m-d') . '.log';

/* ========= PHP error_reporting selon debug ========= */
if ($APP_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

/* ========= Utils ========= */
function is_ajax_request(): bool {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return true;
    }
    // Ou via Accept: application/json
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($accept, 'application/json') !== false;
}

function app_log(string $level, string $message, array $context = []): void {
    global $LOG_FILE;
    $line = sprintf(
        "[%s] [%s] %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
    );
    @file_put_contents($LOG_FILE, $line, FILE_APPEND);
}

/* ========= Flash messages ========= */
function flash(string $type, string $message): void {
    $_SESSION['__flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $f = $_SESSION['__flash'] ?? [];
    unset($_SESSION['__flash']);
    return $f;
}

/**
 * Rendu HTML minimal (compatible Bootstrap-like).
 * Tu peux remplacer les classes par celles de ton CSS.
 */
function render_flashes(): void {
    foreach (get_flashes() as $a) {
        $type = $a['type'] ?? 'danger';
        $msg  = $a['message'] ?? '';
        $cls  = match ($type) {
            'success' => 'alert-success',
            'warning' => 'alert-warning',
            'info'    => 'alert-info',
            default   => 'alert-danger',
        };
        echo '<div class="alert ' . $cls . '" role="alert">' . $msg . '</div>';
    }
}

/* ========= Handlers d’erreurs/exceptions ========= */
set_error_handler(function ($severity, $message, $file, $line) use ($APP_DEBUG) {
    // Ignore si masqué par error_reporting courant
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $map = [
        E_ERROR=>'E_ERROR', E_WARNING=>'E_WARNING', E_PARSE=>'E_PARSE', E_NOTICE=>'E_NOTICE',
        E_CORE_ERROR=>'E_CORE_ERROR', E_CORE_WARNING=>'E_CORE_WARNING',
        E_COMPILE_ERROR=>'E_COMPILE_ERROR', E_COMPILE_WARNING=>'E_COMPILE_WARNING',
        E_USER_ERROR=>'E_USER_ERROR', E_USER_WARNING=>'E_USER_WARNING', E_USER_NOTICE=>'E_USER_NOTICE',
        E_STRICT=>'E_STRICT', E_RECOVERABLE_ERROR=>'E_RECOVERABLE_ERROR',
        E_DEPRECATED=>'E_DEPRECATED', E_USER_DEPRECATED=>'E_USER_DEPRECATED'
    ];

    app_log('error', "PHP {$map[$severity]??$severity}: $message @ $file:$line");

    if ($APP_DEBUG) {
        // Affichage détaillé en dev
        echo "<pre style='white-space:pre-wrap'><b>PHP Error:</b> "
            . htmlspecialchars($message) . "\n"
            . htmlspecialchars($file) . ':' . (int)$line . "</pre>";
    } else {
        // En prod: message générique
        if (is_ajax_request()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status'=>'error','message'=>"Une erreur interne s'est produite."], JSON_UNESCAPED_UNICODE);
        } else {
            flash('danger', "Une erreur interne s'est produite.");
        }
    }
    return true; // géré
});

set_exception_handler(function (Throwable $e) use ($APP_DEBUG) {
    app_log('critical', 'Uncaught Exception', [
        'type' => get_class($e),
        'msg'  => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        // Astuce: éviter de logger la trace complète en prod si tu veux
    ]);

    if ($APP_DEBUG) {
        echo "<pre style='white-space:pre-wrap'><b>Exception:</b> "
            . htmlspecialchars($e->getMessage())
            . "\n" . htmlspecialchars($e->getFile()) . ':' . (int)$e->getLine()
            . "\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        if (is_ajax_request()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>"Erreur interne."], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(500);
            flash('danger', "Erreur interne.");
        }
    }
});

register_shutdown_function(function () use ($APP_DEBUG) {
    $err = error_get_last();
    if (!$err) return;

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (in_array($err['type'] ?? 0, $fatalTypes, true)) {
        app_log('alert', 'Fatal error', $err);
        if ($APP_DEBUG) {
            echo "<pre style='white-space:pre-wrap'><b>Fatal:</b> "
                . htmlspecialchars($err['message'] ?? '') . "\n"
                . htmlspecialchars($err['file'] ?? '') . ':' . (int)($err['line'] ?? 0)
                . "</pre>";
        } else {
            if (is_ajax_request()) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode(['status'=>'error','message'=>"Erreur critique."], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(500);
                flash('danger', "Erreur critique.");
            }
        }
    }
});

/* ========= Abort helper (stopper proprement) ========= */
/**
 * abort(403, "Access denied");
 * abort(404);
 * abort(500, "Custom error");
 */
function abort(int $code = 403, string $message = ''): void {
    http_response_code($code);
    $message = $message ?: match ($code) {
        401 => "Authentification requise.",
        403 => "Accès refusé.",
        404 => "Page introuvable.",
        default => "Erreur interne ($code)."
    };

    app_log('warning', "Abort $code: $message");

    if (is_ajax_request()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status'=>'error','code'=>$code,'message'=>$message], JSON_UNESCAPED_UNICODE);
    } else {
        flash($code >= 500 ? 'danger' : 'warning', $message);
    }
    exit;
}
