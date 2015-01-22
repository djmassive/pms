<?php
/**
	PHP Mysql Database Synchronization Tool
	Copyright (C) 2015 Grzegorz Miskiewicz
	All Rights Reserved
	
	You can use this script also in commercial work
	Please leave only this comment
	
	IF YOUR DATA BASE USES OTHER THAN UTF8 CHARSET ENCODE, PLEASE CHANGE
	LINES: 81, 86
**/

	error_reporting("E_ALL & ~E_NOTICE & ~E_WARNING");
	echo 'Start sync...' . PHP_EOL;
	
/**
	Source database configuration
**/

	$cl_host = "localhost";
	$cl_name = "dbname";
	$cl_user = "root";
	$cl_pass = "root";
	
/**
	Remote database configuration
**/

	$cr_host = "remote.com";
	$cr_name = "dbname";
	$cr_user = "user";
	$cr_pass = "user";
	
	function mysql_copy($from, $to, $table) {
		unset($result);
		$result = mysql_query('SELECT * FROM '.$table, $from);
		if(!empty($result)) {
			$num_fields = mysql_num_fields($result);
		}
			
		mysql_query('DROP TABLE IF EXISTS ' . $table, $to);
			
		$tt = mysql_query("SHOW CREATE TABLE ".$table, $from);
			
	    $row2 = mysql_fetch_row($tt);
	    $row2[1] = preg_replace("/\n/" , "" , $row2['1']);
	    mysql_query($row2[1], $to);

	    $return = '';	
		if($num_fields>0) {
	        for ($i = 0; $i < $num_fields; $i++) 
	        {
	            while($row = mysql_fetch_row($result))
	            {
	                $return.= 'INSERT INTO '.$table.' VALUES(';
	                for($j=0; $j<$num_fields; $j++) 
	                {
	                    $row[$j] = addslashes($row[$j]);
	                    $row[$j] = @ereg_replace("\n","\\n",$row[$j]);
	                    if (isset($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= '""'; }
	                    if ($j<($num_fields-1)) { $return.= ','; }
	                }
	                $return.= ");\n";
	                $res = mysql_query($return, $to);
		            if($res === false) {
						echo 'ERROR: Table ' . $table . ' cannot be filled with data ' . PHP_EOL . $return . PHP_EOL . PHP_EOL;
					}
		            $return = '';
	            }
	            
	        }
        }
	   
	    
	}
	
	// Connecting local
	$ll = @mysql_connect($cl_host, $cl_user, $cl_pass);
	mysql_select_db($cl_name, $ll);
	mysql_query("SET NAMES UTF8", $ll);
	
	//Connect remote
	$rl = @mysql_connect($cr_host, $cr_user, $cr_pass);
	mysql_select_db($cr_name, $rl);
	mysql_query("SET NAMES UTF8", $rl);
	
	// Get local schema
	$schema_local = mysql_query("SHOW FULL TABLES FROM ".$cl_name, $ll);
	
	// Get remote schema
	$schema_remote = mysql_query("SHOW FULL TABLES FROM ".$cr_name , $rl);
	
	while($row = mysql_fetch_row($schema_local)) {
		$local['tables'][] = $row[0];
	}

	while($row = mysql_fetch_row($schema_remote)) {
		$remote['tables'][] = $row[0];
	}

	echo 'Comparing table schemas...' . PHP_EOL;

	$diff = array_diff($local['tables'], $remote['tables']);
	if(!empty($diff)) {
		$return = '';
		foreach($diff as $table) {
			echo 'Create table: ' . $table . PHP_EOL;
			mysql_copy($ll, $rl, $table);
		}
	}

	echo 'Comparing table differences...' . PHP_EOL;
	foreach($local['tables'] as $table) {

		$ctl = mysql_query("SHOW CREATE TABLE " . $table, $ll);
		$row = mysql_fetch_row($ctl);
	    $rowl = preg_replace("/\n/" , "" , $row['1']);
		
		$ctr = mysql_query("SHOW CREATE TABLE " . $table, $rl);
		$row = mysql_fetch_row($ctr);
	    $rowr = preg_replace("/\n/" , "" , $row['1']);
		
		if($rowl !== $rowr) {
			echo 'Update table ' . $table . PHP_EOL;
			mysql_copy($ll, $rl, $table);
		}
	
	}

	echo 'Comparing table data...' . PHP_EOL;
	foreach($local['tables'] as $table) {
		$ctl = mysql_query("SELECT * FROM " . $table, $ll);
		$local_rows = mysql_num_rows($ctl);
		
		$ctr = mysql_query("SELECT * FROM " . $table, $rl);
		$remote_rows = mysql_num_rows($ctr);
		
		if($local_rows != $remote_rows) {
			echo 'Updating table data...' . PHP_EOL;
			mysql_copy($ll, $rl, $table);
		}
		
	}
	
	echo 'Sync complete...' . PHP_EOL . PHP_EOL;
	echo 'Starting git deploy... ' . PHP_EOL;
?>