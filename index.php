<?php
header("Content-Type: text/html; charset=utf-8");
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Подключение к БД
    $database_url = getenv('DATABASE_URL');
    $db_params = parse_url($database_url);
    
    $dsn = "pgsql:host={$db_params['host']};port={$db_params['port']};dbname=" . ltrim($db_params['path'], '/');
    $pdo = new PDO($dsn, $db_params['user'], $db_params['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'UTF8'");
    
    // ВСЕГДА используем дату из PostgreSQL, а не из PHP!
    $stmt = $pdo->query("SELECT CURRENT_DATE as today, CURRENT_DATE + INTERVAL '1 day' as tomorrow");
    $dates = $stmt->fetch(PDO::FETCH_ASSOC);
    $todaySData = $dates['today'];
    $tomorrowSData = $dates['tomorrow'];
    
    echo "✅ Дата из PostgreSQL:<br>";
    echo "- Сегодня (CURRENT_DATE): " . $todaySData . "<br>";
    echo "- Завтра: " . $tomorrowSData . "<br>";
    echo "- Дата из PHP (date()): " . date("Y-m-d") . " ← НЕ ИСПОЛЬЗУЕМ!<br><br>";
    
    // Получаем день из POST
    $day = $_POST['day'] ?? null;
    
    // Если нет параметра day, показываем тестовую информацию
    if (!$day) {
        echo "<h3>Тестирование:</h3>";
        
        // Всего записей
        $stmt = $pdo->query("SELECT COUNT(*) FROM tab");
        $count = $stmt->fetchColumn();
        echo "Записей в таблице tab: " . $count . "<br>";
        
        // Праздники на СЕГОДНЯ (из БД!)
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = :today");
        $stmt->execute([':today' => $todaySData]);
        $todayHolidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Праздников на сегодня ($todaySData): " . count($todayHolidays) . "<br>";
        foreach ($todayHolidays as $holiday) {
            echo "- " . htmlspecialchars($holiday) . "<br>";
        }
        
        // Праздники на ЗАВТРА (из БД!)
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = :tomorrow");
        $stmt->execute([':tomorrow' => $tomorrowSData]);
        $tomorrowHolidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Праздников на завтра ($tomorrowSData): " . count($tomorrowHolidays) . "<br>";
        foreach ($tomorrowHolidays as $holiday) {
            echo "- " . htmlspecialchars($holiday) . "<br>";
        }
        
        echo "<hr><h4>Для Android приложения:</h4>";
        echo "Отправляйте POST запрос с параметром 'day=1' (сегодня) или 'day=2' (завтра)";
        exit;
    }
    
    // ОСНОВНАЯ ЛОГИКА ДЛЯ ANDROID
    if ($day == 1) {
        // Сегодняшние праздники
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE");
        $stmt->execute();
    } else if ($day == 2) {
        // Завтрашние праздники
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE + INTERVAL '1 day'");
        $stmt->execute();
    } else {
        // Для других дней (если нужно)
        $offset = $day - 1;
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE + INTERVAL :offset day");
        $stmt->execute([':offset' => $offset]);
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($rows) {
        $all_holidays = [];
        foreach ($rows as $row) {
            $all_holidays[] = $row['text'];
        }
        // Выводим каждый праздник с новой строки
        echo implode("\n", $all_holidays);
    } else {
        echo "Праздников на эту дату не найдено.";
    }
    
    $pdo = null;
    
} catch (PDOException $e) {
    echo "Ошибка БД: " . $e->getMessage();
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}
?>