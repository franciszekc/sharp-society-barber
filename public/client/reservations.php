
<?php

// Lista własnych wizyt klienta, historia i formularze anulowania.

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('client');


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

date_default_timezone_set('Europe/Warsaw');

// Kodowanie wartości przed wyświetleniem w HTML.
function escape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Tłumaczenie kodów statusu na etykiety.
function statusName($status): string
{
    $statuses = [
        'pending' => 'Oczekująca',
        'confirmed' => 'Potwierdzona',
        'completed' => 'Zakończona',
        'cancelled' => 'Anulowana'
    ];

    return $statuses[$status] ?? $status;
}

// Rezerwacje pobierane wyłącznie dla zalogowanego klienta.
$stmt = $pdo->prepare(
    "SELECT
        r.id,
        r.reservation_date,
        r.start_time,
        r.end_time,
        r.status,
        r.comment,
        s.name AS service_name,
        s.price,
        s.duration,
        u.name AS employee_name,
        u.surname AS employee_surname
    FROM reservations r
    INNER JOIN services s ON s.id = r.service_id
    INNER JOIN employees e ON e.id = r.employee_id
    INNER JOIN users u ON u.id = e.user_id
    WHERE r.user_id = ?
    ORDER BY r.reservation_date DESC, r.start_time DESC"
);

