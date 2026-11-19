
<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('client');

date_default_timezone_set('Europe/Warsaw');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reservations.php');
    exit;
}

if (
    !isset($_SESSION['csrf_token']) ||
    !isset($_POST['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['reservation_error'] = 'Nieprawidłowy formularz.';
    header('Location: reservations.php');
    exit;
}

$reservationId = filter_var(
    $_POST['reservation_id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($reservationId === false || $reservationId <= 0) {
    $_SESSION['reservation_error'] = 'Nieprawidłowa rezerwacja.';
    header('Location: reservations.php');
    exit;
}

$now = new DateTimeImmutable();
$today = $now->format('Y-m-d');
$currentTime = $now->format('H:i:s');

$stmt = $pdo->prepare(
    "UPDATE reservations
     SET status = 'cancelled'
     WHERE id = ?
       AND user_id = ?
       AND status IN ('pending', 'confirmed')
       AND (
           reservation_date > ?
           OR (
               reservation_date = ?
               AND start_time > ?
           )
       )"
);

$stmt->execute([
    $reservationId,
    getUserId(),
    $today,
    $today,
    $currentTime
]);

if ($stmt->rowCount() === 1) {
    $_SESSION['reservation_success'] = 'Rezerwacja została anulowana.';
} else {
    $_SESSION['reservation_error'] = 'Nie można anulować tej rezerwacji.';
}

header('Location: reservations.php');
exit;
