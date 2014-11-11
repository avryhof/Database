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
		
*/

// error_reporting(E_ALL);		

class Database {
 	var $db, $last_query, $conn, $affected_rows, $connect_error, $last, $errno, $error, $exists, $parent_class, $sqlite_version;
 	var $lat_col = "latitude";
	var $lon_col = "longitude";
 	
 	function __construct() {
 	 	switch(func_num_args()) {
 	 	 	case 5:
 	 	 		$dsn = array("phptype"=>func_get_arg(0),"username"=>func_get_arg(2),"password"=>func_get_arg(3),"hostspec"=>func_get_arg(1),"database"=>func_get_arg(4));
 	 	 		break;
 	 	 	case 4:
 	 	 		$dsn = array("phptype"=>"mysqli","username"=>func_get_arg(1),"password"=>func_get_arg(2),"hostspec"=>func_get_arg(0),"database"=>func_get_arg(3));
 	 	 		break;
			default:
			   $dsn = func_get_arg(0);
 	 	}
		
 	 	$this->db = $this->parseDSN($dsn);
		 	 	
 	 	switch($this->db['phptype']) {
 	 		case "sqlite":
				$this->exists = file_exists($this->db['database']);
				$this->sqlite_version = (class_exists("SQLite3") ? 3 : 2);
				
				if ($this->sqlite_version == 3) {
					$this->conn = new SQLite3($this->db['database']);
				} else {
					$this->conn = sqlite_open($this->db['database'],(!empty($this->db['args']['type']) ? $this->db['args']['type'] : 0666), $this->connect_error);
 	 				if (!empty($this->connect_error)) { die("<pre>" . $this->connect_error . "</pre>"); }
				}
 	 			break;
 	 		case "mysql":
 	 			$this->conn = mysql_connect($this->db['hostspec'],$this->db['username'],$this->db['password']);
 	 			if (!$this->conn) { $this->connect_error = mysql_error($this->conn); } else { mysql_select_db($this->db['database'],$this->conn); }
 	 			break;
 	 		case "mysqli":
 	 			$this->conn = mysqli_connect($this->db['hostspec'],$this->db['username'],$this->db['password'],$this->db['database']);
 	 			break;
 	 	}
 	 	$this->check_errors();
	} 	
	
 	function query($query) {
 	 	$this->last_query = $query;
 	 	switch($this->db['phptype']) {
 	 		case "sqlite":
				if ($this->sqlite_version == 3) {
					$result = $this->conn->query($query);
					$this->check_errors();
					$this->last = $this->conn->lastInsertRowID();
					return new sqlite3_result($result, $this->conn);
				} else {
 	 				$result = sqlite_query($this->conn, $query, $error);
 	 				if (!$result) { die("<pre>$query \n $error</pre>"); }
 	 				$this->affected_rows = sqlite_changes($this->conn);
 	 				$this->check_errors();
 	 				$this->last = sqlite_last_insert_rowid($this->conn);
 	 				return new sqlite_result($result, $this->conn);
				}
 	 			break;
 	 		case "mysql":
 	 			$result = mysql_query($query,$this->conn);
 	 			$this->affected_rows = mysql_affected_rows($this->conn);
 	 			$this->check_errors();
 	 			$this->last = mysql_insert_id($this->conn);
 	 			return new mysql_result($result, $this->conn);
 	 			break;
 	 		case "mysqli":
 	 			$result = mysqli_query($this->conn,$query);
 	 			$this->affected_rows = mysqli_affected_rows($this->conn);
 	 			$this->check_errors();
 	 			$this->last = mysqli_insert_id($this->conn);
 	 			return $result;
 	 			break;
 	 	}
 	}
	
 	function escape($string) { 
 	 	if (is_numeric($string)) {
 	 	 	return $string;
 	 	} else {
 	 	 	return $this->real_escape_string($string);
 	 	}
 	}
 	
