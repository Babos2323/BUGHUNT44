<?php
require_once __DIR__ . '/bootstrap.php';

$dbFile = __DIR__ . '/bughunter.db';
$isNew = !file_exists($dbFile);

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Создаем таблицы, если их нет
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    login TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK(role IN ('Customer','Tester','Developer','Admin'))
);

CREATE TABLE IF NOT EXISTS projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS bugs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT,
    status TEXT NOT NULL DEFAULT 'New' CHECK(status IN ('New','Review','Confirmed','Rejected','In Progress','Fixed','Closed')),
    priority TEXT DEFAULT 'Medium' CHECK(priority IN ('Critical','High','Medium','Low')),
    project_id INTEGER REFERENCES projects(id),
    author_id INTEGER REFERENCES users(id),
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS bug_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bug_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    old_status TEXT NOT NULL,
    new_status TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (bug_id) REFERENCES bugs(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bug_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    text TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (bug_id) REFERENCES bugs(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_bugs_status ON bugs(status);
CREATE INDEX IF NOT EXISTS idx_bugs_priority ON bugs(priority);
CREATE INDEX IF NOT EXISTS idx_bugs_project_id ON bugs(project_id);
");

// Заполняем данными только если база новая
if ($isNew) {
    $users = [
        ['customer',  'Customer'],
        ['tester',    'Tester'],
        ['developer', 'Developer'],
        ['admin',     'Admin'],
    ];
    $stmt = $pdo->prepare('INSERT INTO users (login, password_hash, role) VALUES (:l, :h, :r)');
    foreach ($users as [$login, $role]) {
        $stmt->execute([':l' => $login, ':h' => password_hash('12345', PASSWORD_DEFAULT), ':r' => $role]);
    }

    $pdo->exec("INSERT INTO projects (name) VALUES ('Core API'), ('Web UI'), ('Mobile App')");

    $pdo->exec("
        INSERT INTO bugs (title, description, status, priority, project_id, author_id) VALUES
        ('Пустое поле пароля', 'При пустом пароле страница падает с 500', 'New', 'Critical', 1, 2),
        ('Кнопка не работает', 'Кнопка Сохранить не реагирует в Firefox', 'Review', 'High', 2, 2),
        ('Опечатка в заголовке', 'На главной странице Добро пожаловать', 'Confirmed', 'Low', 3, 1),
        ('Ошибка API', '500 при запросе /users', 'In Progress', 'Critical', 1, 3),
        ('Баг верстки', 'Съезжает меню на мобильных', 'Fixed', 'Medium', 2, 3);
    ");
    echo "База создана и заполнена тестовыми данными.<br>";
} else {
    echo "База данных уже существует. Структура проверена, данные сохранены.<br>";
}

echo '<a href="index.php">Перейти к входу</a>';