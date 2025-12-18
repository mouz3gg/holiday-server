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

// Создаем строку подключения для pg_connect()
$conn_string = "host={$db_host} port={$db_port} dbname={$db_name} user={$db_user} password={$db_pass}";

echo "Пытаемся подключиться к: {$db_host}<br>";
echo "База данных: {$db_name}<br>";
echo "Пользователь: {$db_user}<br>";

// Подключаемся к БД
$conn = pg_connect($conn_string);

if (!$conn) {
    echo "ОШИБКА подключения к PostgreSQL: " . pg_last_error() . "<br>";
    echo "Проверьте:<br>";
    echo "1. Правильность DATABASE_URL<br>";
    echo "2. Доступность БД<br>";
    echo "3. Наличие расширения pgsql в PHP<br>";
    exit;
}

echo "Успешно подключились к PostgreSQL!<br>";

// Проверяем версию PostgreSQL
$result = pg_query($conn, "SELECT version()");
$version = pg_fetch_result($result, 0);
echo "Версия PostgreSQL: " . $version . "<br>";

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