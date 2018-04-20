<?php
/* ************
    A simple, generic Database Abstraction Layer

	Supports some commonly used PHP Database types: SQLITE, MYSQL, MYSQLi

	Fully object-oriented - results are sqlite_result, mysql_result or sqli_result
		- Sqlite and MySQL Result types are very minimal.

	Connecting:
		$db = new Database($dsn);

		$dsn = array( 'phptype'  => 'mysqli', 'username' => 'username', 'password' => 'password', 'hostspec' => 'localhost', 'database' => 'thedb' );
		$dsn = "sqlite:////home/cadried/public_html/admin/tables.db?mode=0666";
		$dsn = "mysql://user:password@host/database";
		$dsn = "mysqli://user:password@host/database";

		or

		$db = new Database(phptype, host, username, password, database);

		or (for MySQLi assumed)

		$db = new Database(host, username, password, database);

    or

    define('DB_HOST', 'host');
    define('DB_USER', 'user');
    define('DB_NAME', 'database');
    define('DB_PASS', 'password');

    $db = new Database();

*/

// error_reporting(E_ALL);

class Database
{
    var $db;
    var $statement;
    var $last_query;
    var $affected_rows;
    var $last;
    var $errno;
    var $error;

    var $lat_col = "latitude";
    var $lon_col = "longitude";

