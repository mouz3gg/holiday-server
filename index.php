<?php
header("Content-Type: text/html; charset=utf-8");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверяем наличие переменной окружения
if (!getenv('DATABASE_URL')) {
    die("ОШИБКА: Переменная окружения DATABASE_URL не установлена. Проверьте настройки в Render.");
}

// Получаем строку подключения из переменной окружения
$database_url = getenv('DATABASE_URL');

// Парсим строку подключения
$db_params = parse_url($database_url);

if (!$db_params) {
    die("ОШИБКА: Не удалось распарсить строку подключения DATABASE_URL");
}

// Извлекаем параметры с проверкой
$db_host = $db_params['host'] ?? 'localhost';
$db_port = $db_params['port'] ?? '5432';
$db_name = isset($db_params['path']) ? ltrim($db_params['path'], '/') : '';
$db_user = $db_params['user'] ?? '';
$db_pass = $db_params['pass'] ?? '';

// Проверяем наличие всех необходимых параметров
if (empty($db_host) || empty($db_name) || empty($db_user)) {
    die("ОШИБКА: Не хватает данных для подключения. Проверьте строку DATABASE_URL");
}


try {
    // Подключение к БД
    $database_url = getenv('DATABASE_URL');
    $db_params = parse_url($database_url);
    
    // Автокоррекция порта
    if (!isset($db_params['port'])) {
        $db_params['port'] = '5432';
    }
    
    $dsn = "pgsql:host={$db_params['host']};port={$db_params['port']};dbname=" . ltrim($db_params['path'], '/');
    $pdo = new PDO($dsn, $db_params['user'], $db_params['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'UTF8'");
    
    // ФИКСИРУЕМ ЧАСОВОЙ ПОЯС - устанавливаем московское время
    $pdo->exec("SET TIME ZONE 'Europe/Moscow'");
    
    // Получаем день из POST
    $day = $_POST['day'] ?? null;
    
    if (!$day) {
        // Если нет параметра day, показываем сегодняшние праздники
        $day = 1;
    }
    
    // Всегда используем CURRENT_DATE из PostgreSQL с исправленным часовым поясом
    if ($day == 1) {
        // Сегодняшние праздники
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE");
        $stmt->execute();
    } else if ($day == 2) {
        // Завтрашние праздники
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE + INTERVAL '1 day'");
        $stmt->execute();
    } else {
        // По умолчанию сегодня
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE");
        $stmt->execute();
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($rows) {
        $all_holidays = [];
        foreach ($rows as $row) {
            $all_holidays[] = $row['text'];
        }
        echo implode("\n", $all_holidays);
    } else {
        echo "Праздников на эту дату не найдено.";
    }
    
    $pdo = null;
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}
?>