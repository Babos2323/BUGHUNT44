<?php
require_once __DIR__ . '/db.php';

try {
    // Добавляем колонку email в таблицу users
    try { 
        $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT"); 
    } catch(Exception $e){
        // Колонка уже существует, игнорируем
    }

    // Добавляем колонку steps в таблицу bugs
    try { 
        $pdo->exec("ALTER TABLE bugs ADD COLUMN steps TEXT"); 
    } catch(Exception $e){
        // Колонка уже существует, игнорируем
    }

    // Заполняем тестовыми данными, если их нет
    $pdo->exec("UPDATE users SET email = login || '@bughunter.local' WHERE email IS NULL");
    $pdo->exec("UPDATE bugs SET steps = '1. Открыть страницу' || char(10) || '2. Ввести данные' || char(10) || '3. Нажать кнопку' WHERE steps IS NULL");

    echo "<h2>База успешно обновлена!</h2>";
    echo "<p>Добавлены поля <b>email</b> (в users) и <b>steps</b> (в bugs).</p>";
    echo "<a href='bugs_search.php'>Перейти к поиску</a> | <a href='index.php'>На главную</a>";
    
} catch (Throwable $e) {
    exit('<h2 style="color:red;">Ошибка при обновлении:</h2> ' . htmlspecialchars($e->getMessage()));
}