    function __construct($connect_string=false, $username=false, $password=false, $pdo_opts=false)
    {

        if (!$connect_string) {
            if (defined('DB_TYPE') && defined('DB_HOST') && defined('DB_NAME')) {
                $connect_string = sprintf('%s:host=%s;dbname=%s', DB_TYPE, DB_HOST, DB_NAME);
            }
        }

        if (!$username && defined('DB_USER')) {
            $username = DB_USER;
        }

        if (!$password && defined('DB_PASS')) {
            $password = DB_PASS;
        }

        if (!$pdo_opts) {
            $pdo_opts = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);
        } else {
            $pdo_opts = array_merge(array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION), $pdo_opts);
        }

        try {
            $db = new PDO($connect_string, $username, $password, $pdo_opts);
        } catch(PDOException $e) {
            $this->errno = $e->getCode();
            $this->error = $e->getMessage();
        }
    }

    /*
     * Let's try to avoid using this... m'kay
     */
    function query($query)
    {
        $this->last_query = $query;
        $result = $this->db->query($query);
        $this->last = $this->db->lastInsertId();

        return $result;
    }

    function escape($string)
    {
        return $this->real_escape_string($string);
    }

    function real_escape_string($string)
    {
        return $this->db->quote($string);
    }

    /*
     * This needs some explanation....
     * A WHERE array is a multidimensional array formatted like this
     * array(
     *  array('AND/OR/NOT', 'field_name', 'operator', 'variable),
     *  array('AND/OR/NOT', 'field_name', 'operator', 'variable),
     *  array('AND/OR/NOT', 'field_name', 'operator', 'variable)
     * );
     *
     * TODO: Build normalizers for operators.
     */
    function paremtrize_where_array($where_array) {
        $where_parts = array();
        $where_record = array();
        foreach ($where_array as $where_field) {
            list($and_or_not, $field_name, $operator, $value) = $where_field;
            $field_parameter = sprintf(':%s', $field_name);
            $where_part = sprintf('%s %s %s %s', $and_or_not, $field_name, $operator, $field_parameter);
            $where_parts[] = $where_part;
            $where_record[$field_parameter] = $value;
        }
        return array(
            'where_statement' => implode(' ', $where_parts),
            'where_values' => $where_record
        );
    }

    function parametrize($db_record) {
        $columns = array_keys($db_record);
        $fields = array();
        $insert_record = array();
        $update_parts = array();

        foreach ($columns as $column) {
            $field_key = sprintf(':%s', $column);
            $fields[] = $field_key;

            $insert_record[$field_key] = $db_record[$column];
            $update_parts[] = sprintf('%s = :%s', $column, $column);
        }
        $field_string = implode(',', $fields);
        $update_string = implode(',', $update_parts);

        return array(
            'record_values' => $insert_record,
            'update_record' => $update_string,
            'field_string' => $field_string,
            'fields' => $fields
        );
    }

    function create($table, $db_record) {
        $params = $this->parametrize($db_record);

        $field_string = $params['field_string'];
        $parametrized_record = $params['record_values'];

        try {
            $this->statement = $this->db->prepare(
                sprintf('INSERT INTO %s VALUES(%s)',
                    $table,
                    $field_string
                )
            );
            $this->statement->execute($parametrized_record);

            $this->affected_rows = $this->statement->rowCount();
            $this->last = $this->db->lastInsertId();

        } catch(PDOException $e) {
            $this->errno = $e->getCode();
            $this->error = $e->getMessage();
        }
    }

    function insert($table, $db_record) {
        $this->create($table, $db_record);
    }

    function update($table, $db_record, $where)
    {
        $update_params = $this->parametrize($db_record);
        $where_params = $this->parametrize_where_array($where);

        try {
            $this->statement =  $this->db->prepare(
                sprintf('UPDATE %s SET %s WHERE %s',
                    $table,
                    $update_params['update_record'],
                    $where_params['where_statement']
                )
            );
            $this->statement->execute(
                array_merge(
                    $update_params['record_values'],
                    $where_params['where_values']
                )
            );

            $this->affected_rows = $this->statement->rowCount();
            $this->last = $this->db->lastInsertId();

        } catch(PDOException $e) {
            $this->errno = $e->getCode();
            $this->error = $e->getMessage();
        }
    }

    function delete($table, $where)
    {
        $where_params = $this->parametrize_where_array($where);

        try {
            $this->statement =  $this->db->prepare(
                sprintf('DELETE FROM %s WHERE %s',
                    $table,
                    $where_params['where_statement']
                )
            );
            $this->statement->execute(
                $where_params['where_values']
            );

            $this->affected_rows = $this->statement->rowCount();

        } catch(PDOException $e) {
            $this->errno = $e->getCode();
            $this->error = $e->getMessage();
        }
    }

    function select($columns = array(), $table, $where = array(), $order = array(), $limit = false)
    {
        if (!is_array($columns)) {
            $select_columns = $columns;
        } elseif (count($columns) == 0) {
            $select_columns = "*";
        } else {
            foreach ($columns as $column_key => $column_name) {
                $columns[$column_key] = $this->encapsulate_column_name($column_name);
            }
            $select_columns = implode(',', $columns);
        }

        $where_params = $this->parametrize_where_array($where);

        if (count($order) > 0) {
            $is_order = true;
            $orders = array();
            foreach ($order as $ok => $ov) {
                $orders[] = $this->encapsulate_column_name($ok) . " " . $ov;
            }
            $select_order = implode(",", $orders);
        } else {
            $is_order = false;
        }

        $q = sprintf('SELECT %s FROM %s WHERE %s',
            $select_columns,
            $table,
            $where_params['where_statement']
        );

        if ($is_order) {
            $q = sprintf('%s ORDER BY %s', $select_order);
        }

        if ($limit !== false) {
            $q = sprintf('%s LIMIT %i', $q, $limit);
        }

        try {
            $this->statement =  $this->db->prepare($q);
            $this->statement->execute(
                $where_params['where_values']
            );

            $this->affected_rows = $this->statement->rowCount();



        } catch(PDOException $e) {
            $this->errno = $e->getCode();
            $this->error = $e->getMessage();
        };
    }

    /* Select places with coordinates within $radius miles/kilometers of a point */
    function select_geo($table, $latitude, $longitude, $radius = 0, $results = 0, $miles = true, $additional_where = false)
    {
        $coord_cols = $this->geo_detect_coord_cols($table);

        $q = "SELECT *, (" . ($miles ? "3959" : "6371") . " * acos(cos(radians(" . $latitude . ")) * cos(radians(" . $this->lat_col . ")) * cos(radians(" . $this->lon_col . ") - radians(" . $longitude . ")) + sin(radians(" . $latitude . ")) * sin(radians(" . $this->lat_col . ")))) AS distance FROM " . $this->encapsulate_column_name($table);

        if ($radius > 0) {
            $q .= " HAVING distance < " . $radius;
        }
        if ($additional_where !== false) {
            $q .= "WHERE $additional_where";
        }
        $q .= " ORDER BY distance";
        if ($results > 0) {
            $q .= " LIMIT $results;";
        }

        return $this->query($q);
    }



    function table2array($table)
    {
        $items = $this->select("*", $table);
        $retn = array();
        while ($item = $items->fetch_assoc()) {
            $retn["$item[key]"] = $item['val'];
        }
        return $retn;
    }

    /* **
     * Check to see if $data exists in $table, and return it if it does, or false if not.
     */
    function record_exists($table, $data)
    {
        $pairs = array();
        foreach ($data as $key => $value) {
            if (!is_numeric($value)) {
                $pairs[] = $this->encapsulate_column_name($key) . " = '" . $this->escape($value) . "'";
            } else {
                $pairs[] = $this->encapsulate_column_name($key) . " = $value";
            }
        }
        $where = implode(" AND ", $pairs);
        $records = $this->select("*", $table, $data);
        if ($records->num_rows > 0) {
            return $records;
        } else {
            return false;
        }
    }

    function run($sql_file)
    {
        $sql_file_contents = file_get_contents($sql_file);
        $rawsql = explode("\n", $sql_file_contents);
        $q = "";
        $clean_query = "";
        foreach ($rawsql as $sql_line) {
            if (trim($sql_line) != "" && strpos($sql_line, "--") === false) {
                $clean_query .= $sql_line;
                if (preg_match("/(.*);/", $clean_query)) {
                    $clean_query = stripslashes(substr($clean_query, 0, strlen($clean_query)));
                    $q = $clean_query;
                    $this->last_query = $q;
                    $result = $this->query($q);
                    if (!$result) {
                        die("<pre>" . print_r($this->error_details(), true) . "</pre>");
                    } else {
                        $q = "";
                        $clean_query = "";
                    }
                }
            }
        }
    }

    /* Error Handling */
    private function check_errors()
    {
        switch ($this->db['phptype']) {
            case "sqlite":
                if ($this->sqlite_version == 3) {
                    $this->errno = $this->conn->lastErrorCode();
                    $this->error = $this->conn->lastErrorMsg();
                } else {
                    $this->errno = sqlite_last_error($this->conn);
                    $this->error = sqlite_error_string($this->errno);
                }
                break;
            case "mysql":
                $this->errno = mysql_errno($this->conn);
                $this->error = mysql_error($this->conn);
                break;
            case "mysqli":
                if (mysqli_connect_error()) {
                    die('Connect Error: ' . mysqli_connect_error());
                }
                $this->errno = mysqli_errno($this->conn);
                $this->error = mysqli_error($this->conn);
                break;
        }

    }

    function error_details()
    {
        return array("query" => $this->last_query, "errno" => $this->errno, "error" => $this->error);
    }

    function encapsulate_column_name($column_name)
    {
        switch ($this->db['phptype']) {
            case "sqlite":
                return "[" . $column_name . "]";
                break;
            case "mysql":
                return "`" . $column_name . "`";
                break;
            case "mysqli":
                return "`" . $column_name . "`";
                break;
        }
    }

    function geo_detect_coord_cols($table)
    {
        $res = $this->select("*", $table, array(), "", array(), 1)->fetch_assoc();
        $lat_keys = array("lat", "latitude");
        $lon_keys = array("lon", "lng", "longitude");
        foreach ($res as $res_key => $res_val) {
            if (in_array($res_key, $lat_keys)) {
                $this->lat_col = $res_key;
            }
            if (in_array($res_key, $lon_keys)) {
                $this->lon_col = $res_key;
            }
        }
        return array("lat_col" => $this->lat_col, "lon_col" => $this->lon_col);
    }

    /* **
     * Basic DSN Parser.
     *
     * phptype://[username]@[host]:[password]/[database][arguments]
     * or
     * array([dsn parameters])
     */
    function parseDSN($dsn)
    {
        if (is_array($dsn)) {
            return $dsn;
        } else {
            $dsn_match = "|^(?P<phptype>.*?)://(?P<parameters>.*?)/(?P<db>.*?)$|i";
            $parameter_match = "|^(?P<username>.*?):(?P<password>.*?)@(?P<hostspec>.*?)$|i";
            $args_match = '|^(?P<database>.*?)\?(?P<args>.*?)$|';

            /* First, pick out the phptype, database, and gunk in the middle */
            preg_match($dsn_match, $dsn, $matches['base']);
            /* Next, parse the gunk in the middle into username, host, and password */
            if (!empty($matches['base']['parameters'])) {
                preg_match($parameter_match, $matches['base']['parameters'], $matches['parameters']);
            }
            /* Then, parse the database to see if any commandline parameters were passed */
            if (substr_count($matches['base']['db'], "?") > 0) {
                preg_match($args_match, $matches['base']['db'], $matches['args']);
            }

            $retn = array();
            $retn['phptype'] = $matches['base']['phptype'];
            if (!empty($matches['base']['parameters']) && is_array($matches['parameters'])) {
                $retn['username'] = $matches['parameters']['username'];
                $retn['password'] = $matches['parameters']['password'];
                $retn['hostspec'] = $matches['parameters']['hostspec'];
            }
            if (!empty($matches['args']) && is_array($matches['args'])) {
                $retn['database'] = $matches['args']['database'];
                $retn['args'] = $this->parseDSN_args($matches['args']['args']);
            } else {
                $retn['database'] = $matches['base']['db'];
            }
            return $retn;
        }
    }

    /* Helper function to break dsn arguments into an array */
    function parseDSN_args($args)
    {
        $retn = array();
        foreach (explode("&", $args) as $arg) {
            list($key, $val) = explode("=", trim($arg));
            $retn[$key] = $val;
            unset($key, $val);
        }
        return $retn;
    }
}

