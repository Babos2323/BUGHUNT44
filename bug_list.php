<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_login();

$user = current_user();

$statusFilter = $_GET['status'] ?? '';

$sql = "SELECT b.*, 
        (SELECT COUNT(*) FROM comments c WHERE c.bug_id = b.id) AS comment_count,
        (SELECT created_at FROM bug_history h WHERE h.bug_id = b.id ORDER BY h.created_at DESC LIMIT 1) AS last_status_change
        FROM bugs b";

$params = [];
if ($statusFilter !== '') {
    $sql .= " WHERE b.status = :status";
    $params[':status'] = $statusFilter;
}
$sql .= " ORDER BY b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bugs = $stmt->fetchAll();

$statuses = ['New', 'Review', 'Confirmed', 'In Progress', 'Fixed', 'Closed', 'Rejected'];
?>
<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title>Список ошибок — BugHunter</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="container">
    <div class="topbar">
        <span>Вы: <strong><?= htmlspecialchars($user['login']) ?></strong> (<?= htmlspecialchars($user['role']) ?>)</span>
        <div class="nav-links">
            <a href="bug_list.php">Главная</a>
            <a href="bugs_search.php">🔍 Поиск и фильтры</a>
            <a href="dashboard.php">📊 Аналитика</a>
            <a href="logout.php">Выйти</a>
        </div>
    </div>
    
    <h1>Список ошибок</h1>
    
    <form method="GET" class="filter-form" style="margin-bottom: 20px;">
        <label>Фильтр по статусу: 
            <select name="status" onchange="this.form.submit()">
                <option value="">Все статусы</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>Заголовок</th>
            <th>Статус</th>
            <th>💬 Комм.</th>
            <th>Последнее изменение</th>
            <th></th>
        </tr>
        <?php if (!$bugs): ?>
            <tr><td colspan="6">Ошибок с таким статусом нет.</td></tr>
        <?php endif; ?>
        <?php foreach ($bugs as $b): ?>
        <tr>
            <td><?= (int)$b['id'] ?></td>
            <td><?= htmlspecialchars($b['title']) ?></td>
            <td><span class="status s-<?= str_replace(' ', '_', $b['status']) ?>"><?= htmlspecialchars($b['status']) ?></span></td>
            <td align="center"><?= $b['comment_count'] ?></td>
            <td><small><?= $b['last_status_change'] ? htmlspecialchars($b['last_status_change']) : '—' ?></small></td>
            <td><a href="bug_view.php?id=<?= (int)$b['id'] ?>">Открыть</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>