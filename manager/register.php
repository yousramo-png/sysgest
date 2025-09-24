    <?php
    session_start();
    require __DIR__ . '/../includes/database.php';

    $registerError = '';
    $registerSuccess = ''; 

    // Traitement du formulaire
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $name = trim($_POST["name"] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        // Validation des champs
        if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $registerError = "Tous les champs sont obligatoires !";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = "Email invalide !";
    }

    // Vérification de l'unicité de l'email
    $statement = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $statement->execute([':email' => $email]);
    if ($statement->fetch()) {
        $registerError = "Cet email est déjà utilisé !";
    }

    // Hash du mot de passe
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Insertion dans la base de données
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

        $registerSuccess = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
        header("Location: login.php");
        exit;

    } catch (PDOException $e) {
        $registerError = "Erreur lors de l'inscription : " . $e->getMessage();
    }
}


?>
<!DOCTYPE html>
<html lang="zxx" class="js">

<head>
    <base href="../">
    <meta charset="utf-8">
    <meta name="author" content="Softnio">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="A powerful and conceptual apps base dashboard template that especially build for developers and programmers.">
    <!-- Fav Icon  -->
    <link rel="shortcut icon" href="./images/favicon.png">
    <!-- Page Title  -->
    <title>Register | DashLite Admin Template</title>
    <!-- StyleSheets  -->
    <link rel="stylesheet" href="./assets/css/style.css?ver=3.2.3">
    <link id="skin-default" rel="stylesheet" href="./assets/css/theme.css?ver=3.2.3">
</head>

<body class="nk-body bg-white npc-general pg-auth">
    <div class="nk-app-root">
        <!-- main @s -->
        <div class="nk-main ">
            <!-- wrap @s -->
            <div class="nk-wrap nk-wrap-nosidebar">
                <!-- content @s -->
                <div class="nk-content ">
                    <div class="nk-block nk-block-middle nk-auth-body wide-xs">
                        <div class="brand-logo pb-4 text-center">
                            <a href="manager/index.html" class="logo-link">
                                <img class="logo-light logo-img logo-img-lg" src="./images/logo.png" srcset="./images/logo2x.png 2x" alt="logo">
                                <img class="logo-dark logo-img logo-img-lg" src="./images/logo-dark.png" srcset="./images/logo-dark2x.png 2x" alt="logo-dark">
                            </a>
                        </div>
                        <div class="card card-bordered">
                            <div class="card-inner card-inner-lg">
                                <div class="nk-block-head">
                                    <div class="nk-block-head-content">
                                        <h4 class="nk-block-title">Inscription</h4>
                                        <div class="nk-block-des">
                                            <p>Créer un nouveau compte</p>
                                        </div>
                                    </div>
                                </div>
                                <form action="manager/register.php" method="POST">
                                    <div class="form-group">
                                        <label class="form-label" for="name">Nom</label>
                                        <div class="form-control-wrap">
                                            <input type="text" class="form-control form-control-lg" id="name" name="name" placeholder="Enter your name">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="email">Email</label>
                                        <div class="form-control-wrap">
                                            <input type="text" class="form-control form-control-lg" id="email" name="email" placeholder="Enter your email address or username">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="role">Role</label>
                                        <div class="form-control-wrap ">
                                            <div class="form-control-select">
                                                <select class="form-control" id="role" name="role">
                                                    <option value="" selected disabled>Selectionner votre role</option>
                                                    <option value="1">Super admin</option>
                                                    <option value="2">Admin</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="password">Mot de passe</label>
                                        <div class="form-control-wrap">
                                            <a href="#" class="form-icon form-icon-right passcode-switch lg" data-target="password">
                                                <em class="passcode-icon icon-show icon ni ni-eye"></em>
                                                <em class="passcode-icon icon-hide icon ni ni-eye-off"></em>
                                            </a>
                                            <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Enter your passcode">
                                        </div>
                                    </div>
                                    <?php if (!empty($registerError)): ?>
                                        <p style="color: red; "><?php echo htmlspecialchars($registerError); ?></p>
                                    <?php endif; ?>

                                    <?php if (!empty($registerSuccess)): ?>
                                        <p style="color: green; "><?php echo htmlspecialchars($registerSuccess); ?></p>
                                    <?php endif; ?>

                                    <div class="form-group">
                                        <button class="btn btn-lg btn-primary btn-block">Créer le compte</button>
                                    </div>
                                </form>
                                <div class="form-note-s2 text-center pt-4"> Vous avez déjà un compte  <a href="manager/login.php"><strong>Connectez-vous ici</strong></a>
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
    <script src="./assets/js/bundle.js?ver=3.2.3"></script>
    <script src="./assets/js/scripts.js?ver=3.2.3"></script>
    

</html>