<?php

require_once '../config/database.php';
require_once '../includes/auth.php';

requireLogin();
refreshUserSession($pdo);
requireRole('employee');

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Panel pracownika</title>
</head>
<body>
    <h1>Panel pracownika</h1>
    <p>Masz uprawnienia pracownika.</p>
    <a href="profile.php">Powrót do profilu</a>
</body>
</html>
