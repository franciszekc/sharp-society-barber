<?php

// Podsumowanie i nawigacja panelu administratora.

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Liczniki użytkowników, usług i rezerwacji.
$stats = [
    'clients' => (int) $pdo->query(
        "SELECT COUNT(*) FROM users WHERE role = 'client'"
    )->fetchColumn(),

    'employees' => (int) $pdo->query(
        "SELECT COUNT(*) FROM employees WHERE active = 1"
    )->fetchColumn(),

    'services' => (int) $pdo->query(
        "SELECT COUNT(*) FROM services WHERE active = 1"
    )->fetchColumn(),

    'reservations' => (int) $pdo->query(
        "SELECT COUNT(*) FROM reservations"
    )->fetchColumn(),

    'today' => (int) $pdo->query(
        "SELECT COUNT(*) FROM reservations
         WHERE reservation_date = CURDATE()
         AND status != 'cancelled'"
    )->fetchColumn()
];

$name = htmlspecialchars(
    $_SESSION['user_name'],
    ENT_QUOTES,
    'UTF-8'
);

?>
<!-- Widok strony: Podsumowanie i nawigacja panelu administratora -->
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel administratora — Sharp Society Barber</title>
    <link rel="stylesheet" href="../css/style-admin-index.css">
    <link rel="stylesheet" href="../css/style-admin-nav.css">
</head>
<body>

    <header class="topbar">
        <h1>Sharp Society Barber</h1>

        <?php require '../../includes/admin-nav.php'; ?>

    </header>

    <!-- Zawartość główna panelu. -->

    <main class="container">

        <h2>Panel administratora</h2>
        <p>Witaj, <?= $name ?>!</p>

        <section>
            <h3>Podsumowanie</h3>

            <div class="stats">
                <div class="stat">
                    <span>Klienci</span>
                    <strong><?= $stats['clients'] ?></strong>
                </div>

                <div class="stat">
                    <span>Aktywni pracownicy</span>
                    <strong><?= $stats['employees'] ?></strong>
                </div>

                <div class="stat">
                    <span>Aktywne usługi</span>
                    <strong><?= $stats['services'] ?></strong>
                </div>

                <div class="stat">
                    <span>Wszystkie rezerwacje</span>
                    <strong><?= $stats['reservations'] ?></strong>
                </div>

                <div class="stat">
                    <span>Dzisiejsze wizyty</span>
                    <strong><?= $stats['today'] ?></strong>
                </div>
            </div>
        </section>

        <section>
            <h3>Zarządzanie systemem</h3>

            <div class="modules">
                <div class="module">
                    <h4>Kategorie</h4>
                    <p>Dodawanie, edycja i usuwanie kategorii usług.</p>
                    <a href="categories.php">Zarządzaj</a>
                </div>

                <div class="module">
                    <h4>Usługi</h4>
                    <p>Zarządzanie ofertą, cenami i czasem usług.</p>
                    <a href="services.php">Zarządzaj</a>
                </div>

                <div class="module">
                    <h4>Pracownicy</h4>
                    <p>Kontrola pracowników i przypisanych usług.</p>
                    <a href="employees.php">Zarządzaj</a>
                </div>


                <div class="module">
                    <h4>Dostępność</h4>
                    <p>Godziny i dni pracy barberów.</p>
                    <a href="availability.php">Zarządzaj</a>
                </div>


                <div class="module">
                    <h4>Rezerwacje</h4>
                    <p>Lista wizyt i zarządzanie statusami.</p>
                    <a href="reservations.php">Zarządzaj</a>
                </div>


                <div class="module">
                    <h4>Użytkownicy</h4>
                    <p>Przegląd kont użytkowników.</p>
                    <a href="users.php">Zarządzaj</a>
                </div>
            </div>
        </section>

    </main>

</body>
</html>
