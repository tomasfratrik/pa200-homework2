<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

$appName = 'Azure PaaS Todo App';
$flashMessage = null;
$todos = [];
$stats = [
    'total' => 0,
    'completed' => 0,
    'pending' => 0,
];

try {
    $config = appConfig();
    $appName = is_string($config['app_name'] ?? null) ? $config['app_name'] : $appName;
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();

        $action = $_POST['action'] ?? '';
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($action === 'add') {
            $title = trim((string) ($_POST['title'] ?? ''));

            if ($title === '') {
                flash('error', 'Task title cannot be empty.');
                redirectToHome();
            }

            $titleLength = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);

            if ($titleLength > 255) {
                flash('error', 'Task title must be 255 characters or less.');
                redirectToHome();
            }

            $statement = $pdo->prepare('INSERT INTO todos (title) VALUES (:title)');
            $statement->execute(['title' => $title]);
            flash('success', 'Task created.');
            redirectToHome();
        }

        if ($id === false || $id === null) {
            flash('error', 'Invalid task ID.');
            redirectToHome();
        }

        if ($action === 'toggle') {
            $statement = $pdo->prepare('UPDATE todos SET is_done = NOT is_done WHERE id = :id');
            $statement->execute(['id' => $id]);
            flash('success', 'Task updated.');
            redirectToHome();
        }

        if ($action === 'delete') {
            $statement = $pdo->prepare('DELETE FROM todos WHERE id = :id');
            $statement->execute(['id' => $id]);
            flash('success', 'Task deleted.');
            redirectToHome();
        }

        flash('error', 'Unknown action.');
        redirectToHome();
    }

    $todos = $pdo->query(
        'SELECT id, title, is_done, created_at
         FROM todos
         ORDER BY is_done ASC, created_at DESC, id DESC'
    )->fetchAll();

    $stats['total'] = count($todos);

    foreach ($todos as $todo) {
        if ((int) $todo['is_done'] === 1) {
            $stats['completed']++;
        }
    }

    $stats['pending'] = $stats['total'] - $stats['completed'];
    $flashMessage = pullFlash();
} catch (Throwable $throwable) {
    http_response_code(500);
    $fatalError = $throwable->getMessage();
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($appName) ?></title>
    <link rel="stylesheet" href="<?= h(assetUrl('styles.css')) ?>">
</head>
<body>
    <main class="layout">
        <section class="hero">
            <p class="eyebrow">Azure PaaS Assignment</p>
            <h1><?= h($appName) ?></h1>
            <p class="lead">Minimal PHP application connected to Azure Database for MySQL and static assets in Azure Blob Storage.</p>
        </section>

        <?php if (isset($fatalError)): ?>
            <section class="panel error-panel">
                <h2>Application error</h2>
                <p><?= h($fatalError) ?></p>
                <p>Check the App Service environment variables, database connectivity, and whether the schema from `sql/schema.sql` was imported.</p>
            </section>
        <?php else: ?>
            <?php if ($flashMessage !== null): ?>
                <section class="flash flash-<?= h($flashMessage['type']) ?>">
                    <?= h($flashMessage['message']) ?>
                </section>
            <?php endif; ?>

            <section class="stats">
                <article class="stat-card">
                    <span class="stat-label">Total</span>
                    <strong><?= h((string) $stats['total']) ?></strong>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Pending</span>
                    <strong><?= h((string) $stats['pending']) ?></strong>
                </article>
                <article class="stat-card">
                    <span class="stat-label">Completed</span>
                    <strong><?= h((string) $stats['completed']) ?></strong>
                </article>
            </section>

            <section class="panel">
                <h2>Add a task</h2>
                <form method="post" class="task-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="add">
                    <label class="sr-only" for="title">Task title</label>
                    <input id="title" name="title" type="text" maxlength="255" placeholder="Prepare Azure screenshots" required>
                    <button type="submit">Add task</button>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2>Tasks</h2>
                    <span><?= h((string) $stats['total']) ?> item(s)</span>
                </div>

                <?php if ($stats['total'] === 0): ?>
                    <p class="empty-state">No tasks yet. Add one above to verify the database connection.</p>
                <?php else: ?>
                    <ul class="task-list">
                        <?php foreach ($todos as $todo): ?>
                            <li class="task-item <?= (int) $todo['is_done'] === 1 ? 'done' : '' ?>">
                                <div class="task-meta">
                                    <h3><?= h($todo['title']) ?></h3>
                                    <p>Created at <?= h($todo['created_at']) ?></p>
                                </div>
                                <div class="task-actions">
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?= h((string) $todo['id']) ?>">
                                        <button type="submit" class="secondary">
                                            <?= (int) $todo['is_done'] === 1 ? 'Mark pending' : 'Mark done' ?>
                                        </button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= h((string) $todo['id']) ?>">
                                        <button type="submit" class="danger">Delete</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
