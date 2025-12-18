<?php
header("Content-Type: text/html; charset=utf-8");

try {
    // Подключение к БД
    $database_url = getenv('DATABASE_URL');
    $db_params = parse_url($database_url);
    
    $dsn = "pgsql:host={$db_params['host']};port={$db_params['port']};dbname=" . ltrim($db_params['path'], '/');
    $pdo = new PDO($dsn, $db_params['user'], $db_params['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Устанавливаем кодировку
    $pdo->exec("SET NAMES 'UTF8'");
    
    // Получаем день из POST
    $day = $_POST['day'] ?? null;
    
    // Для тестирования через браузер
    if (!$day && isset($_GET['test'])) {
        $day = $_GET['test'];
    }
    
    // Если день не передан, показываем информацию
    if (!$day) {
        echo "<h2>Сервер праздников работает!</h2>";
        echo "Текущая дата на сервере: " . date('Y-m-d H:i:s') . "<br>";
        echo "Часовой пояс: " . date_default_timezone_get() . "<br><br>";
        
        // Проверяем даты в БД
        $stmt = $pdo->query("SELECT CURRENT_DATE as server_date, COUNT(*) as total FROM tab");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Дата на сервере (CURRENT_DATE): " . $result['server_date'] . "<br>";
        echo "Всего записей в БД: " . $result['total'] . "<br><br>";
        
        // Показываем распределение по датам
        $stmt = $pdo->query("SELECT date_grigorian, COUNT(*) as count FROM tab GROUP BY date_grigorian ORDER BY date_grigorian");
        echo "Праздники по датам:<br>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $isToday = ($row['date_grigorian'] == $result['server_date']) ? " (СЕГОДНЯ!)" : "";
            $isTomorrow = ($row['date_grigorian'] == date('Y-m-d', strtotime($result['server_date'] . ' +1 day'))) ? " (ЗАВТРА!)" : "";
            echo "- " . $row['date_grigorian'] . ": " . $row['count'] . " праздников" . $isToday . $isTomorrow . "<br>";
        }
        
        echo "<br>Для Android приложения отправляйте POST запрос с параметром 'day=1' (сегодня) или 'day=2' (завтра)";
        exit;
    }
    
    // ОСНОВНАЯ ЛОГИКА - всегда используем CURRENT_DATE из PostgreSQL!
    if ($day == 1) {
        // Сегодняшние праздники - используем CURRENT_DATE из БД
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE");
        $stmt->execute();
    } else if ($day == 2) {
        // Завтрашние праздники
        $stmt = $pdo->prepare("SELECT text FROM tab WHERE date_grigorian = CURRENT_DATE + INTERVAL '1 day'");
        $stmt->execute();
    } else {
        // Для других дней
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
    echo "Exception: " . $e->getMessage();
}
?>