class sqlite_result
{
    var $result = NULL;
    var $conn = NULL;
    var $num_rows = 0;
    var $field_count = 0;

    function __construct($result, $conn)
    {
        $this->result = $result;
        $this->conn = $conn;
        $this->num_rows = sqlite_num_rows($this->result);
        $this->field_count = sqlite_num_fields($this->result);
    }

    function data_seek($rownum)
    {
        return sqlite_seek($this->result, $rownum);
    }

    /* If you want these functions to be like the MySQLiResult Class, the flag should be SQLITE_NUM */
    function fetch_all($result_type = SQLITE_BOTH)
    {
        return sqlite_fetch_all($this->result, $result_type, true);
    }

    function fetch_array($result_type = SQLITE_BOTH)
    {
        return sqlite_fetch_array($this->result, $result_type, true);
    }

    function fetch_assoc()
    {
        return sqlite_fetch_array($this->result, SQLITE_ASSOC);
    }
}

class sqlite3_result
{
    var $result = NULL;
    var $conn = NULL;
    var $num_rows = 0;
    var $field_count = 0;

    function __construct($result, $conn)
    {
        $this->result = $result;
        $this->conn = $conn;
        $this->num_rows = $this->count_rows();
        $this->field_count = $this->result->numColumns();
    }

    function data_seek($rownum)
    {
        if ($rownum == 0) {
            return $this->result->reset();
        } else {
            return false;
        }
    }

