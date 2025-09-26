<?php
require_once __DIR__ . "/../init.php";
require_once __DIR__ . "/../manager/config/mailer.php";

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_submit'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        flash('danger', 'Session expirée. Merci de réessayer.');
        header('Location: forgot.php'); exit;
    }

    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Message neutre (ne révèle pas)
        flash('success', 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.');
        header('Location: forgot.php'); exit;
    }

    try {
        // Chercher l’utilisateur (actif)
        $stmt = $pdo->prepare("SELECT id, custom_id, status, name FROM " . __DB_PREFIX__ . "users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['status'] === 'active') {
            // Anti-spam: un token récent (<10 min) ?
            $recent = $pdo->prepare("SELECT id FROM password_resets WHERE user_id = :uid AND used_at IS NULL AND expires_at > NOW() - INTERVAL 50 MINUTE ORDER BY id DESC LIMIT 1");
            $recent->execute([':uid' => $user['id']]);
            // (ici on laisse passer; tu peux bloquer si $recent->fetch())

            // Créer un token
            $rawToken   = bin2hex(random_bytes(32)); // à envoyer par email
            $tokenHash  = password_hash($rawToken, PASSWORD_BCRYPT);
            $expiresAt  = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

            $ins = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, ip, ua) VALUES (:uid, :hash, :exp, :ip, :ua)");
            $ins->execute([
                ':uid'  => $user['id'],
                ':hash' => $tokenHash,
                ':exp'  => $expiresAt,
                ':ip'   => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250),
            ]);

            // Lien
            $resetUrl = rtrim(__BASE_URL__, '/').'/auth/reset_password.php?uid='.$user['id'].'&token='.$rawToken;

            // Email
            $subject = 'Réinitialisation de votre mot de passe';
            $html    = '<p>Bonjour '.htmlspecialchars($user['name']).',</p>'
                     . '<p>Vous avez demandé la réinitialisation de votre mot de passe. '
                     . 'Cliquez sur le lien ci-dessous (valide 1 heure) :</p>'
                     . '<p><a href="'.$resetUrl.'">'.$resetUrl.'</a></p>'
                     . '<p>Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer ce message.</p>';

            $sent = send_mail($email, $subject, $html);
            app_log('info', 'Password reset email triggered', ['user_id'=>$user['id'], 'sent'=>$sent]);

            // Réponse neutre
            flash('success', 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.');
        } else {
            // Réponse neutre
            flash('success', 'Si un compte existe pour cet email, un lien de réinitialisation a été envoyé.');
            app_log('warning', 'Password reset requested for non-existing or inactive email', ['email'=>$email]);
        }
    } catch (PDOException $e) {
        app_log('error', 'Forgot process DB error', ['msg'=>$e->getMessage()]);
        flash('danger', "Impossible de traiter la demande pour le moment.");
    }

    header('Location: forgot.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Mot de passe oublié</title>
    <link rel="stylesheet" href="assets/css/dashlite.css?ver=3.2.3">
    <link id="skin-default" rel="stylesheet" href="assets/css/theme.css?ver=3.2.3">
</head>
<body class="nk-body bg-white npc-general pg-auth">
<div class="nk-app-root">
    <div class="nk-main">
        <div class="nk-wrap nk-wrap-nosidebar">
            <div class="nk-content">
                <div class="nk-block nk-block-middle nk-auth-body wide-xs">
                    <div class="card card-bordered">
                        <div class="card-inner card-inner-lg">
                            <h4 class="nk-block-title">Mot de passe oublié</h4>
                            <p>Entrez votre adresse email pour recevoir un lien de réinitialisation.</p>

                            <?php render_flashes(); ?>

                            <form method="post" action="forgot.php" class="form-validate is-alter">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                <div class="form-group">
                                    <label class="form-label" for="email">Adresse Email</label>
                                    <div class="form-control-wrap">
                                        <input type="email" class="form-control form-control-lg" id="email" name="email" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <button class="btn btn-lg btn-primary btn-block" name="forgot_submit" type="submit">Envoyer le lien</button>
                                </div>
                            </form>

                            <div class="form-note-s2 text-center pt-4">
                                <a href="login.php">Retour à la connexion</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- content -->
        </div>
    </div>
</div>
<script src="../assets/js/bundle.js?ver=3.2.3"></script>
<script src="../assets/js/scripts.js?ver=3.2.3"></script>
</body>
</html>
