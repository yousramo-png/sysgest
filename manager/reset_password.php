<?php
require_once __DIR__ . "/../init.php";

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

// Récup params
$uid   = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
$token = $_GET['token'] ?? '';

// Simple garde
if ($uid <= 0 || !$token) {
    flash('danger', 'Lien invalide.');
    header('Location: forgot.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_submit'])) {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        flash('danger', 'Session expirée. Merci de réessayer.');
        header('Location: reset_password.php?uid='.$uid.'&token='.urlencode($token)); exit;
    }

    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        flash('warning', 'Le mot de passe doit contenir au moins 6 caractères.');
        header('Location: reset_password.php?uid='.$uid.'&token='.urlencode($token)); exit;
    }
    if ($password !== $confirm_password) {
        flash('warning', 'Les mots de passe ne correspondent pas.');
        header('Location: reset_password.php?uid='.$uid.'&token='.urlencode($token)); exit;
    }

    try {
        // Récupérer le dernier token actif pour ce user (non utilisé et non expiré)
        $stmt = $pdo->prepare("SELECT id, token_hash, expires_at, used_at FROM password_resets
                               WHERE user_id = :uid AND used_at IS NULL AND expires_at >= NOW()
                               ORDER BY id DESC LIMIT 1");
        $stmt->execute([':uid' => $uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($token, $row['token_hash'])) {
            flash('danger', 'Lien invalide ou expiré.');
            header('Location: forgot.php'); exit;
        }

        // Mettre à jour le mot de passe
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $pdo->beginTransaction();

        $upUser = $pdo->prepare("UPDATE users SET password = :pwd WHERE id = :id");
        $upUser->execute([':pwd' => $hashed, ':id' => $uid]);

        // Marquer le token comme utilisé
        $upTok = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = :id");
        $upTok->execute([':id' => $row['id']]);

        // (Optionnel) Invalider tous les autres tokens non utilisés
        $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL")->execute([':uid' => $uid]);

        $pdo->commit();

        app_log('info', 'Password reset success', ['user_id'=>$uid, 'token_id'=>$row['id']]);
        flash('success', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
        header('Location: login.php'); exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        app_log('error', 'Password reset DB error', ['msg'=>$e->getMessage(), 'user_id'=>$uid]);
        flash('danger', "Impossible de réinitialiser le mot de passe pour le moment.");
        header('Location: reset_password.php?uid='.$uid.'&token='.urlencode($token)); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Nouveau mot de passe</title>
    <link rel="stylesheet" href="../assets/css/dashlite.css?ver=3.2.3">
    <link rel="stylesheet" href="../assets/css/theme.css?ver=3.2.3">
</head>
<body class="nk-body bg-white npc-general pg-auth">
<div class="nk-app-root">
    <div class="nk-main">
        <div class="nk-wrap nk-wrap-nosidebar">
            <div class="nk-content">
                <div class="nk-block nk-block-middle nk-auth-body wide-xs">
                    <div class="card card-bordered">
                        <div class="card-inner card-inner-lg">
                            <h4 class="nk-block-title">Définir un nouveau mot de passe</h4>
                            <?php render_flashes(); ?>
                            <form method="post" action="">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                                <div class="form-group">
                                    <label class="form-label" for="password">Nouveau mot de passe</label>
                                    <div class="form-control-wrap">
                                        <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="confirm_password">Confirmer le mot de passe</label>
                                    <div class="form-control-wrap">
                                        <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <button class="btn btn-lg btn-primary btn-block" name="reset_submit" type="submit">Mettre à jour</button>
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
