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
    // Подключаемся к БД через PDO
    $dsn = "pgsql:host={$db_host};port={$db_port};dbname={$db_name}";
    $pdo = new PDO($dsn, $db_user, $db_pass);
    
    // Устанавливаем режим ошибок
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Устанавливаем кодировку UTF-8
    $pdo->exec("SET NAMES 'UTF8'");
    
    // Проверяем версию PostgreSQL
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetchColumn();
    
    // Получаем день из POST-запроса от Android приложения
    $day = $_POST['day'] ?? null;
    
    // Для ручного тестирования через браузер - раскомментируйте следующую строку:
    // $day = 1;
    
    if (!$day) {
        // Если день не передан, показываем тестовую информацию

        
        // Проверяем таблицу
        $stmt = $pdo->query("SELECT COUNT(*) FROM tab");
        $count = $stmt->fetchColumn();
        echo "Записей в таблице tab: " . $count . "<br>";
        
        // Показываем сегодняшние праздники
        $today = date("Y-m-d");
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = :today");
        $stmt->execute([':today' => $today]);
        $todayHolidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Праздников на сегодня ($today): " . count($todayHolidays) . "<br>";
        if ($todayHolidays) {
            foreach ($todayHolidays as $holiday) {
                echo "- " . htmlspecialchars($holiday) . "<br>";
            }
        }
        
        // Показываем завтрашние праздники
        $stmt = $pdo->query("SELECT text FROM tab WHERE date_grigorian = (CURRENT_DATE + INTERVAL '1 day')");
        $tomorrowHolidays = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $tomorrow = date("Y-m-d", strtotime("+1 day"));
        echo "Праздников на завтра ($tomorrow): " . count($tomorrowHolidays) . "<br>";
        if ($tomorrowHolidays) {
            foreach ($tomorrowHolidays as $holiday) {
                echo "- " . htmlspecialchars($holiday) . "<br>";
            }
        }
        
        echo "<hr>";
        echo "<h4>Для Android приложения отправляйте POST запрос с параметром 'day'</h4>";
        echo "Пример: day=1 (сегодня) или day=2 (завтра)";
        
        // Закрываем соединение
        $pdo = null;
        exit;
    }
    
    $todaySData = date("Y-m-d");
    
    /* Если $day = 1, то находим сегодняшний праздник*/
    /* Если $day = 2, то находим завтрашний праздник*/
    if ($day == 1) {
        $query = "SELECT text FROM tab WHERE date_grigorian = :date";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':date' => $todaySData]);
    } else {
        // Для PostgreSQL используем CURRENT_DATE + INTERVAL '1 day'
        $query = "SELECT text FROM tab WHERE date_grigorian = (CURRENT_DATE + INTERVAL '1 day')";
        $stmt = $pdo->query($query);
    }
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($rows) {
        // Собираем все праздники в одну строку
        $all_holidays = [];
        foreach ($rows as $row) {
            $all_holidays[] = $row['text'];
        }
        
        // Отправляем праздники разделенные переносом строки
        echo implode("\n", $all_holidays);
    } else {
        echo "Праздников на эту дату не найдено.";
    }
    
    // Закрываем соединение
    $pdo = null;
    
} catch (PDOException $e) {
    die("❌ Ошибка PDO: " . $e->getMessage());
} catch (Exception $e) {
    die("❌ Общая ошибка: " . $e->getMessage());
}
?>