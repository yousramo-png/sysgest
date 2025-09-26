<?php
session_start();

// Si déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: utilisateurs.php"); 
    exit;
}

require_once __DIR__ . "/../init.php";


if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        flash('danger', "Email et mot de passe sont requis !");
    } else {
        // Vérifier l'utilisateur
        $stmt = $pdo->prepare("
            SELECT id, custom_id, name, email, password, role_id, status 
            FROM " . __DB_PREFIX__ . "users 
            WHERE email = :email
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            
            // Vérifier si compte actif
            if ($user['status'] !== 'active') {
                flash('danger', "Votre compte est inactif. Contactez l’administrateur.");
            } else {
                // régénérer l'ID de session
                session_regenerate_id(true);

                // Stocker les infos utilisateur
                $_SESSION['user_id'] = $user['custom_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['LAST_ACTIVITY'] = time(); // dernière activité

                // Charger permissions
                $permStmt = $pdo->prepare("
                    SELECT p.resource, p.action
                    FROM permissions p
                    JOIN role_permissions rp ON p.id = rp.permission_id
                    WHERE rp.role_id = :role_id
                ");
                $permStmt->execute([':role_id' => $user['role_id']]);

                $_SESSION['permissions'] = [];
                while ($row = $permStmt->fetch(PDO::FETCH_ASSOC)) {
                    $_SESSION['permissions'][$row['resource']][] = $row['action'];
                }

                foreach ($_SESSION['permissions'] as $res => $actions) {
                    $_SESSION['permissions'][$res] = array_values(array_unique($actions));
                }

                header("Location: utilisateurs.php");
                exit;
            }
        } else {
            flash('danger', "Identifiants invalides !");
        }
    }
}
?>


<!DOCTYPE html>
<html lang="zxx" class="js">

<head>
    
    <meta charset="utf-8">
    <meta name="author" content="Softnio">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="A powerful and conceptual apps base dashboard template that especially build for developers and programmers.">
    <!-- Fav Icon  -->
    <link rel="shortcut icon" href="./images/favicon.png">
    <!-- Page Title  -->
    <title>Login | DashLite Admin Template</title>
    <!-- StyleSheets  -->
    <link rel="stylesheet" href="assets/css/dashlite.css?ver=3.2.3">
    <link id="skin-default" rel="stylesheet" href="assets/css/theme.css?ver=3.2.3">
</head>

<body class="nk-body bg-white npc-general pg-auth">
    <div class="nk-app-root">
        <!-- main @s -->
        <div class="nk-main ">
            <!-- wrap @s -->
            <div class="nk-wrap nk-wrap-nosidebar">
                <!-- content @s -->
                <div class="nk-content ">
                    <div class="nk-block nk-block-middle nk-auth-body  wide-xs">
                        <div class="brand-logo pb-4 text-center">
                            <a href="index.html" class="logo-link">
                                <img class="logo-light logo-img logo-img-lg" src="./images/logo.png" srcset="./images/logo2x.png 2x" alt="logo">
                                <img class="logo-dark logo-img logo-img-lg" src="./images/logo-dark.png" srcset="./images/logo-dark2x.png 2x" alt="logo-dark">
                            </a>
                        </div>
                        <div class="card card-bordered">
                            <div class="card-inner card-inner-lg">
                                <div class="nk-block-head">
                                    <?php render_flashes(); ?>
                                    <div class="nk-block-head-content">
                                        <h4 class="nk-block-title">Se connecter</h4>
                                        <div class="nk-block-des">
                                            <p>Merci de vous identifier avec votre email et mot de passe pour accéder à l’espace d’administration.</p>
                                        </div>
                                    </div>
                                </div>
                                <form action="login.php" method="POST">
                                    <div class="form-group">
                                        <div class="form-label-group">
                                            <label class="form-label" for="email">Email</label>
                                        </div>
                                        <div class="form-control-wrap">
                                            <input type="text" class="form-control form-control-lg" id="email" name="email" placeholder="Enter your email address or username">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <div class="form-label-group">
                                            <label class="form-label" for="password">Mot de passe</label>
                                            <a class="link link-primary link-sm" href="forgot.php">Mot de passe oublié?</a>
                                        </div>
                                        <div class="form-control-wrap">
                                            <a href="#" class="form-icon form-icon-right passcode-switch lg" data-target="password">
                                                <em class="passcode-icon icon-show icon ni ni-eye"></em>
                                                <em class="passcode-icon icon-hide icon ni ni-eye-off"></em>
                                            </a>
                                            <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Enter your passcode">
                                        </div>
                                    </div>
                                    
                                   
                                    <div class="form-group">
                                        <button class="btn btn-lg btn-primary btn-block">Se connecter</button>
                                    </div>
                                </form>
                                
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
                <!-- wrap @e -->
            </div>
            <!-- content @e -->
        </div>
        <!-- main @e -->
    </div>
    <!-- app-root @e -->
    <!-- JavaScript -->
    <script src="assets/js/bundle.js?ver=3.2.3"></script>
    <script src="assets/js/scripts.js?ver=3.2.3"></script>
    <!-- select region modal -->

</html>