$stmt->execute([getUserId()]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Podział wizyt na przyszłe i historyczne.
$upcoming = [];
$history = [];

$now = new DateTimeImmutable();

foreach ($reservations as $reservation) {

    $endDate = new DateTimeImmutable(
        $reservation['reservation_date'] . ' ' .
        $reservation['end_time']
    );

    if (
        in_array($reservation['status'], ['pending', 'confirmed'], true)
        && $endDate > $now
    ) {
        $upcoming[] = $reservation;
    } else {
        $history[] = $reservation;
    }
}

usort($upcoming, function ($a, $b) {
    return strcmp(
        $a['reservation_date'] . $a['start_time'],
        $b['reservation_date'] . $b['start_time']
    );
});

?>

<!-- Widok strony: Lista własnych wizyt klienta, historia i formularze anulowania -->
<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Moje rezerwacje - Sharp Society Barber</title>

    <link rel="stylesheet" href="../css/style-client-reservations.css">
</head>

<body>

    <header class="topbar">

        <div class="container header-content">

            <h2>Sharp Society Barber</h2>

            <nav>
                <a href="index.php">Panel główny</a>
                <a href="services.php">Zarezerwuj wizytę</a>
                <a href="reservations.php">Moje rezerwacje</a>
                <a href="../profile.php">Mój profil</a>
                
                <form action="../logout.php" method="POST" class="logout-form">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <button type="submit" class="logout-button">
                        Wyloguj
                    </button>
                </form>

            </nav>

        </div>

    </header>

    <!-- Zawartość główna panelu. -->

    <main class="container">
        <?php if (isset($_SESSION['reservation_success'])): ?>

            <div class="alert alert-success">
                <?= escape($_SESSION['reservation_success']) ?>
            </div>

            <?php unset($_SESSION['reservation_success']); ?>

        <?php endif; ?>

        <?php if (isset($_SESSION['reservation_error'])): ?>

            <div class="alert alert-error">
                <?= escape($_SESSION['reservation_error']) ?>
            </div>

            <?php unset($_SESSION['reservation_error']); ?>

        <?php endif; ?>

        <div class="page-heading">

            <div>
                <h1>Moje rezerwacje</h1>
                <p>Sprawdź swoje nadchodzące i poprzednie wizyty.</p>
            </div>

            <a href="services.php" class="book-button">
                + Nowa rezerwacja
            </a>

        </div>

        <div class="stats">

            <div class="stat-card">
                <h3>Nadchodzące wizyty</h3>
                <strong><?= count($upcoming) ?></strong>
            </div>

            <div class="stat-card">
                <h3>Historia wizyt</h3>
                <strong><?= count($history) ?></strong>
            </div>

            <div class="stat-card">
                <h3>Wszystkie rezerwacje</h3>
                <strong><?= count($reservations) ?></strong>
            </div>

        </div>

        <section class="section">

            <h2>Nadchodzące wizyty</h2>

            <?php if (empty($upcoming)): ?>

                <div class="empty-state">

                    <h3>Nie masz nadchodzących wizyt</h3>

                    <p>
                        Wybierz usługę i zarezerwuj dogodny termin.
                    </p>

                    <a href="services.php" class="book-button">
                        Zarezerwuj wizytę
                    </a>

                </div>

            <?php else: ?>

                <div class="reservations-grid">

                    <?php foreach ($upcoming as $reservation): ?>

                        <article class="reservation-card">

                            <div class="card-header">

                                <h3>
                                    <?= escape($reservation['service_name']) ?>
                                </h3>

                                <span class="status status-<?= escape($reservation['status']) ?>">
                                    <?= escape(statusName($reservation['status'])) ?>
                                </span>

                            </div>

                            <div class="card-details">

                                <p>
                                    <strong>Barber:</strong>
                                    <?= escape($reservation['employee_name']) ?>
                                    <?= escape($reservation['employee_surname']) ?>
                                </p>

                                <p>
                                    <strong>Data:</strong>
                                    <?= date('d.m.Y', strtotime($reservation['reservation_date'])) ?>
                                </p>

                                <p>
                                    <strong>Godzina:</strong>
                                    <?= escape(substr($reservation['start_time'], 0, 5)) ?>
                                    -
                                    <?= escape(substr($reservation['end_time'], 0, 5)) ?>
                                </p>

                                <p>
                                    <strong>Czas trwania:</strong>
                                    <?= (int) $reservation['duration'] ?> min
                                </p>

                                <p>
                                    <strong>Cena:</strong>
                                    <?= number_format((float) $reservation['price'], 2, ',', ' ') ?> zł
                                </p>

                                <?php if (!empty($reservation['comment'])): ?>

                                    <p>
                                        <strong>Uwagi:</strong>
                                        <?= escape($reservation['comment']) ?>
                                    </p>

                                <?php endif; ?>

                            </div>
                            
                            <?php

                            $startDate = new DateTimeImmutable(
                                $reservation['reservation_date'] . ' ' .
                                $reservation['start_time']
                            );

                            $canCancel = in_array(
                                $reservation['status'],
                                ['pending', 'confirmed'],
                                true
                            ) && $startDate > new DateTimeImmutable();

                            ?>

                            <?php if ($canCancel): ?>

                                <form
                                    method="POST"
                                    action="cancel-reservation.php"
                                    onsubmit="return confirm('Czy na pewno chcesz anulować wizytę?');"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= escape($_SESSION['csrf_token']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="reservation_id"
                                        value="<?= (int) $reservation['id'] ?>"
                                    >

                                    <button type="submit" class="cancel-button">
                                        Anuluj rezerwację
                                    </button>

                                </form>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

        <section class="section">

            <h2>Historia wizyt</h2>

            <?php if (empty($history)): ?>

                <div class="empty-state">
                    <p>Historia wizyt jest pusta.</p>
                </div>

            <?php else: ?>

                <div class="reservations-grid">

                    <?php foreach ($history as $reservation): ?>

                        <article class="reservation-card">

                            <div class="card-header">

                                <h3>
                                    <?= escape($reservation['service_name']) ?>
                                </h3>

                                <span class="status status-<?= escape($reservation['status']) ?>">
                                    <?= escape(statusName($reservation['status'])) ?>
                                </span>

                            </div>

                            <div class="card-details">

                                <p>
                                    <strong>Barber:</strong>
                                    <?= escape($reservation['employee_name']) ?>
                                    <?= escape($reservation['employee_surname']) ?>
                                </p>

                                <p>
                                    <strong>Data:</strong>
                                    <?= date('d.m.Y', strtotime($reservation['reservation_date'])) ?>
                                </p>

                                <p>
                                    <strong>Godzina:</strong>
                                    <?= escape(substr($reservation['start_time'], 0, 5)) ?>
                                    -
                                    <?= escape(substr($reservation['end_time'], 0, 5)) ?>
                                </p>

                                <p>
                                    <strong>Cena:</strong>
                                    <?= number_format((float) $reservation['price'], 2, ',', ' ') ?> zł
                                </p>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>

</body>
</html>