    function count_rows()
    {
        $retn = 0;
        while ($item = $this->fetch_array()) {
            $retn++;
        }
        return $retn;
    }

    /* If you want these functions to be like the MySQLiResult Class, the flag should be SQLITE_NUM */
    function fetch_all($result_type = SQLITE_BOTH)
    {
        $retn = array();
        while ($item = $this->fetch_array()) {
            $retn[] = $item;
        }
        return $retn;
    }

    function fetch_array($result_type = SQLITE3_BOTH)
    {
        return $this->result->fetchArray($result_type);
    }

    function fetch_assoc()
    {
        return $this->fetch_array(SQLITE3_ASSOC);
    }
}

class mysql_result
{
    var $result = NULL;
    var $conn = NULL;
    var $num_rows = 0;
    var $field_count = 0;

    function __construct($result, $conn)
    {
        $this->result = $result;
        $this->conn = $conn;
        $this->num_rows = mysql_num_rows($this->result);
    }

    function data_seek($rownum)
    {
        return mysql_data_seek($this->result, $rownum);
    }

    /* If you want these functions to be like the MySQLiResult Class, the flag should be MYSQL_NUM */
    function fetch_all($result_type = MYSQL_BOTH)
    {
        return mysql_fetch_array($this->result, $result_type);
    }

    function fetch_array($result_type = MYSQL_BOTH)
    {
        return mysql_fetch_array($this->result, $result_type);
    }

    function fetch_assoc()
    {
        return mysql_fetch_assoc($this->result);
    }
}

?>
