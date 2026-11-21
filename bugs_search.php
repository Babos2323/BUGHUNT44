<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_login();

$user = current_user();

$projects = $pdo->query("SELECT * FROM projects ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT id, login FROM users ORDER BY login")->fetchAll();

$allowedStatuses = ['New', 'Review', 'Confirmed', 'In Progress', 'Fixed', 'Closed', 'Rejected'];
$allowedPriorities = ['Critical', 'High', 'Medium', 'Low'];

$q = trim($_GET['q'] ?? '');
$projectId = (int)($_GET['project_id'] ?? 0);
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$authorId = (int)($_GET['author_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

$allowedSort = [
    'date' => 'b.created_at',
    'priority' => "CASE b.priority WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 ELSE 5 END",
    'status' => 'b.status',
];
$sortKey = $_GET['sort'] ?? 'date';
$sortCol = $allowedSort[$sortKey] ?? 'b.created_at';
$dir = (($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

// Экранируем % и _ для LIKE
if ($q !== '') {
    $where[] = '(b.title LIKE :q OR b.description LIKE :q OR b.steps LIKE :q OR u.email LIKE :q)';
    $escapedQ = str_replace(['%', '_'], ['\%', '\_'], $q);
    $params[':q'] = '%' . $escapedQ . '%';
}
if ($projectId > 0) {
    $where[] = 'b.project_id = :pid';
    $params[':pid'] = $projectId;
}
if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $where[] = 'b.status = :status';
    $params[':status'] = $status;
}
if ($priority !== '' && in_array($priority, $allowedPriorities, true)) {
    $where[] = 'b.priority = :priority';
    $params[':priority'] = $priority;
}
if ($authorId > 0) {
    $where[] = 'b.author_id = :aid';
    $params[':aid'] = $authorId;
}
if ($dateFrom !== '') {
    $where[] = 'b.created_at >= :dfrom';
    $params[':dfrom'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[] = 'b.created_at <= :dto';
    $params[':dto'] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM bugs b LEFT JOIN users u ON u.id = b.author_id" . $whereSql);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

$sql = "SELECT b.*, p.name AS project_name, u.login AS author_name 
        FROM bugs b 
        LEFT JOIN projects p ON p.id = b.project_id 
        LEFT JOIN users u ON u.id = b.author_id
        {$whereSql} 
        ORDER BY {$sortCol} {$dir} 
        LIMIT :lim OFFSET :off";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$bugs = $stmt->fetchAll();

function buildUrl($overrides = []) {
    $params = array_merge($_GET, $overrides);
    return 'bugs_search.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title>Поиск ошибок</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="container">
    <div class="topbar">
        <a href="bug_list.php">← На главную</a>
        <span><?= htmlspecialchars($user['login']) ?> · <a href="logout.php">Выйти</a></span>
    </div>
    <h1>Поиск и фильтрация</h1>

    <form method="GET" action="bugs_search.php" class="filter-form">
        <div class="form-group">
            <label>Поисковый запрос (название, описание, шаги, email)</label>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Введите текст...">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Проект</label>
                <select name="project_id">
                    <option value="0">Все проекты</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $projectId == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Автор</label>
                <select name="author_id">
                    <option value="0">Все авторы</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $authorId == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['login']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Статус</label>
                <select name="status">
                    <option value="">Все</option>
                    <?php foreach ($allowedStatuses as $s): ?>
                        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Приоритет</label>
                <select name="priority">
                    <option value="">Все</option>
                    <?php foreach ($allowedPriorities as $p): ?>
                        <option value="<?= $p ?>" <?= $priority === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Дата с</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="form-group">
                <label>Дата по</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
        </div>
        <button type="submit" style="margin-top: 10px;">Применить фильтры</button>
        <a href="bugs_search.php" class="btn-reset" style="margin-top: 10px;">Сбросить</a>
    </form>

    <hr>
    <p>Найдено результатов: <strong><?= $totalRows ?></strong></p>

    <table>
        <tr>
            <th>ID</th>
            <th>Заголовок</th>
            <th>Автор</th>
            <th>Проект</th>
            <th><a href="<?= buildUrl(['sort' => 'priority', 'dir' => $dir === 'asc' ? 'desc' : 'asc']) ?>">Приоритет <?= $sortKey === 'priority' ? ($dir === 'asc' ? '↑' : '↓') : '' ?></a></th>
            <th><a href="<?= buildUrl(['sort' => 'status', 'dir' => $dir === 'asc' ? 'desc' : 'asc']) ?>">Статус <?= $sortKey === 'status' ? ($dir === 'asc' ? '↑' : '↓') : '' ?></a></th>
            <th><a href="<?= buildUrl(['sort' => 'date', 'dir' => $dir === 'asc' ? 'desc' : 'asc']) ?>">Дата <?= $sortKey === 'date' ? ($dir === 'asc' ? '↑' : '↓') : '' ?></a></th>
            <th></th>
        </tr>
        <?php if (!$bugs): ?>
            <tr><td colspan="8">Ничего не найдено.</td></tr>
        <?php endif; ?>
        <?php foreach ($bugs as $b): ?>
        <tr>
            <td><?= (int)$b['id'] ?></td>
            <td><?= htmlspecialchars($b['title']) ?></td>
            <td><?= htmlspecialchars($b['author_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($b['project_name'] ?? '—') ?></td>
            <td><?= htmlspecialchars($b['priority']) ?></td>
            <td><span class="status s-<?= str_replace(' ', '_', $b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></td>
            <td><?= htmlspecialchars($b['created_at']) ?></td>
            <td><a href="bug_view.php?id=<?= (int)$b['id'] ?>">Открыть</a></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="<?= buildUrl(['page' => $page - 1]) ?>">← Назад</a><?php endif; ?>
        <span>Стр. <?= $page ?> из <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a href="<?= buildUrl(['page' => $page + 1]) ?>">Вперед →</a><?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>