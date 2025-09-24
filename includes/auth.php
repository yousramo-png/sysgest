<?php
session_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in():bool{
    return isset($_SESSION['user_id']);
}

function require_login():void{
    if(!is_logged_in()){
        header("Location: ../manager/login.php");
        exit;
    }
}

function has_role(int $role_id):bool{
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] == $role_id ;
}

function require_role(array $roles):void{
    if(!in_array($_SESSION['role_id'] ,$roles)){
        header("Location: ../manager/error.php");
        exit;
    }
}