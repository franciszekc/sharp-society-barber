<?php
// Nawigacja administratora: moduły, profil i bezpieczne wylogowanie.
?>
<nav class="admin-nav">
    <a href="index.php">Panel administratora</a>
    <a href="categories.php">Kategorie</a>
    <a href="services.php">Usługi</a>
    <a href="employees.php">Pracownicy</a>
    <a href="employee-services.php">Usługi pracowników</a>
    <a href="availability.php">Dostępność</a>
    <a href="reservations.php">Rezerwacje</a>
    <a href="users.php">Użytkownicy</a>
    <a href="../profile.php">Mój profil</a>
    <form action="../logout.php" method="POST" class="admin-logout-form">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="admin-logout-button">Wyloguj</button>
    </form>
</nav>
