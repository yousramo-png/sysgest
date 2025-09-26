<?php
require_once __DIR__ . "/../init.php";

$formSubmitted = false;

// --------------------
// ACTIONS (delete / toggle status)
// --------------------
if (isset($_GET['action'], $_GET['id'])) {
    $id = trim($_GET['id']);
    $action = $_GET['action'];

    if ($id !== '') {
        if ($action === "delete") {
            deleteUser($id);
        } elseif ($action === "toggle") {
            toggleUserStatus($id);
        }
    }

    header("Location: utilisateurs.php");
    exit;
}

// --------------------
// MODIFIER UTILISATEUR
// --------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_user'])) {
    $user_id   = $_POST['user_id'] ?? '';
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $rolePost  = $_POST['role'] ?? '';

    if ($user_id && $name && $email && $rolePost) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', "Adresse email invalide !");
        } else {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET name = :name, email = :email, telephone = :telephone, role_id = :role_id 
                WHERE custom_id = :id
            ");
            $stmt->execute([
                ':name'      => $name,
                ':email'     => $email,
                ':telephone' => $telephone,
                ':role_id'   => (int)$rolePost,
                ':id'        => $user_id
            ]);
            flash('success', "Utilisateur modifié avec succès.");
        }
    } else {
        flash('danger', "Tous les champs requis ne sont pas remplis.");
    }
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
$stmt = $pdo->query("SELECT id, libelle FROM roles ORDER BY id ASC");
$roles = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// --------------------
// FILTRES + RECHERCHE + PAGINATION
// --------------------
$filterQ      = trim($_GET['q'] ?? '');
$filterRole   = isset($_GET['role']) && $_GET['role'] !== '' ? (int)$_GET['role'] : null;
$filterStatus = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$order        = strtoupper($_GET['order'] ?? 'DESC');
$limit        = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

$limit = in_array($limit, [10, 20, 50]) ? $limit : 10;
$order = in_array($order, ['ASC','DESC']) ? $order : 'DESC';

// construire WHERE
$conditions = [];
$bindParams = [];

