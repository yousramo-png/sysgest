<?php
// fonction pour la creation des inputs
function create_input(
    $type = "text",
    $name = "",
    $id = "",
    $placeholder = "",
    $class = "",
    $style = "",
    $value = "",
    $required = false,
    $checked = false,
    $disabled = false,
    $readonly = false,
    $multiple = false
){
    
    $type = !empty($type) ? htmlspecialchars($type, ENT_QUOTES, 'UTF-8') : "text";

    $input = "<input type=\"$type\"";

    if (!empty($name))       $input .= " name=\"" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($id))         $input .= " id=\"" . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($placeholder))$input .= " placeholder=\"" . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($class))      $input .= " class=\"" . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($style))      $input .= " style=\"" . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($value) || $value === "0") {
        $input .= " value=\"" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\"";
    }

    if ($required) $input .= " required";
    if ($checked)  $input .= " checked";
    if ($disabled) $input .= " disabled";
    if ($readonly) $input .= " readonly";
    if ($multiple) $input .= " multiple";

    $input .= " />"; 
    return $input;
}

// fonctions pour la creation des select
function create_select(
    $name = "",
    $id = "",
    $class = "",
    $style = "",
    $required = false,
    $disabled = false,
    $multiple = false,
    $options = [],
    $selected = [],
    $defaultOption = "" 
) {
    if (!is_array($selected)) $selected = [$selected];

    $select = "<select";
    if (!empty($name))  $select .= " name=\"" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($id))    $select .= " id=\"" . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($class)) $select .= " class=\"" . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($style)) $select .= " style=\"" . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . "\"";

    if ($required) $select .= " required";
    if ($disabled) $select .= " disabled";
    if ($multiple) $select .= " multiple";

    $select .= ">";

    // Option par défaut
    if (!empty($defaultOption)) {
        $select .= "<option value=\"\" disabled selected>" . htmlspecialchars($defaultOption, ENT_QUOTES, 'UTF-8') . "</option>";
    }

    foreach ($options as $value => $label) {
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $isSelected = in_array($value, $selected) ? " selected" : "";
        $select .= "<option value=\"$value\"$isSelected>$label</option>";
    }

    $select .= "</select>";
    return $select;
}


// fonction pour la creation des textarea
function create_textarea($name = "", $id = "", $class = "", $style = "", $placeholder = "", $rows = 4, $cols = 50, $required = false, $disabled = false, $readonly = false, $value = "") {
    $textarea = "<textarea";

    if (!empty($name))        $textarea .= " name=\"" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($id))          $textarea .= " id=\"" . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($class))       $textarea .= " class=\"" . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($style))       $textarea .= " style=\"" . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($placeholder)) $textarea .= " placeholder=\"" . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . "\"";
    if (!empty($rows))        $textarea .= " rows=\"" . (int)$rows . "\"";
    if (!empty($cols))        $textarea .= " cols=\"" . (int)$cols . "\"";

    if ($required) $textarea .= " required";
    if ($disabled) $textarea .= " disabled";
    if ($readonly) $textarea .= " readonly";

    $textarea .= ">";
    $textarea .= htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $textarea .= "</textarea>";

    return $textarea;
}

// Supprimer un utilisateur
function deleteUser($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM " . __DB_PREFIX__ . "users WHERE id = :id");
    return $stmt->execute([':id' => $id]);
}

// Récupérer le statut actuel d’un utilisateur
function getUserStatus($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT status FROM " . __DB_PREFIX__ . "users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetchColumn();
}

// Activer/Désactiver un utilisateur (toggle)
function toggleUserStatus($id) {
    global $pdo;
    $status = getUserStatus($id);
    if ($status !== false) {
        $newStatus = ($status === 'active') ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE " . __DB_PREFIX__ . "users SET status = :status WHERE id = :id");
        return $stmt->execute([
            ':status' => $newStatus,
            ':id'     => $id
        ]);
    }
    return false;
}
