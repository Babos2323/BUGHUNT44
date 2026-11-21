<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';
require_login();

$user = current_user();

$totalBugs = $pdo->query("SELECT COUNT(*) FROM bugs")->fetchColumn();
$closedBugs = $pdo->query("SELECT COUNT(*) FROM bugs WHERE status = 'Closed'")->fetchColumn();
$rejectedBugs = $pdo->query("SELECT COUNT(*) FROM bugs WHERE status = 'Rejected'")->fetchColumn();
$openBugs = $totalBugs - $closedBugs - $rejectedBugs;

$percentClosed = $totalBugs > 0 ? round(($closedBugs / $totalBugs) * 100) : 0;
$criticalBugs = $pdo->query("SELECT COUNT(*) FROM bugs WHERE priority = 'Critical'")->fetchColumn();

$avgComments = $pdo->query("
    SELECT AVG(c_cnt) FROM (
        SELECT b.id, COUNT(c.id) AS c_cnt 
        FROM bugs b LEFT JOIN comments c ON c.bug_id = b.id 
        GROUP BY b.id
    )
")->fetchColumn();
$avgComments = $avgComments ? round($avgComments, 1) : 0;

$topProject = $pdo->query("
    SELECT p.name, COUNT(b.id) as cnt 
    FROM projects p 
    JOIN bugs b ON b.project_id = p.id 
    GROUP BY p.id 
    ORDER BY cnt DESC LIMIT 1
")->fetch();

$statusStats = $pdo->query("SELECT status, COUNT(*) AS cnt FROM bugs GROUP BY status ORDER BY cnt DESC")->fetchAll();
$priorityStats = $pdo->query("SELECT priority, COUNT(*) AS cnt FROM bugs GROUP BY priority ORDER BY cnt DESC")->fetchAll();

$projectStats = $pdo->query("
    SELECT p.name, COUNT(b.id) AS total,
    SUM(CASE WHEN b.status = 'New' THEN 1 ELSE 0 END) AS new_cnt,
    SUM(CASE WHEN b.status = 'In Progress' THEN 1 ELSE 0 END) AS prog_cnt,
    SUM(CASE WHEN b.status = 'Fixed' THEN 1 ELSE 0 END) AS fixed_cnt,
    SUM(CASE WHEN b.status = 'Closed' THEN 1 ELSE 0 END) AS closed_cnt
    FROM projects p
    LEFT JOIN bugs b ON b.project_id = p.id
    GROUP BY p.id ORDER BY total DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"><title>Dashboard — BugHunter</title><link rel="stylesheet" href="style.css"></head>
<body>
<div class="container">
    <div class="topbar">
        <a href="bug_list.php">← На главную</a>
        <span><?= htmlspecialchars($user['login']) ?> · <a href="logout.php">Выйти</a></span>
    </div>
    <h1>Аналитика (Dashboard)</h1>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Всего ошибок</h3>
            <div class="stat-number"><?= $totalBugs ?></div>
        </div>
        <div class="stat-card">
            <h3>Открытые ошибки</h3>
            <div class="stat-number" style="color: #b45309;"><?= $openBugs ?></div>
        </div>
        <div class="stat-card">
            <h3>% Закрытых</h3>
            <div class="stat-number" style="color: #1e7a3a;"><?= $percentClosed ?>%</div>
        </div>
        <div class="stat-card">
            <h3>Critical</h3>
            <div class="stat-number" style="color: #c22323;"><?= $criticalBugs ?></div>
        </div>
        <div class="stat-card">
            <h3>Ср. комм. на баг</h3>
            <div class="stat-number"><?= $avgComments ?></div>
        </div>
        <div class="stat-card">
            <h3>Топ-проект</h3>
            <div class="stat-number" style="font-size: 16px;"><?= $topProject ? htmlspecialchars($topProject['name']) : '—' ?></div>
        </div>
    </div>

    <div style="display: flex; gap: 40px;">
        <div style="flex: 1;">
            <h2>По статусам</h2>
            <?php foreach ($statusStats as $s): ?>
                <?php $w = $totalBugs > 0 ? round(($s['cnt'] / $totalBugs) * 100) : 0; ?>
                <div style="margin-bottom: 8px;">
                    <div style="display: flex; justify-content: space-between; font-size: 14px;">
                        <strong><?= htmlspecialchars($s['status']) ?></strong>
                        <span><?= $s['cnt'] ?> (<?= $w ?>%)</span>
                    </div>
                    <div style="background: #eee; height: 8px; border-radius: 4px; margin-top: 4px;">
                        <div style="background: #1e66f5; height: 100%; border-radius: 4px; width: <?= $w ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="flex: 1;">
            <h2>По приоритетам</h2>
            <?php foreach ($priorityStats as $p): ?>
                <?php $w = $totalBugs > 0 ? round(($p['cnt'] / $totalBugs) * 100) : 0; ?>
                <div style="margin-bottom: 8px;">
                    <div style="display: flex; justify-content: space-between; font-size: 14px;">
                        <strong><?= htmlspecialchars($p['priority']) ?></strong>
                        <span><?= $p['cnt'] ?> (<?= $w ?>%)</span>
                    </div>
                    <div style="background: #eee; height: 8px; border-radius: 4px; margin-top: 4px;">
                        <div style="background: <?= $p['priority'] === 'Critical' ? '#c22323' : '#b45309' ?>; height: 100%; border-radius: 4px; width: <?= $w ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <h2>Аналитика по проектам</h2>
    <table>
        <tr>
            <th>Проект</th>
            <th>Всего ошибок</th>
            <th>Новые</th>
            <th>В работе</th>
            <th>Исправлены</th>
            <th>Закрыты</th>
        </tr>
        <?php foreach ($projectStats as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['name']) ?></td>
            <td>
                <strong><?= $p['total'] ?></strong>
                <?php if ($totalBugs > 0): ?>
                    <div style="background: #eee; height: 4px; border-radius: 2px; margin-top: 4px;">
                        <div style="background: #1e66f5; height: 100%; border-radius: 2px; width: <?= round(($p['total'] / $totalBugs) * 100) ?>%;"></div>
                    </div>
                <?php endif; ?>
            </td>
            <td><?= $p['new_cnt'] ?></td>
            <td><?= $p['prog_cnt'] ?></td>
            <td><?= $p['fixed_cnt'] ?></td>
            <td><?= $p['closed_cnt'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>