if ($filterQ !== '') {
    $conditions[] = "(u.custom_id LIKE :q 
                   OR u.name LIKE :q 
                   OR u.email LIKE :q 
                   OR u.telephone LIKE :q)";
    $bindParams[':q'] = "%{$filterQ}%";
}
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
    SELECT u.custom_id, u.name, u.email, u.telephone, u.status, u.role_id, r.libelle AS role_name
    FROM " . __DB_PREFIX__ . "users u
    LEFT JOIN roles r ON u.role_id = r.id
    $where
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
                                                        <li><a href="#" class="btn btn-white btn-outline-light"><em class="icon ni ni-download-cloud"></em><span>Export</span></a></li>
                                                        <li class="nk-block-tools-opt">
                                                            
                                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalForm"> <em class="icon ni ni-plus"></em> Ajouter un utilisateur</button>
                                                            
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
                                                <div class="card-inner position-relative card-tools-toggle">
                                                    <div class="card-title-group">

                                                        <div class="card-tools">  
                                                                <div class="form-inline flex-nowrap gx-3">
                                                                    <div class="form-wrap w-150px">

                                                                        <?= create_select(
                                                                            "bulk_action",                     
                                                                            "",                               
                                                                            "form-select form-select-sm me-2", 
                                                                            "",                                
                                                                            false,                             
                                                                            false,                             
                                                                            false,                             
                                                                            [                                   
                                                                                "active" => "Activer",
                                                                                "inactive"  => "Suspendre",
                                                                                "supprimer"   => "Supprimer",
                                                                            ],
                                                                            "",                                
                                                                            "Action groupée"                   
                                                                        ) ?>
                                                                    </div>
                                                                    <div class="btn-wrap">
                                                                        <span class="d-none d-md-block"><button class="btn btn-sm btn-primary"  name="apply_bulk" type="submit">Appliquer</button></span>
                                                                        <span class="d-md-none"><button class="btn btn-dim btn-outline-light btn-icon disabled"><em class="icon ni ni-arrow-right"></em></button></span>
                                                                    </div>
                                                                </div><!-- .form-inline -->
                                                        </div><!-- .card-tools -->
                                                        <div class="card-tools me-n1">
                                                            <ul class="btn-toolbar gx-1">
                                                                <!-- simple search (GET) -->
                                                                <li>
                                                                    <form action="utilisateurs.php" method="get" class="d-flex align-items-center">
                                                                        <input type="hidden" name="role" value="<?= htmlspecialchars($filterRole ?? '') ?>">
                                                                        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus ?? '') ?>">
                                                                        <input type="hidden" name="limit" value="<?= htmlspecialchars($limit) ?>">
                                                                        <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
                                                                        <div class="form-control-wrap">
                                                                            <input type="text" class="form-control form-control-sm" 
                                                                                name="q" placeholder="Rechercher nom / email / téléphone"
                                                                                value="<?= htmlspecialchars($filterQ) ?>">
                                                                        </div>
                                                                        <button type="submit" class="btn btn-sm btn-primary ms-2">
                                                                            <em class="icon ni ni-search"></em>
                                                                        </button>
                                                                        <!-- bouton reset -->
                                                                        <a href="utilisateurs.php" class="btn btn-sm btn-outline-light ms-2">
                                                                                <em class="icon ni ni-reload"></em>

                                                                        </a>
                                                                    </form>
                                                                </li>


                                                                <li class="btn-toolbar-sep"></li>

                                                                <!-- dropdown filters (en GET) -->
                                                                <li>
                                                                    <div class="dropdown">
                                                                        <a href="#" class="btn btn-trigger btn-icon dropdown-toggle" data-bs-toggle="dropdown">
                                                                            <em class="icon ni ni-filter-alt"></em>
                                                                        </a>
                                                                        <div class="filter-wg dropdown-menu dropdown-menu-xl dropdown-menu-end">
                                                                            <div class="dropdown-head"><span class="sub-title dropdown-title">Filtres</span></div>
                                                                            <div class="dropdown-body dropdown-body-rg">
                                                                                <form method="get" action="utilisateurs.php" class="row gx-6 gy-3 p-2">
                                                                                    <div class="col-6">
                                                                                        <label class="overline-title overline-title-alt">Rôle</label>
                                                                                        <select name="role" class="form-select js-select2">
                                                                                            <option value="">Tous</option>
                                                                                            <?php foreach ($roles as $id => $libelle): ?>
                                                                                                <option value="<?= $id ?>" <?= ($filterRole == $id) ? 'selected' : '' ?>>
                                                                                                    <?= htmlspecialchars($libelle) ?>
                                                                                                </option>
                                                                                            <?php endforeach; ?>
                                                                                        </select>
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

                                                        <!-- .card-tools -->
                                                    </div><!-- .card-title-group -->
                                                    <div class="card-search search-wrap" data-search="search">
                                                        <div class="card-body">
                                                            <div class="search-content">
                                                                <a href="#" class="search-back btn btn-icon toggle-search" data-target="search"><em class="icon ni ni-arrow-left"></em></a>
                                                                <input type="text" class="form-control border-transparent form-focus-none" placeholder="Search by user or email">
                                                                <button class="search-submit btn btn-icon"><em class="icon ni ni-search"></em></button>
                                                            </div>
                                                        </div>
                                                    </div><!-- .card-search -->
                                                
                                                </div><!-- .card-inner -->
                                                <div class="nk-block nk-block-lg">
                                        <div class="nk-block-head">
                                            
                                        </div>
                                        <div class="card card-bordered card-preview">
                                            <div class="card-inner">
                                                <table class="datatable-init-export nowrap table" data-export-title="Export">
                                                    <thead>
                                                        <tr>
                                                            <th></th>
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
                                                            <tr data-id="<?= htmlspecialchars($user['custom_id']) ?>">
                                                                <!-- Checkbox -->
                                                                <td>
                                                                    <?= create_input(
                                                                        "checkbox",               
                                                                        "users[]",                
                                                                        "",                       
                                                                        "",                      
                                                                        "form-check-input",       
                                                                        "",                       
                                                                        $user['custom_id'],       
                                                                        false,                   
                                                                        false
                                                                    ) ?>
                                                                </td>

                                                                <!-- ID -->
                                                                <td><?= htmlspecialchars($user['custom_id']) ?></td>

                                                                <!-- Nom -->
                                                                <td><?= htmlspecialchars($user['name']) ?></td>

                                                                <!-- Email -->
                                                                <td><?= htmlspecialchars($user['email']) ?></td>

                                                                <!-- Téléphone -->
                                                                <td><?= htmlspecialchars($user['telephone'] ?? "Non défini") ?></td>

                                                                <!-- Rôle -->
                                                                <td><?= htmlspecialchars($user['role_name'] ?? "Inconnu") ?></td>

                                                                <!-- Statut -->
                                                                <td>
                                                                    <?php if ($user['status'] === 'active'): ?>
                                                                        <span class="badge rounded-pill bg-success">Actif</span>
                                                                    <?php else: ?>
                                                                        <span class="badge rounded-pill bg-danger">Inactif</span>
                                                                    <?php endif; ?>
                                                                </td>

                                                                <!-- Actions -->
                                                                <td class="text-center">
                                                                    <a href="#" 
                                                                    class="btn btn-sm btn-icon btn-primary" 
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#editUserModal<?= $user['custom_id'] ?>" 
                                                                    title="Modifier">
                                                                        <em class="icon ni ni-edit"></em>
                                                                    </a>
                                                                    <a href="utilisateurs.php?action=delete&id=<?= urlencode($user['custom_id']) ?>" 
                                                                    class="btn btn-sm btn-icon btn-danger" 
                                                                    onclick="return confirm('Supprimer cet utilisateur ?')" 
                                                                    title="Supprimer">
                                                                        <em class="icon ni ni-trash"></em>
                                                                    </a>
                                                                    <a href="edit-rights.php?id=<?= urlencode($user['custom_id']) ?>" 
                                                                    class="btn btn-sm btn-icon btn-warning" 
                                                                    title="Gérer les droits">
                                                                        <em class="icon ni ni-lock-alt"></em>
                                                                    </a>
                                                                    <a href="utilisateurs.php?action=toggle&id=<?= urlencode($user['custom_id']) ?>" 
                                                                    class="btn btn-sm btn-icon <?= $user['status'] === 'active' ? 'btn-success' : 'btn-secondary' ?>" 
                                                                    title="<?= $user['status'] === 'active' ? 'Désactiver' : 'Activer' ?>">
                                                                        <em class="icon ni ni-power"></em>
                                                                    </a>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>

                                                </table>
                                            </div>
                                        </div><!-- .card-preview -->
                                    </div> <!-- nk-block -->                                                
                                                    
                                                    <div class="card-inner p-0">
                                                        <div class="nk-tb-list nk-tb-ulist">
                                                            <!-- Header -->
                                                            <div class="nk-tb-item nk-tb-head">
                                                                <div class="nk-tb-col"><span class="sub-text"></span></div>
                                                                <div class="nk-tb-col"><span class="sub-text">ID</span></div>
                                                                <div class="nk-tb-col"><span class="sub-text">Nom</span></div>
                                                                <div class="nk-tb-col"><span class="sub-text">Email</span></div>
                                                                <div class="nk-tb-col"><span class="sub-text">Numéro de téléphone</span></div>
                                                                <div class="nk-tb-col"><span class="sub-text">Rôle</span></div>
                                                                <div class="nk-tb-col"><span class="sub-text">Statut</span></div>
                                                                <div class="nk-tb-col nk-tb-col-tools"><span class="sub-text">Actions</span></div>
                                                            </div>
                                                            <!-- Rows -->
                                                            <?php foreach ($users as $user): ?>
                                                                <div class="nk-tb-item">
                                                                    <div class="nk-tb-col">
                                                                        <?= create_input(
                                                                            "checkbox",               
                                                                            "users[]",                
                                                                            "",                       
                                                                            "",                      
                                                                            "form-check-input",       
                                                                            "",                       
                                                                            $user['custom_id'],       
                                                                            false,                   
                                                                            false)  ?>

                                                                    </div>
                                                                    <div class="nk-tb-col">
                                                                        <?= htmlspecialchars($user['custom_id']) ?>
                                                                    </div>
                                                                    <div class="nk-tb-col">
                                                                        <?= htmlspecialchars($user['name']) ?>
                                                                    </div>
                                                                    <div class="nk-tb-col">
                                                                        <?= htmlspecialchars($user['email']) ?>
                                                                    </div>
                                                                    <div class="nk-tb-col">
                                                                        <?= htmlspecialchars($user['telephone'] ?? "Non défini") ?>
                                                                    </div>
                                                                    <div class="nk-tb-col">
                                                                        <?= htmlspecialchars($user['role_name'] ?? "Inconnu") ?>
                                                                    </div>
                                                                    <div class="nk-tb-col">
                                                                        <?php if ($user['status'] === 'active'): ?>
                                                                            <span class="badge rounded-pill bg-success">Actif</span>
                                                                        <?php else: ?>
                                                                            <span class="badge rounded-pill bg-danger">Inactif</span>
                                                                        <?php endif; ?>
                                                                    </div>

                                                                    <div class="nk-tb-col nk-tb-col-tools">
                                                                        <ul class="nk-tb-actions gx-1">
                                                                            <!-- Bouton ouvrir modal -->
                                                                            <li>
                                                                                <a href="#" 
                                                                                class="btn btn-trigger btn-icon" 
                                                                                data-bs-toggle="modal" 
                                                                                data-bs-target="#editUserModal<?= $user['custom_id'] ?>" 
                                                                                title="Modifier">
                                                                                    <em class="icon ni ni-edit"></em>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a href="utilisateurs.php?action=delete&id=<?= urlencode($user['custom_id']) ?>" 
                                                                                class="btn btn-trigger btn-icon text-danger" 
                                                                                onclick="return confirm('Supprimer cet utilisateur ?')" 
                                                                                title="Supprimer">
                                                                                    <em class="icon ni ni-trash"></em>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a href="edit-rights.php?id=<?= urlencode($user['custom_id']) ?>" 
                                                                                class="btn btn-trigger btn-icon text-warning" 
                                                                                title="Gérer les droits">
                                                                                    <em class="icon ni ni-lock-alt"></em>
                                                                                </a>
                                                                            </li>
                                                                            <li>
                                                                                <a href="utilisateurs.php?action=toggle&id=<?= urlencode($user['custom_id']) ?>" 
                                                                                class="btn btn-trigger btn-icon <?= $user['status'] === 'active' ? 'text-success' : 'text-muted' ?>" 
                                                                                title="<?= $user['status'] === 'active' ? 'Désactiver' : 'Activer' ?>">
                                                                                    <em class="icon ni ni-power"></em>
                                                                                </a>
                                                                            </li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>


                                                            <!-- ========================
                                                                MODALS POUR CHAQUE USER
                                                            ========================= -->
                                                            <?php foreach ($users as $user): ?>
                                                            <div class="modal fade" id="editUserModal<?= $user['custom_id'] ?>" tabindex="-1">
                                                                <div class="modal-dialog" role="document">
                                                                    <div class="modal-content">
                                                                        <form action="utilisateurs.php" method="POST">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title">Modifier utilisateur</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <input type="hidden" name="user_id" value="<?= htmlspecialchars($user['custom_id']) ?>">

                                                                                <div class="form-group">
                                                                                    <label for="edit_name<?= $user['custom_id'] ?>">Nom</label>
                                                                                    <?= create_input(
                                                                                        "text", 
                                                                                        "name", 
                                                                                        "edit_name" . $user['custom_id'], 
                                                                                        "", 
                                                                                        "form-control", 
                                                                                        "", 
                                                                                        $user['name'] ?? "", 
                                                                                        true
                                                                                    ) ?>

                                                                                </div>

                                                                                <div class="form-group">
                                                                                    <label for="edit_email<?= $user['custom_id'] ?>">Email</label>
                                                                                    <input type="email" 
                                                                                        class="form-control" 
                                                                                        id="edit_email<?= $user['custom_id'] ?>" 
                                                                                        name="email" 
                                                                                        value="<?= htmlspecialchars($user['email']) ?>" required>

                                                                                </div>

                                                                                <div class="form-group">
                                                                                    <label for="edit_tel<?= $user['custom_id'] ?>">Téléphone</label>
                                                                                    <input type="tel" 
                                                                                        class="form-control" 
                                                                                        id="edit_phone<?= $user['custom_id'] ?>" 
                                                                                        name="telephone" 
                                                                                        value="<?= htmlspecialchars($user['telephone']) ?>">

                                                                                </div>

                                                                                <div class="form-group">
                                                                                    <label for="edit_role<?= $user['custom_id'] ?>">Rôle</label>
                                                                                    <?php 
                                                                                    echo create_select(
                                                                                        "role", 
                                                                                        "edit_role".$user['custom_id'], 
                                                                                        "form-control", 
                                                                                        "", 
                                                                                        true, 
                                                                                        false, 
                                                                                        false, 
                                                                                        $roles, 
                                                                                        $user['role_id'], 
                                                                                        "Choisir un rôle"
                                                                                    ); 
                                                                                    ?>
                                                                                </div>

                                                                            </div>
                                                                            <div class="modal-footer">
                                                                                <button type="submit" name="update_user" class="btn btn-primary">Enregistrer</button>
                                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <?php endforeach; ?>

                                                        </div>
                                                    </div>
                                                
                                                <!-- .card-inner -->
                                                <div class="card-inner">
                                                    <div class="nk-block-between-md g-3">
                                                        <div class="g">
                                                            <ul class="pagination justify-content-center justify-content-md-start">
                                                                <!-- Prev -->
                                                                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                                                                    <a class="page-link" href="?page=<?= max(1, $currentPage - 1) ?>">Prev</a>
                                                                </li>

                                                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                                    <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                                                    </li>
                                                                <?php endfor; ?>

                                                                <!-- Next -->
                                                                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                                                                    <a class="page-link" href="?page=<?= min($totalPages, $currentPage + 1) ?>">Next</a>
                                                                </li>
                                                            </ul>
                                                        </div>

                                                        <!-- Info page -->
                                                        <div class="g">
                                                            <div class="pagination-goto d-flex justify-content-center justify-content-md-start gx-3">
                                                                <div>Page <?= $currentPage ?> of <?= $totalPages ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>                                       
                                            </div><!-- .card-inner-group -->
                                        </div><!-- .card -->
                                    
                                </div><!-- .nk-block -->
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
    <script src="./assets/js/bundle.js?ver=3.2.3"></script>
    <script src="./assets/js/scripts.js?ver=3.2.3"></script>
    <script src="./assets/js/libs/datatable-btns.js?ver=3.2.3"></script>
</body>

</html>
______________________
 <!-- Filtres -->
                                <ul class="nk-block-tools g-3">
                                    <li>
                                        <div class="dropdown">
                                            <a href="#" class="btn btn-trigger btn-icon dropdown-toggle" data-bs-toggle="dropdown">
                                                <em class="icon ni ni-filter-alt"></em>
                                            </a>
                                            <div class="filter-wg dropdown-menu dropdown-menu-xl dropdown-menu-end">
                                                <div class="dropdown-head">
                                                    <span class="sub-title dropdown-title">Filtres</span>
                                                </div>
                                                <div class="dropdown-body dropdown-body-rg">
                                                    <<form method="get" action="utilisateurs.php" class="row gx-6 gy-3 p-2">
                                                        <div class="col-6">
                                                            <label class="overline-title overline-title-alt">Rôle</label>
                                                            <select name="role" class="form-select js-select2">
                                                                <option value="">Tous</option>
                                                                <?php foreach ($roles as $id => $libelle): ?>
                                                                    <option value="<?= $id ?>" <?= ($filterRole == $id) ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($libelle) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
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