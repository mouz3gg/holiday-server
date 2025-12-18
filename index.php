<?php
header("Content-Type: text/html; charset=utf-8"); // Важно для кириллицы

// --- ПОЛУЧАЕМ ДАННЫХ ДЛЯ ПОДКЛЮЧЕНИЯ ИЗ СТРОКИ RENDER ---
// Пример строки: postgresql://holiday_user:abc123@dpg-xxxxxxx-a.oregon-postgres.render.com/holiday_db
$database_url = getenv('DATABASE_URL'); // Render автоматически помещает строку подключения в эту переменную

// Парсим строку подключения
$db_params = parse_url($database_url);

// Формируем параметры для подключения к PostgreSQL
$db_host = $db_params['host'] . (isset($db_params['port']) ? ':' . $db_params['port'] : '');
$db_name = ltrim($db_params['path'], '/');
$db_user = $db_params['user'];
$db_pass = $db_params['pass'];

// Создаем строку подключения для pg_connect()
$conn_string = "host={$db_host} dbname={$db_name} user={$db_user} password={$db_pass}";

// Подключаемся к БД
$conn = pg_connect($conn_string);

if (!$conn) {
    echo "Подключение невозможно: " . pg_last_error($conn);
    exit;
}

// Устанавливаем кодировку UTF-8
pg_set_client_encoding($conn, "UTF-8");

// Получаем день из POST-запроса от Android приложения
$day = $_POST['day'];
// $day = 1; // Для ручного тестирования через браузер

$todaySData = date("Y-m-d");

/* Если $day = 1, то находим сегодняшний праздник*/
/* Если $day = 2, то находим завтрашний праздник*/
if ($day == 1) {
    $query = "SELECT text FROM tab WHERE date_grigorian = '" . $todaySData . "'";
} else {
    // Для PostgreSQL используем CURRENT_DATE + INTERVAL '1 day'
    $query = "SELECT text FROM tab WHERE date_grigorian = (CURRENT_DATE + INTERVAL '1 day')";
}

$result = pg_query($conn, $query);

if (!$result) {
    echo "Ошибка запроса: " . pg_last_error($conn);
    pg_close($conn);
    exit;
}

$row = pg_fetch_array($result, null, PGSQL_ASSOC);
$data = $row['text'];

if ($data) {
    /* Вывод информации в окно приложения */
    echo $data;
} else {
    echo "Праздников на эту дату не найдено.";
}

// Закрываем соединение
pg_close($conn);
?>