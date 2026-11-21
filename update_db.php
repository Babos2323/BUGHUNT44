<?php
require_once __DIR__ . '/db.php';

try {
    // 1. Принудительно создаем ВСЕ таблицы, если их нет (без удаления старых данных)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        login TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
    );

    CREATE TABLE IF NOT EXISTS bugs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT NOT NULL DEFAULT 'New',
        priority TEXT DEFAULT 'Medium',
        project_id INTEGER,
        author_id INTEGER,
        created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS bug_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bug_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        old_status TEXT NOT NULL,
        new_status TEXT NOT NULL,
        created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bug_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        text TEXT NOT NULL,
        created_at TEXT DEFAULT (datetime('now'))
    );
    ");

    // 2. Добавляем новые колонки (email и steps), если их еще нет
    try { $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE bugs ADD COLUMN steps TEXT"); } catch(Exception $e){}

    // 3. Проверяем, есть ли вообще пользователи. Если база пустая — создаем тестовых.
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount == 0) {
        $users = [
            ['customer',  'Customer'],
            ['tester',    'Tester'],
            ['developer', 'Developer'],
            ['admin',     'Admin'],
        ];
        $stmt = $pdo->prepare('INSERT INTO users (login, password_hash, role, email) VALUES (:l, :h, :r, :e)');
        foreach ($users as [$login, $role]) {
            $stmt->execute([
                ':l' => $login, 
                ':h' => password_hash('12345', PASSWORD_DEFAULT), 
                ':r' => $role,
                ':e' => $login . '@bughunter.local'
            ]);
        }
    }

    // Проверяем проекты
    $projCount = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    if ($projCount == 0) {
        $pdo->exec("INSERT INTO projects (name) VALUES ('Core API'), ('Web UI'), ('Mobile App')");
    }

    // Проверяем баги
    $bugCount = $pdo->query("SELECT COUNT(*) FROM bugs")->fetchColumn();
    if ($bugCount == 0) {
        $pdo->exec("
            INSERT INTO bugs (title, description, status, priority, project_id, author_id, steps) VALUES
            ('Пустое поле пароля', 'При пустом пароле страница падает с 500', 'New', 'Critical', 1, 2, '1. Открыть форму\n2. Оставить пустое поле'),
            ('Кнопка не работает', 'Кнопка Сохранить не реагирует в Firefox', 'Review', 'High', 2, 2, '1. Открыть Firefox\n2. Нажать Сохранить'),
            ('Опечатка в заголовке', 'На главной странице Добро пожаловать', 'Confirmed', 'Low', 3, 1, '1. Зайти на главную'),
            ('Ошибка API', '500 при запросе /users', 'In Progress', 'Critical', 1, 3, '1. Отправить GET /users'),
            ('Баг верстки', 'Съезжает меню на мобильных', 'Fixed', 'Medium', 2, 3, '1. Открыть на смартфоне');
        ");
    }

    // 4. Обновляем пустые email и steps, если они остались от старых записей
    $pdo->exec("UPDATE users SET email = login || '@bughunter.local' WHERE email IS NULL");
    $pdo->exec("UPDATE bugs SET steps = 'Шаги не указаны' WHERE steps IS NULL");

    // 5. Создаем индексы для скорости
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bugs_status ON bugs(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bugs_priority ON bugs(priority)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bugs_project_id ON bugs(project_id)");

    echo "<h2>Успех! База данных создана/обновлена.</h2>";
    echo "<p>Все таблицы на месте, тестовые данные добавлены.</p>";
    echo "<a href='index.php'>Перейти к входу</a> | <a href='bug_list.php'>Список ошибок</a>";
    
} catch (Throwable $e) {
    exit('<h2 style="color:red;">Ошибка:</h2> ' . htmlspecialchars($e->getMessage()));
}