 	function real_escape_string($string) {
 	 	switch($this->db['phptype']) {
 	 		case "sqlite":
				if ($this->sqlite_version == 3) {
					return $this->conn->escapeString($string);
				} else {
					return sqlite_escape_string($string);
				}
 	 			break;
 	 		case "mysql":
 	 			return mysql_real_escape_string($string,$this->conn);
 	 			break;
 	 		case "mysqli":
 	 			return mysqli_real_escape_string($this->conn,$string);
 	 			break;
 	 	}
 	}
	
	function select($columns = array(), $table, $where = array(), $where_operator = "AND", $order = array(), $limit = false) {
		if (count($columns) == 0) {
			$select_columns = "*";
		} else {
			foreach($columns as $column_key => $column_name) {
				$columns[$column_key] = $this->encapsulate_column_name($column_name);
			}
			$select_columns = implode(',',$columns);
		}
		/* TODO: Figure out how to mix AND/OR/LIKE Operators */
		if (count($where) > 0) {
			$is_where = true;
			$select_wheres = array();
			foreach ($where as $key => $value) {
				$select_wheres[] = encapsulate_column_name($key). " = " . (is_numeric($value) ? $value : "'$value'");
			}
			$select_where = implode(" ". $where_operator ." ",$select_wheres);
		} else {
			$is_where = false;
		}
		if (count($order) > 0) {
			$is_order = true;
			$orders = array();
			foreach($order as $ok => $ov) {
				$orders[] = encapsulate_column_name($ok)." ".$ov;
			}
			$select_order = implode(",",$orders);
		} else {
			$is_order = false;
		}

		$q = "SELECT $select_columns FROM ".$this->encapsulate_column_name($table).($is_where ? " WHERE $select_where" : "").($is_order ? " ORDER BY $select_order" : "").($limit !== false ? " LIMIT $limit" : "");
		return $this->query($q);
	}
  
	/* Select places with coordinates within $radius miles/kilometers of a point */
	function select_geo($table, $latitude, $longitude, $radius = 0, $results = 0, $miles = true, $additional_where = false) {
		$coord_cols = $this->geo_detect_coord_cols($table);
		
		$q = "SELECT *, (".($miles ? "3959" : "6371")." * acos(cos(radians(".$latitude.")) * cos(radians(".$this->lat_col.")) * cos(radians(".$this->lon_col.") - radians(".$longitude.")) + sin(radians(".$latitude.")) * sin(radians(".$this->lat_col.")))) AS distance FROM ".$this->encapsulate_column_name($table);
    
		if ($radius > 0) { $q .= " HAVING distance < ".$radius; }
		if ($additional_where !== false) { $q.= "WHERE $additional_where"; }
		$q .= " ORDER BY distance";
		if ($results > 0) { $q .= " LIMIT $results;"; }
		
		return $this->query($q);
	}
   	
 	/* Query Commands ... INSERT, UPDATE, DELETE */
	function insert($table, $data) {
		$q = "INSERT INTO $table ";
		$v=''; $n='';
		foreach($data as $key=>$val) {
			$n .=" ".$this->encapsulate_column_name($key).", ";
			if(strtolower($val)=='null') {
				$v.="NULL, ";
			} elseif(strtolower($val)=='now()') {
				$v.="NOW(), ";
			} else {
				$v.= "'".$this->escape($val)."', ";
			}
		}
		$q .= "(". rtrim($n, ', ') .") VALUES (". rtrim($v, ', ') .");";
		return $this->query($q);
	}
	
	function update($table, $data, $where) {
		if (is_array($where) && count($where) > 0) {
			$update_wheres = array();
			foreach ($where as $key => $value) {
				$update_wheres[] = encapsulate_column_name($key). " = " . (is_numeric($value) ? $value : "'$value'");
			}
			$where = implode(" AND ",$update_wheres);
		}
		$q="UPDATE ".$table." SET ";
		foreach($data as $key=>$val) {
			if(strtolower($val)=='null') {
				$q.= $this->encapsulate_column_name($key)." = NULL, ";
			} elseif(strtolower($val)=='now()') {
				$q.= $this->encapsulate_column_name($key)." = NOW(), ";
			} else {
				$q.= $this->encapsulate_column_name($key)."='".$this->escape($val)."', ";
			}
		}
		$q = rtrim($q, ', ') . ' WHERE '.$where.';';
		return $this->query($q);
	}
	
