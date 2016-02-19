<?
    $table = '';
    $field = '';
    $replace = '';
    $replace_with = '';

    define('DB_HOST', 'host');
    define('DB_USER', 'user');
    define('DB_NAME', 'database');
    define('DB_PASS', 'password');

    $basepath = __DIR__;
    $baseurl = "http" . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . pathinfo($_SERVER['PHP_SELF'],PATHINFO_DIRNAME);

    if (file_exists("database.php")) { require_once("database.php"); }

    if (class_exists('Database')) {
        $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    } else {
        $db = new MySQLi(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }

    $items = $db->query("SELECT * FROM `$table`");
    while($item = $items->fetch_assoc()) {
        if (substr_count($item[$field],$replace) > 0) {
            echo "<pre>Updating Item: $item[id]</pre>";
            $db->query("UPDATE `$table` SET `$field` = '".str_replace($replace,$replacewith,$item[$field])."' WHERE `id` = $item[id]");
        }
    }

    echo "Done";

?>
