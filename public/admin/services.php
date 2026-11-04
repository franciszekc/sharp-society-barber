
<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin('../login.php');
refreshUserSession($pdo, '../login.php');
requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = $_SESSION['service_success'] ?? '';
unset($_SESSION['service_success']);

$form = [
    'id' => '',
    'category_id' => '',
    'name' => '',
    'description' => '',
    'duration' => '',
    'price' => '',
    'active' => 1
];

$editing = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Nieprawidłowy token formularza.');
    }

    $action = $_POST['action'] ?? '';

    $id = filter_var(
        $_POST['id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($action === 'add' || $action === 'edit') {

        $editing = $action === 'edit';

        $form = [
            'id' => $id ?: '',
            'category_id' => $_POST['category_id'] ?? '',
            'name' => trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : ''),
            'description' => trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : ''),
            'duration' => $_POST['duration'] ?? '',
            'price' => str_replace(',', '.', trim(is_string($_POST['price'] ?? null) ? $_POST['price'] : '')),
            'active' => isset($_POST['active']) ? 1 : 0
        ];

        $categoryId = filter_var(
            $form['category_id'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $duration = filter_var(
            $form['duration'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 480]]
        );

        if ($editing && !$id) {
            $errors[] = 'Nieprawidłowy identyfikator usługi.';
        }

        if ($form['name'] === '' || mb_strlen($form['name']) > 100) {
            $errors[] = 'Nazwa usługi musi mieć od 1 do 100 znaków.';
        }

        if (mb_strlen($form['description']) > 5000) {
            $errors[] = 'Opis jest zbyt długi.';
        }

        if (!$categoryId) {
            $errors[] = 'Wybierz kategorię.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM service_categories WHERE id = ?'
            );
            $stmt->execute([$categoryId]);

            if ((int) $stmt->fetchColumn() === 0) {
                $errors[] = 'Wybrana kategoria nie istnieje.';
            }
        }

        if ($duration === false) {
            $errors[] = 'Czas trwania musi wynosić od 1 do 480 minut.';
        }

        if (
            !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $form['price']) ||
            (float) $form['price'] > 99999999.99
        ) {
            $errors[] = 'Podaj prawidłową cenę od 0 do 99999999,99 zł.';
        }

        if ($editing && $id) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM services WHERE id = ?'
            );
            $stmt->execute([$id]);

            if ((int) $stmt->fetchColumn() === 0) {
                $errors[] = 'Edytowana usługa nie istnieje.';
            }
        }

        if (empty($errors)) {

            if ($action === 'add') {

                $stmt = $pdo->prepare(
                    'INSERT INTO services
                        (category_id, name, description, duration, price, active)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $categoryId,
                    $form['name'],
                    $form['description'],
                    $duration,
                    $form['price'],
                    $form['active']
                ]);

                $_SESSION['service_success'] = 'Dodano usługę.';

            } else {

                $stmt = $pdo->prepare(
                    'UPDATE services
                     SET category_id = ?,
                         name = ?,
                         description = ?,
                         duration = ?,
                         price = ?,
                         active = ?
                     WHERE id = ?'
                );

                $stmt->execute([
                    $categoryId,
                    $form['name'],
                    $form['description'],
                    $duration,
                    $form['price'],
                    $form['active'],
                    $id
                ]);

                $_SESSION['service_success'] = 'Zapisano zmiany usługi.';
            }

            header('Location: services.php');
            exit;
        }

    } elseif ($action === 'toggle' || $action === 'delete') {

        if (!$id) {
            $errors[] = 'Nieprawidłowy identyfikator usługi.';
        } else {

            if ($action === 'toggle') {

                $stmt = $pdo->prepare(
                    'UPDATE services
                     SET active = IF(active = 1, 0, 1)
                     WHERE id = ?'
                );

                $stmt->execute([$id]);

                $_SESSION['service_success'] = $stmt->rowCount()
                    ? 'Zmieniono aktywność usługi.'
                    : 'Nie znaleziono usługi.';

                header('Location: services.php');
                exit;

            } else {

                $stmt = $pdo->prepare(
                    'SELECT
                        (SELECT COUNT(*) FROM reservations WHERE service_id = ?) AS reservations_count,
                        (SELECT COUNT(*) FROM employee_services WHERE service_id = ?) AS employees_count'
                );

                $stmt->execute([$id, $id]);
                $counts = $stmt->fetch(PDO::FETCH_ASSOC);

                if (
                    (int) $counts['reservations_count'] > 0 ||
                    (int) $counts['employees_count'] > 0
                ) {
                    $errors[] = 'Nie można usunąć usługi powiązanej z rezerwacjami lub pracownikami.';
                } else {
                    try {
                        $stmt = $pdo->prepare(
                            'DELETE FROM services WHERE id = ?'
                        );
                        $stmt->execute([$id]);

                        $_SESSION['service_success'] = $stmt->rowCount()
                            ? 'Usunięto usługę.'
                            : 'Nie znaleziono usługi.';

                        header('Location: services.php');
                        exit;

                    } catch (PDOException $exception) {
                        $errors[] = 'Nie można usunąć usługi z powodu istniejących powiązań.';
                    }
                }
            }
        }

    } else {
        $errors[] = 'Nieprawidłowa operacja.';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {

    $id = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$id) {
        $errors[] = 'Nieprawidłowy identyfikator usługi.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM services WHERE id = ?'
        );
        $stmt->execute([$id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($service) {
            $form = $service;
            $editing = true;
        } else {
            $errors[] = 'Nie znaleziono usługi.';
        }
    }
}

$categories = $pdo->query(
    'SELECT id, name
     FROM service_categories
     ORDER BY name'
)->fetchAll(PDO::FETCH_ASSOC);

$services = $pdo->query(
    'SELECT
        s.*,
        c.name AS category_name,
        (SELECT COUNT(*) FROM reservations r WHERE r.service_id = s.id) AS reservations_count,
        (SELECT COUNT(*) FROM employee_services es WHERE es.service_id = s.id) AS employees_count
     FROM services s
     INNER JOIN service_categories c ON c.id = s.category_id
     ORDER BY c.name, s.name'
)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usługi — Sharp Society Barber</title>
    <link rel="stylesheet" href="../css/style-services.css">
    <link rel="stylesheet" href="../css/style-admin-nav.css">
</head>
<body>

    <header class="topbar">
        <h1>Sharp Society Barber</h1>

        <?php require '../../includes/admin_nav.php'; ?>

    </header>

    <main class="container">

        <h2>Zarządzanie usługami</h2>

        <?php foreach ($errors as $error): ?>
            <p class="message-error"><?= escape($error) ?></p>
        <?php endforeach; ?>

        <?php if ($success !== ''): ?>
            <p class="message-success"><?= escape($success) ?></p>
        <?php endif; ?>

        <section class="card">

            <h3><?= $editing ? 'Edytuj usługę' : 'Dodaj usługę' ?></h3>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escape($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editing ? 'edit' : 'add' ?>"
                >

                <?php if ($editing): ?>
                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $form['id'] ?>"
                    >
                <?php endif; ?>

                <label for="name">Nazwa usługi</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    required
                    value="<?= escape($form['name']) ?>"
                >

                <label for="category_id">Kategoria</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Wybierz kategorię</option>

                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?= (int) $category['id'] ?>"
                            <?= (string) $form['category_id'] === (string) $category['id'] ? 'selected' : '' ?>
                        >
                            <?= escape($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="form-row">

                    <div>
                        <label for="duration">Czas trwania (minuty)</label>
                        <input
                            type="number"
                            id="duration"
                            name="duration"
                            min="1"
                            max="480"
                            required
                            value="<?= escape($form['duration']) ?>"
                        >
                    </div>

                    <div>
                        <label for="price">Cena (zł)</label>
                        <input
                            type="number"
                            id="price"
                            name="price"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            required
                            value="<?= escape($form['price']) ?>"
                        >
                    </div>

                </div>

                <label for="description">Opis</label>
                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    maxlength="5000"
                ><?= escape($form['description'] ?? '') ?></textarea>

                <label class="checkbox-label">
                    <input
                        type="checkbox"
                        name="active"
                        value="1"
                        <?= (int) $form['active'] === 1 ? 'checked' : '' ?>
                    >
                    Usługa aktywna
                </label>

                <button type="submit">
                    <?= $editing ? 'Zapisz zmiany' : 'Dodaj usługę' ?>
                </button>

                <?php if ($editing): ?>
                    <a class="cancel-link" href="services.php">
                        Anuluj edycję
                    </a>
                <?php endif; ?>

            </form>

        </section>

        <section class="card">

            <h3>Lista usług</h3>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usługa</th>
                            <th>Kategoria</th>
                            <th>Czas</th>
                            <th>Cena</th>
                            <th>Status</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($services as $service): ?>

                            <tr>
                                <td><?= (int) $service['id'] ?></td>

                                <td>
                                    <strong><?= escape($service['name']) ?></strong>

                                    <?php if (!empty($service['description'])): ?>
                                        <div class="service-description">
                                            <?= escape($service['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td><?= escape($service['category_name']) ?></td>
                                <td><?= (int) $service['duration'] ?> min</td>
                                <td><?= number_format((float) $service['price'], 2, ',', ' ') ?> zł</td>

                                <td>
                                    <span class="<?= $service['active'] ? 'active' : 'inactive' ?>">
                                        <?= $service['active'] ? 'Aktywna' : 'Nieaktywna' ?>
                                    </span>
                                </td>

                                <td class="actions">

                                    <a href="?edit=<?= (int) $service['id'] ?>">
                                        Edytuj
                                    </a>

                                    <form method="POST">
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= escape($_SESSION['csrf_token']) ?>"
                                        >
                                        <input type="hidden" name="action" value="toggle">
                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $service['id'] ?>"
                                        >

                                        <button type="submit" class="toggle-btn">
                                            <?= $service['active'] ? 'Dezaktywuj' : 'Aktywuj' ?>
                                        </button>
                                    </form>

                                    <?php if (
                                        (int) $service['reservations_count'] === 0 &&
                                        (int) $service['employees_count'] === 0
                                    ): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Czy na pewno usunąć tę usługę?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= escape($_SESSION['csrf_token']) ?>"
                                            >
                                            <input type="hidden" name="action" value="delete">
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $service['id'] ?>"
                                            >

                                            <button type="submit" class="delete-btn">
                                                Usuń
                                            </button>
                                        </form>

                                    <?php else: ?>
                                        <span class="disabled">Nie można usunąć</span>
                                    <?php endif; ?>

                                </td>
                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($services)): ?>
                            <tr>
                                <td colspan="7">Brak usług.</td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </section>

    </main>

<script>

    document.addEventListener('DOMContentLoaded', function () {

        const storageKey = 'services_scroll_position';

        const savedPosition = sessionStorage.getItem(storageKey);

        if (savedPosition !== null) {
            sessionStorage.removeItem(storageKey);

            requestAnimationFrame(function () {
                window.scrollTo({
                    top: Number(savedPosition),
                    behavior: 'instant'
                });
            });
        }

        const toggleForms = document.querySelectorAll(
            'form:has(input[name="action"][value="toggle"])'
        );

        toggleForms.forEach(function (form) {

            form.addEventListener('submit', function () {
                sessionStorage.setItem(
                    storageKey,
                    String(window.scrollY)
                );
            });

        });

    });

</script>

</body>
</html>