	function delete($table, $where) {
		if (is_array($where) && count($where) > 0) {
			$delete_wheres = array();
			foreach ($where as $key => $value) {
				$delete_wheres[] = encapsulate_column_name($key). " = " . (is_numeric($value) ? $value : "'$value'");
			}
			$where = implode(" AND ",$delete_wheres);
		}		
		$q="DELETE FROM ".$table." WHERE $where;";
		return $this->query($q);
	}
	
	function table2array($table) {
		$items = $this->select("*",$table);
		$retn = array();
		while($item = $items->fetch_assoc()) {
			$retn["$item[key]"] = $item['val'];
		}
		return $retn;
	}
	
	/* **
	 * Check to see if $data exists in $table, and return it if it does, or false if not.
	 */
	function record_exists($table, $data) {
		$pairs = array();
		foreach($data as $key => $value) {
			if (!is_numeric($value)) {
				$pairs[] = $this->encapsulate_column_name($key)." = '".$this->escape($value)."'";
			} else {
				$pairs[] = $this->encapsulate_column_name($key)." = $value";
			}
		}
		$where = implode(" AND ", $pairs);
		$records = $this->select("*",$table,$data);
		if ($records->num_rows > 0) {
			return $records;
		} else {
			return false;
		}
	}
	
	function run($sql_file) {
		$sql_file_contents = file_get_contents($sql_file);
		$rawsql = explode("\n",$sql_file_contents);
		$q = "";
		$clean_query = "";
		foreach($rawsql as $sql_line) {
			if(trim($sql_line) != "" && strpos($sql_line, "--") === false) {
				$clean_query .= $sql_line;
				if(preg_match("/(.*);/", $clean_query)) {
					$clean_query = stripslashes(substr($clean_query, 0, strlen($clean_query)));
					$q = $clean_query;
					$this->last_query = $q;
					$result = $this->query($q);
					if (!$result) { 
						die("<pre>" . print_r($this->error_details(),true) . "</pre>"); 
					} else {
						$q = "";
						$clean_query = "";
					}
				}
			}
		}
	}	
	
