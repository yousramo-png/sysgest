<?php
require_once __DIR__ . "/../init.php";

$formSubmitted = false;

// --------------------
// ACTIONS GROUPEES
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['bulk_action'] ?? '';
    $selectedUsers = $_POST['users'] ?? [];

    if (!empty($action) && !empty($selectedUsers)) {
        // Sécurité : assainir les IDs
        $ids = array_map('intval', $selectedUsers);
        $idsList = implode(',', $ids);

        if ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM " . __DB_PREFIX__ . "users WHERE id IN ($idsList)");
            $stmt->execute();
            flash('success', count($ids) . " utilisateur(s) supprimé(s).");

        } elseif ($action === 'activate') {
            $stmt = $pdo->prepare("UPDATE " . __DB_PREFIX__ . "users SET status = 'active' WHERE id IN ($idsList)");
            $stmt->execute();
            flash('success', count($ids) . " utilisateur(s) activé(s).");

        } elseif ($action === 'deactivate') {
            $stmt = $pdo->prepare("UPDATE " . __DB_PREFIX__ . "users SET status = 'inactive' WHERE id IN ($idsList)");
            $stmt->execute();
            flash('success', count($ids) . " utilisateur(s) désactivé(s).");

        } else {
            flash('danger', "Action groupée non reconnue.");
        }

        // Redirection pour éviter le resubmit du formulaire
        header("Location: utilisateurs.php");
        exit;

    } else {
        flash('danger', "Veuillez sélectionner au moins un utilisateur et une action.");
    }
}
// --------------------
// ACTIONS (delete / toggle status)
// --------------------
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($id > 0) {
        if ($action === "delete") {
            deleteUser($id);
            header("Location: utilisateurs.php");
            exit;
        } elseif ($action === "toggle") {
            toggleUserStatus($id);
            header("Location: utilisateurs.php");
            exit;
        }
       
    }
}


