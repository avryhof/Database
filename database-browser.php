<?
	
  if (file_exists("config.inc.php")) {
    require_once("config.inc.php");
  } elseif (file_exists("../config.inc.php")) {
    require_once("../config.inc.php");
  } else {
    define('DB_HOST', 'host');
    define('DB_USER', 'user');
    define('DB_NAME', 'database');
    define('DB_PASS', 'password');
    
    $basepath = __DIR__;
    $baseurl = "http" . ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? "s" : "") . "://" . $_SERVER['HTTP_HOST'] . pathinfo($_SERVER['PHP_SELF'],PATHINFO_DIRNAME);
    require_once("database.php");    
    $db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  }
  
  if (!is_array($bootstrap)) {
    $bootstrap = array(
      'version'   => '3.3.2',
      'theme'     => 'default'
    );
  } 
	
	if ($_POST['action'] == "execute_sql") {
		
		if (!empty($_FILES['sql_file']) && !empty($_FILES['sql_file']['tmp_name'])) {
			$sql = stripslashes(file_get_contents($_FILES['sql_file']['tmp_name']));
		} elseif (!empty($_POST['sql'])) {
			$sql = stripslashes($_POST['sql']);
		}
		if (substr_count($sql,';') > 1) {
			$queries = explode(';',$sql);
			$is_error = false;
			foreach($queries as $query) {
				$db->query($query);
				if ($db->errno > 0) {
					$is_error = true;
					echo "<pre>ERROR: " . $db->error . "\n\nQUERY:\n\n" . $db->last_query . "\n\nSQL:\n\n" . $sql . "</pre>";
				}
			}
			if (!$is_error) {
				header("Location:".$_SERVER['PHP_SELF']);
			}
		} else {
			$db->query($sql);
			if ($db->errno > 0) {
				echo "<pre>ERROR: " . $db->error . "\n\nQUERY:\n\n" . $db->last_query . "\n\nSQL:\n\n" . $sql . "</pre>";
			} else {
				header("Location:".$_SERVER['PHP_SELF']);
			}
		}
	}
	
	$database = (!empty($_GET['database']) ? $_GET['database'] : DB_NAME);
	$table = $_GET['table'];
	$show = (!empty($_GET['show']) ? $_GET['show'] : 'structure');
	
	if (!empty($database)) {
		$db_tables = $db->query("SHOW TABLES FROM `$database`");
	}
	
	$scriptname = basename(__FILE__);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->

    <title>Database Browser</title>

    <!-- Bootstrap core CSS -->
    <link href="//maxcdn.bootstrapcdn.com/bootstrap/<?= $bootstrap['version']; ?>/css/bootstrap.min.css" rel="stylesheet">
    
    <? if (!isset($bootstrap['theme']) || empty($bootstrap['theme']) || $bootstrap['theme'] == "default") { ?>
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/<?= $bootstrap['version']; ?>/css/bootstrap-theme.min.css">
 	  <? } else { ?>
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootswatch/<?= $bootstrap['version']; ?>/<?= $bootstrap['theme']; ?>/bootstrap.min.css">
    <? } ?>
    
    <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet">
    <link href="//cdnjs.cloudflare.com/ajax/libs/codemirror/4.12.0/codemirror.min.css" rel="stylesheet">
    <link href="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.1.0/addon/hint/show-hint.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <style>
		body {
			padding-top: 50px;
		}
		.starter-template {
			padding: 40px 15px;
			text-align: center;
		}
	</style>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>

  <body>

    <nav class="navbar navbar-inverse navbar-fixed-top">
      <div class="container-fluid">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="#">Database Browser</a>
        </div>
        <div id="navbar" class="collapse navbar-collapse">
		<ul class="nav navbar-nav">
        <? if (file_exists("index.php")) { ?>
        	<li><a href="index.php">Admin</a></li>
        <? } ?>
        </ul>
        <ul class="nav navbar-nav navbar-right">
            <li><a href="user.php?username=<?= $_SERVER['PHP_AUTH_USER']; ?>"><i class="fa fa-user"></i> <?= $_SERVER['PHP_AUTH_USER']; ?></a></li>
        </ul>
        </div><!--/.nav-collapse -->
      </div>
    </nav>
    
    <div class="container-fluid">
    
    <div class="row">
    	<div class="col-xs-12">
        	<ol class="breadcrumb">
            	<li><a href="<?= $scriptname; ?>">Home</a></li>
                <? if (!empty($database)) { ?>
                <li><a href="<?= $scriptname.'?database='.$database; ?>" <?= (empty($table) ? 'class="active"' : ''); ?>><?= $database; ?></a></li>
                <? } ?>
                <? if (!empty($table)) { ?>
                <li <?= (empty($show) ? 'class="active"' : ''); ?>><a href="<?= $scriptname.'?database='.$database.'&table='.$table; ?>"><?= $table; ?></a></li>
                <? } ?>
                <? if (!empty($table) && !empty($show)) { ?>
                <li class="active"><a href="<?= $scriptname.'?database='.$database.'&table='.$table,'&show='.$show; ?>"><?= $show; ?></a></li>
                <? } ?>
            </ol>
        </div>
    </div>
    
    <div class="row">
    
		<div class="col-xs-12 col-sm-3">
        	<a href="<?= $scriptname; ?>?show=sql" class="btn btn-primary">SQL command</a>
			<? if ($db_tables->num_rows > 0) { ?>
            <ul>
                <? while($db_table = $db_tables->fetch_assoc()) { ?>
                    <li><a href="<?= $scriptname.'?database='.$database.'&table='.$db_table['Tables_in_'.$database]; ?>"><?= $db_table['Tables_in_'.$database]; ?></a></li>        
                <? } ?>
            </ul>
            <? } ?>
        </div>
        <div class="col-xs-12 col-sm-9">
        
       		<ul class="nav nav-tabs">
            	<li role="presentation" <?= ($show == "structure" ? 'class="active"' : ''); ?>><a href="<?= $scriptname.'?database='.$database.'&table='.$table,'&show=structure'; ?>">Structure</a></li>
                <li role="presentation" <?= ($show == "data" ? 'class="active"' : ''); ?>><a href="<?= $scriptname.'?database='.$database.'&table='.$table,'&show=data'; ?>">Data</a></li>
                <li role="presentation" <?= ($show == "code" ? 'class="active"' : ''); ?>><a href="<?= $scriptname.'?database='.$database.'&table='.$table,'&show=code'; ?>">Code</a></li>
                <li role="presentation" <?= ($show == "sql" ? 'class="active"' : ''); ?>><a href="<?= $scriptname.'?database='.$database.'&table='.$table,'&show=sql'; ?>">SQL Command</a></li>
            </ul>
            
            <? 
				if ($show == "structure" && !empty($table)) {
					$header = 0;
					$columns = $db->query("SHOW COLUMNS FROM `$table`");
					if ($columns->num_rows > 0) {
						?>
                        
                    <div class="panel">
                    	<div class="panel-body">
                            <div class="btn-toolbar" role="toolbar">
                                <div class="btn-group" role="group">
                                    <a href="adminer/?server=<?= DB_HOST; ?>&username=<?= DB_USER; ?>&db=<?= $database; ?>&table=<?= $table; ?>" class="btn btn-info" target="_blank">Alter Table</a>
                                </div>
                            </div>
                        </div>
                    </div>
                        
                        <table class="table table-striped table-bordered">
                        <?
						while($column = $columns->fetch_assoc()) {
					?>
                    	<?
							if ($header == 0) { 
						?>
                        <thead>
                        <tr>
                        	<th><?= implode('</th><th>', array_keys($column)); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?
							$header++;
							}
						?>
            			
            			<tr>
                        	<td><?= implode('</td><td>', $column); ?></td>
                        </tr>            
            <?
						}
					?>
                    </tbody>
                    </table>
                    <?
					}
				}
			?>
            
            <?
				if ($show == "data") {
					$header = 0;
					$items = $db->query("SELECT * FROM `$table`");
					if ($items->num_rows > 0) {
					?>
                    
                    <div class="panel">
                    	<div class="panel-body">
                            <div class="btn-toolbar" role="toolbar">
                                <div class="btn-group" role="group">
                                    <a href="<?= $scriptname; ?>?show=sql" class="btn btn-info" target="_blank">SQL Command</a>
                                </div>
                            </div>
                        </div>
                     </div>
                    
                    <table class="table table-striped table-bordered">
                    <?
						while($item = $items->fetch_assoc()) {
					?>
                    	<? 
							if ($header == 0) { 
							
						?>
                        <thead>
                        <tr>
                        	<th><?= implode('</th><th>', array_keys($item)); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <? 
							$header++;
							} 
						?>
                        
                        <tr>
                        	<td><?= implode('</td><td>', $item); ?></td>
                        </tr>
                    <?
						}
					}
				}
			?>
            
            <? 
				if ($show == "sql") { 
				
				if (!empty($table)) {
					$columns = array_keys($db->query("SELECT * FROM `$table` LIMIT 1")->fetch_assoc());
				}
			?>
            
            
            <form action="<?= $scriptname; ?>" method="post" class="form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="execute_sql">
            <div class="form-group">
            	<label for="sql">SQL Query</label>
            	<textarea name="sql" id="sql" class="form-control" style="width:100%"></textarea>
            </div>
            <div class="form-group">
            	<label for="upload">Upload</label>
                <input type="file" name="sql_file" id="sql_file">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Execute</button>
            </div>
            </form>
            
           <div role="tabpanel">
           		<!-- Nav tabs -->
                <ul class="nav nav-tabs" role="tablist">
                	<li role="presentation" class="active"><a href="#basics" aria-controls="basics" role="tab" data-toggle="tab">BASICS</a></li>
                    <li role="presentation"><a href="#create" aria-controls="create" role="tab" data-toggle="tab">CREATE TABLE</a></li>
                    <li role="presentation"><a href="#alter" aria-controls="alter" role="tab" data-toggle="tab">ALTER TABLE</a></li>
                </ul>
                
                <!-- Tab panes -->
                <div class="tab-content">
                	<div role="tabpanel" class="tab-pane active" id="basics">
                    	<p><strong>INSERT</strong></p>
                        <pre class="codesample">INSERT INTO `<?= (!empty($table) ? $table : 'table'); ?>` (<?= (is_array($columns) ? '`'.implode('`,`',$columns).'`' : '`col1`,`col2`,`col3`'); ?>) VALUES (<?= (is_array($columns) ? "'".implode("','",$columns)."'" : "'val1','val2','val3'"); ?>);</pre>
                        <p><strong>UPDATE</strong></p>
                        <pre class="codesample">UPDATE `<?= (!empty($table) ? $table : 'table'); ?>` SET <? foreach($columns as $ckey => $column) { echo "`$column` = '$column'"; echo ($ckey !== (count($columns) - 1) ? ', ' : ''); } ?> WHERE `id` = 'id';</pre>
                        <p><strong>DELETE</strong></p>
                        <pre class="codesample">DELETE FROM `<?= (!empty($table) ? $table : 'table'); ?>` WHERE `id` = 'id';</pre>
                    </div>
    				<div role="tabpanel" class="tab-pane" id="create">
                    	<p><strong>CREATE TABLE</strong></p>
                        <pre class="codesample">CREATE TABLE `<?= (!empty($table) ? $table : 'table'); ?>` (
<?
							$columns = $db->query("SHOW COLUMNS FROM `$table`");
							if ($columns->num_rows > 0) {
								while($column = $columns->fetch_assoc()) {
									if ($column['Key'] == "PRI") {
										$primary_key = $column['Field'];
									}
						?>    `<?= $column['Field']; ?>` <?= $column['Type']; ?> <?= ($column['Null'] == "NO" ? 'NOT NULL' : 'NULL'); ?> <?= $column['Extra']; ?>,
<?
								}
							}
							echo "    KEY (`$primary_key`)\n"; ?>);
                        </pre>
                    </div>
    				<div role="tabpanel" class="tab-pane" id="alter">
                    <p><strong>ADD COLUMN</strong></p>
                    <pre class="codesample">ALTER TABLE `<?= (!empty($table) ? $table : 'table'); ?>` ADD column_name datatype;</pre>
					<p><strong>DELETE COLUMN</strong></p>
                    <pre class="codesample">ALTER TABLE `<?= (!empty($table) ? $table : 'table'); ?>` DROP COLUMN column_name;</pre>
					<p><strong>CHANGE COLUMN TYPE</strong></p>
                    <pre class="codesample">ALTER TABLE `<?= (!empty($table) ? $table : 'table'); ?>` MODIFY COLUMN column_name datatype;</pre>
                    </div>
  				</div>

			</div> 
            
            <? } ?>
            
            <? 
				if ($show == "code") { 
					$columns = $db->query("SHOW COLUMNS FROM `$table`");
			?>
				<div class="panel panel-default">
                		<div class="panel-heading">
                    		<h3 class="panel-title">Retrieve Data</h3>
                    	</div>
                    	<div class="panel-body">
                        	<pre>$items = $db->query("SELECT * FROM `<?= $table; ?>`");</pre>
                        </div>
                    </div>
                    
                    <div class="panel panel-default">
                		<div class="panel-heading">
                    		<h3 class="panel-title">Save Data</h3>
                    	</div>
                    	<div class="panel-body">
                        <pre>$action = $_REQUEST['action'];
	
$table = '<?= $table; ?>';

if ($action == "edit" || $action == "delete") {
    $id =  intval($_REQUEST['id']);
    $where = "`id` = $id";
}

if ($action == "add" || $action == "edit") {
    $data = array(
<?
        $n = 0;
		$padwidth = 16;
		$prepad = 8;
        while($column = $columns->fetch_assoc()) {
            $n++;
            if ($column['Field'] !== "id" && $column['Field'] !== "created") {
				if (preg_match('/int\(\d+\)/',$column['Type']) && $column['Field'] == "active") {
					echo str_repeat(" ", $prepad).str_pad('"'.$column['Field'].'"',$padwidth).' => (intval($_POST[\''.$column['Field'].'\']) == 1 ? 1 :0)';
				} elseif (preg_match('/int\(\d+\)/',$column['Type'])) {
                    echo str_repeat(" ", $prepad).str_pad('"'.$column['Field'].'"',$padwidth).' => intval($_POST[\''.$column['Field'].'\'])';
                } elseif ($column['Type'] == "date") {
                    echo str_repeat(" ", $prepad).str_pad('"'.$column['Field'].'"',$padwidth).' => date("Y-m-d",strtotime($_POST[\''.$column['Field'].'\']))';
				} elseif ($column['Type'] == "datetime" && ($column['Field'] == "updated" || $column['Field'] == "modified")) {
                    echo str_repeat(" ", $prepad).str_pad('"'.$column['Field'].'"',$padwidth).' => date("Y-m-d H:i:s")';
                } elseif ($column['Type'] == "datetime") {
                    echo str_repeat(" ", $prepad).str_pad('"'.$column['Field'].'"',$padwidth).' => date("Y-m-d H:i:s",strtotime($_POST[\''.$column['Field'].'\']))';
                } else {
                    echo str_repeat(" ", $prepad).str_pad('"'.$column['Field'].'"',$padwidth).' => $_POST[\''.$column['Field'].'\']';
                }
                echo ($n !== $columns->num_rows ? ',' : '')."\n";
            }
        }
    ?>
    );
    if ($action == "add") { 
        $data["created"] = date("Y-m-d H:i:s"); 
    }
}</pre>
                    	</div>
                    </div>            
            <?
				}
			?>
        
        </div>

    </div>
    
    </div>


    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
    <script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
    <? if ($show == "sql") { ?>
    <script src="//cdnjs.cloudflare.com/ajax/libs/codemirror/4.12.0/codemirror.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.0.0/mode/sql/sql.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/codemirror/5.0.0/addon/hint/sql-hint.min.js"></script>
    <script>
	var mime = 'text/x-mariadb';
	window.editor = CodeMirror.fromTextArea(document.getElementById("sql"), {
		mode: mime,
		indentWithTabs: true,
		smartIndent: true,
		lineNumbers: true,
		matchBrackets : true,
		autofocus: true,
		extraKeys: {"Ctrl-Space": "autocomplete"},
		hintOptions: {tables: {
			users: {name: null, score: null, birthDate: null},
			countries: {name: null, population: null, size: null}
		}}
	});
	$(document).ready(function(e) {
        if ($(".codesample").length > 0) {
			$(".codesample").dblclick(function(e) {
				codevalue = window.editor.doc.getValue() + "\n" + $(this).html();
				window.editor.doc.setValue(codevalue);
            });
		}
    });
	</script>
    <? } ?>
  </body>
</html>