	/* Error Handling */
 	private function check_errors() {
 	 	switch($this->db['phptype']) {
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
 	
 	function error_details() {
 	 	return array("query" => $this->last_query, "errno" => $this->errno, "error" => $this->error);
 	}
	
	function encapsulate_column_name($column_name) {
		switch($this->db['phptype']) {
 	 		case "sqlite":
 	 			return "[".$column_name."]";
 	 			break;
 	 		case "mysql":
 	 			return "`".$column_name."`";
 	 			break;
 	 		case "mysqli":
				return "`".$column_name."`";
 	 			break;
 	 	}		
	}
  
  function geo_detect_coord_cols($table) {
		$res = $this->select("*", $table, array(), "", array(), 1)->fetch_assoc();
		$lat_keys = array("lat","latitude");
		$lon_keys = array("lon","lng","longitude");
		foreach($res as $res_key => $res_val) {
			if (in_array($res_key,$lat_keys)) { $this->lat_col = $res_key; }
			if (in_array($res_key,$lon_keys)) { $this->lon_col = $res_key; }
		}
		return array("lat_col"=>$this->lat_col,"lon_col"=>$this->lon_col);
	} 	
	
	/* ** 
	 * Basic DSN Parser.
	 *
	 * phptype://[username]@[host]:[password]/[database][arguments]
	 * or
	 * array([dsn parameters])
	 */
	function parseDSN($dsn) {
	 	if (is_array($dsn)) { 
			return $dsn; 
		} else {
		 	$dsn_match = "|^(?P<phptype>.*?)://(?P<parameters>.*?)/(?P<db>.*?)$|i";
		 	$parameter_match = "|^(?P<username>.*?):(?P<password>.*?)@(?P<hostspec>.*?)$|i";
		 	$args_match = '|^(?P<database>.*?)\?(?P<args>.*?)$|';
		 	
		 	/* First, pick out the phptype, database, and gunk in the middle */
			preg_match($dsn_match,$dsn,$matches['base']);
			/* Next, parse the gunk in the middle into username, host, and password */
			if (!empty($matches['base']['parameters'])) { preg_match($parameter_match,$matches['base']['parameters'],$matches['parameters']); }
			/* Then, parse the database to see if any commandline parameters were passed */
			if (substr_count($matches['base']['db'],"?") > 0) { preg_match($args_match,$matches['base']['db'],$matches['args']); }
			
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
    function parseDSN_args($args) {
     	$retn = array();
     	foreach(explode("&",$args) as $arg) {
     	 	list($key,$val) = explode("=",trim($arg));
     	 	$retn[$key] = $val;
     	 	unset($key,$val);
     	}
     	return $retn;
    }
}

class sqlite_result {
 	var $result 	= NULL;
 	var $conn		= NULL;
 	var $num_rows	= 0;
 	var $field_count = 0;
 	
	function __construct($result, $conn) {
	 	$this->result 		= $result;
	 	$this->conn			= $conn;
	 	$this->num_rows		= sqlite_num_rows($this->result);
	 	$this->field_count 	= sqlite_num_fields($this->result);
	}
	
	function data_seek($rownum) {
	 	return sqlite_seek($this->result,$rownum);
	}
	
	/* If you want these functions to be like the MySQLiResult Class, the flag should be SQLITE_NUM */
	function fetch_all($result_type = SQLITE_BOTH) {
	 	return sqlite_fetch_all($this->result, $result_type, true);
	}
	
	function fetch_array($result_type = SQLITE_BOTH) {
	 	return sqlite_fetch_array($this->result, $result_type, true);
	}
	
	function fetch_assoc() {
	 	return sqlite_fetch_array($this->result, SQLITE_ASSOC);
	}
}

class sqlite3_result {
 	var $result 	= NULL;
 	var $conn		= NULL;
 	var $num_rows	= 0;
 	var $field_count = 0;
 	
	function __construct($result, $conn) {
	 	$this->result 		= $result;
	 	$this->conn			= $conn;
	 	$this->num_rows		= $this->count_rows();
	 	$this->field_count 	= $this->result->numColumns();
	}
	
	function data_seek($rownum) {
		if ($rownum == 0) {
			return $this->result->reset();
		} else {
			return false;
		}
	}
	
	function count_rows() {
		$retn = 0;
		while($item = $this->fetch_array()) {
			$retn++;
		}
		return $retn;
	}
	
	/* If you want these functions to be like the MySQLiResult Class, the flag should be SQLITE_NUM */
	function fetch_all($result_type = SQLITE_BOTH) {
		$retn = array();
		while($item = $this->fetch_array()) {
			$retn[] = $item;
		}
		return $retn;
	}
	
	function fetch_array($result_type = SQLITE3_BOTH) {
		return $this->result->fetchArray($result_type);
	}
	
	function fetch_assoc() {
		return $this->fetch_array(SQLITE3_ASSOC);
	}
}

class mysql_result {
 	var $result 	= NULL;
 	var $conn		= NULL;
 	var $num_rows	= 0;
 	var $field_count = 0;
 	
	function __construct($result, $conn) {
	 	$this->result 		= $result;
	 	$this->conn			= $conn;
	 	$this->num_rows		= mysql_num_rows($this->result);
	}
	
	function data_seek($rownum) {
	 	return mysql_data_seek($this->result,$rownum);
	}
	
	/* If you want these functions to be like the MySQLiResult Class, the flag should be MYSQL_NUM */
	function fetch_all($result_type = MYSQL_BOTH) {
	 	return mysql_fetch_array($this->result, $result_type);
	}
	
	function fetch_array($result_type = MYSQL_BOTH) {
	 	return mysql_fetch_array($this->result, $result_type);
	}
	
	function fetch_assoc() {
	 	return mysql_fetch_assoc($this->result);
	}
}
?>
