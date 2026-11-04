<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('employee');

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel pracownika — Sharp Society Barber</title>
</head>
<body>

    <h1>Panel pracownika</h1>
    <p>Masz uprawnienia pracownika.</p>

    <a href="../profile.php">Mój profil</a>

</body>
</html>