// --------------------
// TRAITEMENT UPDATE
// --------------------
if (($_GET['action'] ?? '') === "update" && isset($_GET['id']) && $_SERVER["REQUEST_METHOD"] === "POST") {
    $userId    = (int)$_GET['id'];
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $roleId    = (int)($_POST['role_id'] ?? 0);
    $status    = $_POST['status'] ?? 'inactive';

    if ($name && $email && $roleId) {
        $stmt = $pdo->prepare("
            UPDATE " . __DB_PREFIX__ . "users 
            SET name = :name, email = :email, telephone = :telephone, role_id = :role_id, status = :status 
            WHERE id = :id
        ");

        $stmt->execute([
            ':name'      => $name,
            ':email'     => $email,
            ':telephone' => $telephone,
            ':role_id'   => $roleId,
            ':status'    => $status,
            ':id'        => $userId,
        ]);
        flash('success', "Utilisateur modifié avec succès.");
        header("Location: utilisateurs.php");
        exit;
    } else {
        flash('danger', "Tous les champs requis ne sont pas remplis.");
    }
}

// -----------
// ---------
// PAGE EDITION UTILISATEUR
// --------------------
if (($_GET['action'] ?? '') === 'modifier' && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];

    $stmt = $pdo->prepare("
        SELECT u.*, r.libelle AS role_name
        FROM " . __DB_PREFIX__ . "users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "<p>Utilisateur introuvable.</p>";
        exit;
    }

    // Charger les rôles
   $rolesStmt = $pdo->query("
        SELECT id, libelle 
        FROM " . __DB_PREFIX__ . "roles 
        WHERE name != 'super-admin'
        ORDER BY id ASC
    ");

    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Modifier utilisateur</title>
        <link rel="stylesheet" href="assets/css/bootstrap.min.css">
         <link rel="stylesheet" href="assets/css/dashlite.css?ver=3.2.3">
    <link id="skin-default" rel="stylesheet" href="assets/css/theme.css?ver=3.2.3">
    </head>
    <body class="container py-4">
        <h2>Modifier l’utilisateur #<?= htmlspecialchars($user['custom_id']) ?></h2>

        <?php render_flashes(); ?>

        <form method="post" action="utilisateurs.php?action=update&id=<?= $user['id'] ?>">
            <div class="mb-3">
                <label>Nom</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="form-control">
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-control">
            </div>
            <div class="mb-3">
                <label>Téléphone</label>
                <input type="text" name="telephone" value="<?= htmlspecialchars($user['telephone']) ?>" class="form-control">
            </div>
            <div class="mb-3">
                <label>Rôle</label>
                <select name="role_id" class="form-select">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>" <?= $role['id'] == $user['role_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['libelle']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label>Statut</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $user['status']==='active'?'selected':'' ?>>Actif</option>
                    <option value="inactive" <?= $user['status']==='inactive'?'selected':'' ?>>Inactif</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
            <a href="utilisateurs.php" class="btn btn-secondary">Annuler</a>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// --------------------
// AJOUTER UTILISATEUR
// --------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajouter_utilisateur'])) {
    $formSubmitted     = true;
    $name              = trim($_POST["nom"] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $telephone         = trim($_POST['telephone'] ?? '');
    $password          = $_POST['password'] ?? '';
    $confirm_password  = $_POST['confirm_password'] ?? '';
    $rolePost          = $_POST['role'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($rolePost)) {
        flash('danger', "Tous les champs sont obligatoires !");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', "Adresse email invalide !");
    } elseif ($password !== $confirm_password) {
        flash('danger', "Les mots de passe ne correspondent pas !");
    } else {
        $statement = $pdo->prepare("SELECT id FROM " . __DB_PREFIX__ . "users WHERE email = :email");
        $statement->execute([':email' => $email]);

        if ($statement->fetch()) {
            flash('danger', "Cet email est déjà utilisé !");
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $statement = $pdo->prepare("
                INSERT INTO users (name, email, telephone, password, role_id, status)
                VALUES (:name, :email, :telephone, :password, :role_id, 'active')
            ");
            try {
                $statement->execute([
                    ':name'      => $name,
                    ':email'     => $email,
                    ':telephone' => $telephone,
                    ':password'  => $hashed_password,
                    ':role_id'   => (int)$rolePost,
                ]);

                $lastInsertId = $pdo->lastInsertId();
                $customId = 'USR' . str_pad($lastInsertId, 5, '0', STR_PAD_LEFT);

                $updateStmt = $pdo->prepare("UPDATE users SET custom_id = :custom_id WHERE id = :id");
                $updateStmt->execute([
                    ':custom_id' => $customId,
                    ':id'        => $lastInsertId
                ]);

                flash('success', "Utilisateur ajouté avec succès.");
            } catch (PDOException $e) {
                flash('danger', "Erreur lors de l'ajout : " . $e->getMessage());
            }
        }
    }
}

// --------------------
// RÉCUPÉRER RÔLES
// --------------------
$rolesStmt = $pdo->query("
    SELECT id, libelle 
    FROM " . __DB_PREFIX__ . "roles 
    WHERE role != 'super-admin'
    ORDER BY id ASC
");

$roles = $rolesStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// --------------------
// FILTRES + RECHERCHE + PAGINATION
// --------------------
$filterRole   = isset($_GET['role']) && $_GET['role'] !== '' ? (int)$_GET['role'] : null;
$filterStatus = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$order        = strtoupper($_GET['order'] ?? 'DESC');
$limit        = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

$limit = in_array($limit, [10, 20, 50]) ? $limit : 10;
$order = in_array($order, ['ASC','DESC']) ? $order : 'DESC';

// construire WHERE
$conditions = [];
$bindParams = [];

if ($filterRole !== null) {
    $conditions[] = "u.role_id = :role";
    $bindParams[':role'] = $filterRole;
}
if ($filterStatus !== null) {
    $conditions[] = "u.status = :status";
    $bindParams[':status'] = $filterStatus;
}

$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

// --------------------
// TOTAL UTILISATEURS
// --------------------
$sqlCount = "SELECT COUNT(*) FROM " . __DB_PREFIX__ . "users u $where";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($bindParams);
$totalUsers = (int)$stmt->fetchColumn();

$totalPages  = $totalUsers > 0 ? (int)ceil($totalUsers / $limit) : 1;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
if ($currentPage > $totalPages) $currentPage = $totalPages;

$offset = ($currentPage - 1) * $limit;

// --------------------
// LISTE DES UTILISATEURS
// --------------------
$sql = "
    SELECT u.id, u.custom_id, u.name, u.email, u.telephone, u.status, u.role_id, r.libelle AS role_name
    FROM " . __DB_PREFIX__ . "users u
    LEFT JOIN " . __DB_PREFIX__ . "roles r ON u.role_id = r.id
    WHERE r.role <> 'super-admin'
    " . ($where ? " AND $where" : "") . "
    ORDER BY u.id $order
    LIMIT :limit OFFSET :offset
";


$stmt = $pdo->prepare($sql);

foreach ($bindParams as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ensuite inclure ton HTML DataTables existant...
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
    <title>User List | DashLite Admin Template</title>
    <!-- StyleSheets  -->
    <link rel="stylesheet" href="assets/css/dashlite.css?ver=3.2.3">
    <link id="skin-default" rel="stylesheet" href="assets/css/theme.css?ver=3.2.3">
</head>

<body class="nk-body ui-rounder npc-default has-sidebar ">
    <div class="nk-app-root">
            <?php include __DIR__ . "/../includes/navLeft.php"; ?>

        <!-- main @s -->
        <div class="nk-main ">
            <!-- wrap @s -->
            <div class="nk-wrap ">
                <?php include __DIR__ . "/../includes/navTop.php"; ?>
                <!-- content @s -->
                <div class="nk-content ">
                    <div class="container-fluid">
                        <div class="nk-content-inner">
                            <div class="nk-content-body">
                                <div class="nk-block-head nk-block-head-sm">
                                    <div class="nk-block-between">
                                        <div class="nk-block-head-content">
                                            <h3 class="nk-block-title page-title">Liste des utilisateurs</h3>
                                            <div class="nk-block-des text-soft">
                                                <p>Vous avez un total de <?= $totalUsers ?> utilisateurs.</p>
                                            </div>
                                        </div><!-- .nk-block-head-content -->
                                        <?php render_flashes(); ?>
                                        <div class="nk-block-head-content">
                                            <div class="toggle-wrap nk-block-tools-toggle">
                                                <a href="#" class="btn btn-icon btn-trigger toggle-expand me-n1" data-target="pageMenu"><em class="icon ni ni-menu-alt-r"></em></a>
                                                <div class="toggle-expand-content" data-content="pageMenu">
                                                    <ul class="nk-block-tools g-3">
                                                        <li class="nk-block-tools-opt">                                                  
                                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalForm"> <em class="icon ni ni-plus"></em> Ajouter un utilisateur</button>
                                                        </li>
                                                        <li>
                                                            <div class="dropdown">
                                                                <button class="btn btn-warning dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                                                                    <em class="icon ni ni-filter-alt me-1"></em> Recherche avancée
                                                                </button>
                                                                <div class="filter-wg dropdown-menu dropdown-menu-xl dropdown-menu-end">
                                                                    <div class="dropdown-head">
                                                                        <span class="sub-title dropdown-title">Filtres</span>
                                                                    </div>
                                                                    <div class="dropdown-body dropdown-body-rg">
                                                                        <form method="get" action="utilisateurs.php" class="row gx-6 gy-3 p-2">
                                                                            <div class="col-6">
                                                                                <label class="overline-title overline-title-alt">Rôle</label>
                                                                                <div class="form-control-select">
                                                                                    <?php 
                                                                                    echo create_select("role","role","form-control","",true,false, false, $roles,"","Choisir un rôle");
                                                                                    ?>                                        
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <label class="overline-title overline-title-alt">Statut</label>
                                                                                <select name="status" class="form-select js-select2">
                                                                                    <option value="">Tous</option>
                                                                                    <option value="active" <?= ($filterStatus === 'active') ? 'selected' : '' ?>>Actif</option>
                                                                                    <option value="inactive" <?= ($filterStatus === 'inactive') ? 'selected' : '' ?>>Inactif</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <label class="overline-title overline-title-alt">Afficher</label>
                                                                                <select name="limit" class="form-select js-select2">
                                                                                    <option value="10" <?= ($limit==10)?'selected':'' ?>>10</option>
                                                                                    <option value="20" <?= ($limit==20)?'selected':'' ?>>20</option>
                                                                                    <option value="50" <?= ($limit==50)?'selected':'' ?>>50</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <label class="overline-title overline-title-alt">Ordre</label>
                                                                                <select name="order" class="form-select js-select2">
                                                                                    <option value="DESC" <?= ($order==='DESC')?'selected':'' ?>>DESC</option>
                                                                                    <option value="ASC" <?= ($order==='ASC')?'selected':'' ?>>ASC</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-12 d-flex justify-content-between">
                                                                                <a href="utilisateurs.php" class="btn btn-light">Réinitialiser</a>
                                                                                <button type="submit" class="btn btn-primary">Filtrer</button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div><!-- .filter-wg -->
                                                            </div>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div><!-- .toggle-wrap -->
                                        </div><!-- .nk-block-head-content -->
                                    </div><!-- .nk-block-between -->
                                </div><!-- .nk-block-head -->
                                <div class="nk-block">
                                    <div class="card card-bordered card-stretch">
                                        <div class="card-inner-group">
                                            <div class="nk-block nk-block-lg">
                                                <div class="card card-bordered card-preview">
                                                    <div class="card-inner">
                                                        
                                                        <form method="post" action="utilisateurs.php">
                                                            <div class="nk-block-between mb-3 d-flex justify-content-between align-items-center">
                                                                <!-- Sélecteur Action groupée -->
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <select name="bulk_action" class="form-select form-select-sm">
                                                                        <option value="">Action groupée</option>
                                                                        <option value="delete">Supprimer</option>
                                                                        <option value="activate">Activer</option>
                                                                        <option value="deactivate">Désactiver</option>
                                                                    </select>
                                                                    <button type="submit" name="apply_bulk" class="btn btn-sm btn-primary">
                                                                        Appliquer
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <!-- Tableau -->
                                                            <table class="datatable-init-export nowrap table" data-export-title="Export">
                                                                <thead>
                                                                    <tr>
                                                                        <th><input type="checkbox" id="checkAll" class="form-check-input"></th>
                                                                        <th>ID</th>
                                                                        <th>Nom</th>
                                                                        <th>Email</th>
                                                                        <th>Numéro de téléphone</th>
                                                                        <th>Rôle</th>
                                                                        <th>Statut</th>
                                                                        <th>Actions</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($users as $user): ?>
                                                                        <tr data-id="<?= htmlspecialchars($user['id']) ?>">
                                                                            <!-- Checkbox -->
                                                                            <td>
                                                                                <?= create_input(
                                                                                    "checkbox", "users[]", "", "", "form-check-input checkItem", "",
                                                                                    $user['id'], false, false
                                                                                ) ?>
                                                                            </td>
                                                                            <td><?= htmlspecialchars($user['custom_id']) ?></td>
                                                                            <td><?= htmlspecialchars($user['name']) ?></td>
                                                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                                                            <td><?= htmlspecialchars($user['telephone'] ?? "Non défini") ?></td>
                                                                            <td><?= htmlspecialchars($user['role_name'] ?? "Inconnu") ?></td>
                                                                            <td>
                                                                                <?php if ($user['status'] === 'active'): ?>
                                                                                    <span class="badge rounded-pill bg-success">Actif</span>
                                                                                <?php else: ?>
                                                                                    <span class="badge rounded-pill bg-danger">Inactif</span>
                                                                                <?php endif; ?>
                                                                            </td>
                                                                            <td class="text-center">
                                                                                <a href="utilisateurs.php?action=modifier&id=<?= urlencode($user['id']) ?>" 
                                                                                class="btn btn-sm btn-icon btn-primary" title="Modifier">
                                                                                    <em class="icon ni ni-edit"></em>
                                                                                </a>
                                                                                <a href="edit-rights.php?id=<?= urlencode($user['id']) ?>" 
                                                                                class="btn btn-sm btn-icon btn-warning" title="Gérer les droits">
                                                                                    <em class="icon ni ni-lock-alt"></em>
                                                                                </a>
                                                                                <a href="utilisateurs.php?action=toggle&id=<?= urlencode($user['id']) ?>" 
                                                                                class="btn btn-sm btn-icon <?= $user['status'] === 'active' ? 'btn-success' : 'btn-secondary' ?>" 
                                                                                title="<?= $user['status'] === 'active' ? 'Désactiver' : 'Activer' ?>">
                                                                                    <em class="icon ni ni-power"></em>
                                                                                </a>
                                                                                <a href="utilisateurs.php?action=delete&id=<?= urlencode($user['id']) ?>" 
                                                                                class="btn btn-sm btn-icon btn-danger" 
                                                                                onclick="return confirm('Supprimer cet utilisateur ?')" title="Supprimer">
                                                                                    <em class="icon ni ni-trash"></em>
                                                                                </a>
                                                                            </td>
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>

                                                        </form><!-- Fin du form englobant -->
                                                                                    
                                                    </div><!-- .card-inner -->
                                                </div><!-- .card-preview -->
                                            </div><!-- .nk-block-lg -->

                                        </div><!-- .card-inner-group -->
                                    </div><!-- .card -->
                                </div><!-- .nk-block -->
                                <!-- .nk-block -->
                            </div>
                        </div>
                    </div>
                </div>
                <!-- content @e -->
            </div>
            <!-- wrap @e -->
        </div>
        <!-- main @e -->
    </div>
    <!-- app-root @e -->
    
    <!-- Modal Form -->
    <div class="modal fade" id="modalForm">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un nouvel utilisateur</h5>
                    <a href="#" class="close" data-bs-dismiss="modal" aria-label="Close">
                        <em class="icon ni ni-cross"></em>
                    </a>
                </div>
                <div class="modal-body">
                    <?php if ($formSubmitted && !empty($registerError)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($registerError) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                        </div>
                        <?php elseif ($formSubmitted && !empty($registerSuccess)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($registerSuccess) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
                        </div>
                        <?php endif; ?>

                    <form action="" method="POST" class="form-validate is-alter">
                        
                        <!-- Nom -->
                        <div class="form-group">
                            <label class="form-label" for="nom">Nom</label>
                            <div class="form-control-wrap">
                                <?php 
                                echo create_input("text", "nom", "nom" , "Entrer le nom" , "form-control" , "", "", true);
                                ?>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="form-group">
                            <label class="form-label" for="email">Adresse Email</label>
                            <div class="form-control-wrap">
                                <?php 
                                echo create_input("email", "email", "email" , "Entrer l'adresse email" , "form-control" , "", "", true);
                                ?>
                            </div>
                        </div>

                        <!-- Téléphone -->
                        <div class="form-group">
                            <label class="form-label" for="telephone">Numéro de téléphone</label>
                            <div class="form-control-wrap">
                                <?php 
                                echo create_input("tel", "telephone","telephone","Entrer le numéro de téléphone", "form-control", "", "",true) ;
                                ?>
                            </div>
                        </div>

                        <!-- Rôle -->
                        <div class="form-group">
                            <label class="form-label">Rôle</label>
                            <div class="form-control-select">
                                <?php 
                                echo create_select("role","role","form-control","",true,false, false, $roles,"","Choisir un rôle");
                                ?>                                        
                            </div>
                        </div>

                        <!-- Mot de passe -->
                        <div class="form-group">
                            <label class="form-label" for="password">Mot de passe</label>
                            <div class="form-control-wrap">
                                <?php 
                                echo create_input("password", "password","password","Entrer un mot de passe", "form-control", "", "",true) ;
                                ?>
                            </div>
                        </div>

                        <!-- Confirmer le mot de passe -->
                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirmer le mot de passe</label>
                            <div class="form-control-wrap">
                                <?php 
                                echo create_input("password", "confirm_password","confirm_password","Confirmer le mot de passe", "form-control", "", "",true) ;
                                ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="ajouter_utilisateur" class="btn btn-primary">Enregistrer</button>
                        </div>
                        
                    </form>
                </div>
            </div>
        </div>
    </div>
                       
    <!-- JavaScript -->
     <script src="./assets/js/customjs.js"></script>
    <script src="./assets/js/bundle.js?ver=3.2.3"></script>
    <script src="./assets/js/scripts.js?ver=3.2.3"></script>
    <script src="./assets/js/libs/datatable-btns.js"></script>


</body>

</html>