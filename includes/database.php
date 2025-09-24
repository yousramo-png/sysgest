<?php
$host = 'localhost';
$port = 3306;
$dbname = "sysgest";
$user = "root";
$password = "";

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8";

try{
    $pdo = new PDO(dsn: $dsn,username: $user,password: $password);
    // echo 'Connected Successfuly';
}catch(PDOException $e){
    echo 'Connection Failed' . $e->getMessage();
}