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
    
    // ВАЖНО: Получаем даты ТОЛЬКО из PostgreSQL!
    $stmt = $pdo->query("SELECT CURRENT_DATE as today, CURRENT_DATE + INTERVAL '1 day' as tomorrow");
    $serverDates = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $todayFromDB = $serverDates['today'];    // Сегодня по серверу
    $tomorrowFromDB = $serverDates['tomorrow']; // Завтра по серверу
    
    echo "Серверное время (PostgreSQL):<br>";
    echo "- Сегодня: $todayFromDB<br>";
    echo "- Завтра: $tomorrowFromDB<br>";
    echo "PHP время: " . date("Y-m-d") . "<br><br>";
    
    // Получаем день из POST
    $day = $_POST['day'] ?? null;
    
    if (!$day) {
        // Тестовая информация
        $stmt = $pdo->query("SELECT COUNT(*) FROM tab");
        $count = $stmt->fetchColumn();
        echo "Записей в таблице tab: " . $count . "<br>";
        
        // Праздники на СЕГОДНЯ (используем дату из БД!)
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = :today");
        $stmt->execute([':today' => $todayFromDB]);
        $todayHolidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Праздников на сегодня ($todayFromDB): " . count($todayHolidays) . "<br>";
        foreach ($todayHolidays as $holiday) {
            echo "- " . htmlspecialchars($holiday) . "<br>";
        }
        
        // Праздники на ЗАВТРА (используем дату из БД!)
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = :tomorrow");
        $stmt->execute([':tomorrow' => $tomorrowFromDB]);
        $tomorrowHolidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Праздников на завтра ($tomorrowFromDB): " . count($tomorrowHolidays) . "<br>";
        foreach ($tomorrowHolidays as $holiday) {
            echo "- " . htmlspecialchars($holiday) . "<br>";
        }
        
        // Показать все записи
        echo "<h3>Все записи в БД:</h3>";
        $stmt = $pdo->query("SELECT date_grigorian, text FROM tab ORDER BY date_grigorian");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $marker = ($row['date_grigorian'] == $todayFromDB) ? " [СЕГОДНЯ]" : 
                     (($row['date_grigorian'] == $tomorrowFromDB) ? " [ЗАВТРА]" : "");
            echo "- " . $row['date_grigorian'] . $marker . ": " . htmlspecialchars($row['text']) . "<br>";
        }
        
        exit;
    }
    
    // ОСНОВНАЯ ЛОГИКА ДЛЯ ANDROID
    // ВСЕГДА используем CURRENT_DATE из PostgreSQL!
    if ($day == 1) {
        // Сегодняшние праздники - ТОЛЬКО из CURRENT_DATE
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE");
        $stmt->execute();
    } else if ($day == 2) {
        // Завтрашние праздники - ТОЛЬКО из CURRENT_DATE
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE + INTERVAL '1 day'");
        $stmt->execute();
    } else {
        // Для тестирования других дней
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE + INTERVAL :days day");
        $stmt->execute([':days' => $day - 1]);
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