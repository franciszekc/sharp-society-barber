<?php

require_once '../config/database.php';
require_once '../includes/auth.php';

requireLogin();
refreshUserSession($pdo);
requireRole('admin');

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Panel administratora</title>
</head>
<body>
    <h1>Panel administratora</h1>
    <p>Masz uprawnienia administratora.</p>
    <a href="profile.php">Powrót do profilu</a>
</body>
</html>
