
<?php

require_once '../../config/database.php';
require_once '../../includes/auth.php';

requireLogin();
refreshUserSession($pdo);
requireRole('admin');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = $_SESSION['category_success'] ?? '';
unset($_SESSION['category_success']);

$editCategory = null;
$name = '';
$description = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !is_string($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
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

        $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
        $description = trim(is_string($_POST['description'] ?? null) ? $_POST['description'] : '');

        if ($name === '') {
            $errors[] = 'Nazwa kategorii jest wymagana.';
        } elseif (mb_strlen($name) > 100) {
            $errors[] = 'Nazwa kategorii może mieć maksymalnie 100 znaków.';
        }

        if (mb_strlen($description) > 5000) {
            $errors[] = 'Opis jest zbyt długi.';
        }

        if ($action === 'edit' && $id === false) {
            $errors[] = 'Nieprawidłowy identyfikator kategorii.';
        }

        if (empty($errors)) {

            if ($action === 'add') {
                $stmt = $pdo->prepare(
                    'INSERT INTO service_categories (name, description)
                     VALUES (?, ?)'
                );
                $stmt->execute([$name, $description]);

                $_SESSION['category_success'] = 'Dodano kategorię.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE service_categories
                     SET name = ?, description = ?
                     WHERE id = ?'
                );
                $stmt->execute([$name, $description, $id]);

                $_SESSION['category_success'] = 'Zapisano zmiany kategorii.';
            }

            header('Location: categories.php');
            exit;
        }

        if ($action === 'edit' && $id !== false) {
            $editCategory = [
                'id' => $id,
                'name' => $name,
                'description' => $description
            ];
        }

    } elseif ($action === 'delete') {

        if ($id === false) {
            $errors[] = 'Nieprawidłowy identyfikator kategorii.';
        } else {

            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM services WHERE category_id = ?'
            );
            $stmt->execute([$id]);

            if ((int) $stmt->fetchColumn() > 0) {
                $errors[] = 'Nie można usunąć kategorii, do której przypisano usługi.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'DELETE FROM service_categories WHERE id = ?'
                    );
                    $stmt->execute([$id]);

                    if ($stmt->rowCount() === 1) {
                        $_SESSION['category_success'] = 'Usunięto kategorię.';
                    } else {
                        $_SESSION['category_success'] = 'Kategoria nie istnieje.';
                    }

                    header('Location: categories.php');
                    exit;

                } catch (PDOException $exception) {
                    $errors[] = 'Nie można usunąć kategorii. Sprawdź jej powiązania.';
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

    if ($id === false) {
        $errors[] = 'Nieprawidłowy identyfikator kategorii.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, description
             FROM service_categories
             WHERE id = ?'
        );
        $stmt->execute([$id]);
        $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$editCategory) {
            $errors[] = 'Nie znaleziono kategorii.';
        }
    }
}

$categories = $pdo->query(
    'SELECT
        c.id,
        c.name,
        c.description,
        COUNT(s.id) AS services_count
     FROM service_categories c
     LEFT JOIN services s ON s.category_id = c.id
     GROUP BY c.id, c.name, c.description
     ORDER BY c.name'
)->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorie — Sharp Society Barber</title>
    <link rel="stylesheet" href="../css/style-categories.css">
</head>
<body>

    <header class="topbar">
        <h1>Sharp Society Barber</h1>
        <nav>
            <a href="../admin.php">Panel administratora</a>
            <a href="categories.php">Kategorie</a>
        </nav>
    </header>

    <main class="container">

        <h2>Zarządzanie kategoriami</h2>

        <?php foreach ($errors as $error): ?>
            <p class="message-error"><?= e($error) ?></p>
        <?php endforeach; ?>

        <?php if ($success !== ''): ?>
            <p class="message-success"><?= e($success) ?></p>
        <?php endif; ?>

        <section class="card">

            <h3>
                <?= $editCategory ? 'Edytuj kategorię' : 'Dodaj kategorię' ?>
            </h3>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editCategory ? 'edit' : 'add' ?>"
                >

                <?php if ($editCategory): ?>
                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int) $editCategory['id'] ?>"
                    >
                <?php endif; ?>

                <label for="name">Nazwa kategorii</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    maxlength="100"
                    required
                    value="<?= e($editCategory['name'] ?? $name) ?>"
                >

                <label for="description">Opis</label>
                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    maxlength="5000"
                ><?= e($editCategory['description'] ?? $description) ?></textarea>

                <button type="submit">
                    <?= $editCategory ? 'Zapisz zmiany' : 'Dodaj kategorię' ?>
                </button>

                <?php if ($editCategory): ?>
                    <a class="cancel-link" href="categories.php">
                        Anuluj edycję
                    </a>
                <?php endif; ?>

            </form>

        </section>

        <section class="card">

            <h3>Lista kategorii</h3>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nazwa</th>
                            <th>Opis</th>
                            <th>Usługi</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?= (int) $category['id'] ?></td>
                                <td><?= e($category['name']) ?></td>
                                <td><?= e($category['description'] ?? '') ?></td>
                                <td><?= (int) $category['services_count'] ?></td>
                                <td class="actions">

                                    <a href="?edit=<?= (int) $category['id'] ?>">
                                        Edytuj
                                    </a>

                                    <?php if ((int) $category['services_count'] === 0): ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Czy na pewno usunąć tę kategorię?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $category['id'] ?>"
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

                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="5">Brak kategorii.</td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </section>

    </main>

</body>
</html>
