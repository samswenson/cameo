<?php

class BackupDatabase {

	var $errors;
	
    private $fp;

    function BackupDatabase() {
        __construct();
    }

	function __construct() {
		$this->errors = array();
	}


	/**
	 * Sets the open file point to be used when writing out the 
	 * table dumps. Not needed on the import step.
	 * @param string $args
	 * @return none
	 */
	function set_fp($fp) {
		if ($fp)
			$this->fp = $fp;		
	}
	
	/**
	 * Logs any error messages
	 * @param string $args
	 * @return none
	 */
	function error($error) {

		$this->errors[] = $error;
	}

	/**
	 * Write to the backup file
	 * @param string $query_line the line to write
	 * @return null
	 */
	function stow($query_line) {
		//echo "query_line=[". $query_line ."]<br />";
		if(false === @fwrite($this->fp, $query_line))
			$this->error(__('There was an error writing a line to the backup script:', SNAPSHOT_I18N_DOMAIN) . '  ' . $query_line . '  ' . $php_errormsg);
	}

	/**
	 * Better addslashes for SQL queries.
	 * Taken from phpMyAdmin.
	 */
	function sql_addslashes($a_string = '', $is_like = false) {
		if ($is_like) $a_string = str_replace('\\', '\\\\\\\\', $a_string);
		else $a_string = str_replace('\\', '\\\\', $a_string);
		return str_replace('\'', '\\\'', $a_string);
	} 

	/**
	 * Add backquotes to tables and db-names in
	 * SQL queries. Taken from phpMyAdmin.
	 */
	function backquote($a_name) {
		if (!empty($a_name) && $a_name != '*') {
			if (is_array($a_name)) {
				$result = array();
				reset($a_name);
				while(list($key, $val) = each($a_name)) 
					$result[$key] = '`' . $val . '`';
				return $result;
			} else {
				return '`' . $a_name . '`';
			}
		} else {
			return $a_name;
		}
	} 

	/**
	 * Front-end function to the backup_table() function. This
	 * function just provides the foreach looping over the 
	 * tables array provided. 
	 *
	 * @since 1.0.0
	 * @uses non
	 *
	 * @param array $tables an array of table names to backup. 
	 * @return none
	 */				

	function backup_tables($tables) {

		if (is_array($tables)) {
			foreach($tables as $table)
			{
				$this->backup_table($table);
			}		
		}
	}

	/**
	 * Taken partially from phpMyAdmin and partially from
	 * Alain Wolf, Zurich - Switzerland
	 * Website: http://restkultur.ch/personal/wolf/scripts/db_backup/
	 * Modified by Scott Merrill (http://www.skippy.net/) 
	 * to use the WordPress $wpdb object
	 * @param string $table
	 * @param string $segment
	 * @return void
	 */
	function backup_table($table, $segment = 'none') {

		global $wpdb;

		$ROWS_PER_SEGMENT = 100;


		$table_structure = $wpdb->get_results("DESCRIBE $table");
		//echo "table_structure<pre>"; print_r($table_structure); echo "</pre>";
		if (! $table_structure) {
			$this->error(__('Error getting table details', SNAPSHOT_I18N_DOMAIN) . ": $table");
			return false;
		}

		$this->stow("TRUNCATE TABLE " . $this->backquote($table) . ";\n");

		if(($segment == 'none') || ($segment >= 0)) {
			$defs = array();
			$ints = array();
			
			foreach ($table_structure as $struct) {
				if ( (0 === strpos($struct->Type, 'tinyint')) ||
					(0 === strpos(strtolower($struct->Type), 'smallint')) ||
					(0 === strpos(strtolower($struct->Type), 'mediumint')) ||
					(0 === strpos(strtolower($struct->Type), 'int')) ||
					(0 === strpos(strtolower($struct->Type), 'bigint')) ) {
						$defs[strtolower($struct->Field)] = ( null === $struct->Default ) ? 'NULL' : $struct->Default;
						$ints[strtolower($struct->Field)] = "1";
				}
			}


			// Batch by $row_inc

			if($segment == 'none') {
				$row_start = 0;
				$row_inc = $ROWS_PER_SEGMENT;
			} else {
				$row_start = $segment * $ROWS_PER_SEGMENT;
				$row_inc = $ROWS_PER_SEGMENT;
			}

			do {	
				$where = '';
				if ( !ini_get('safe_mode')) @set_time_limit(15*60);
				$table_data = $wpdb->get_results("SELECT * FROM $table $where LIMIT {$row_start}, {$row_inc}", ARRAY_A);

				$entries = 'INSERT INTO ' . $this->backquote($table) . ' VALUES (';	
				//    \x08\\x09, not required
				$search = array("\x00", "\x0a", "\x0d", "\x1a");
				$replace = array('\0', '\n', '\r', '\Z');
				if($table_data) {
					foreach ($table_data as $row) {
						$values = array();
						foreach ($row as $key => $value) {
							
							if (isset($ints[strtolower($key)])) {
								// make sure there are no blank spots in the insert syntax,
								// yet try to avoid quotation marks around integers
								$value = ( null === $value || '' === $value) ? $defs[strtolower($key)] : $value;
								$values[] = ( '' === $value ) ? "''" : $value;
							} else {
								$values[] = "'" . str_replace($search, $replace, $this->sql_addslashes($value)) . "'";
							}
						}
						$this->stow(" \n" . $entries . implode(', ', $values) . ');');
					}
					$row_start += $row_inc;
				}
			} while((count($table_data) > 0) and ($segment=='none'));
		}

		if(($segment == 'none') || ($segment < 0)) {
			// Create footer/closing comment in SQL-file
			$this->stow("\n");
			$this->stow("# --------------------------------------------------------\n");
			$this->stow("\n");
		}
	}
	
	
	function restore_databases($buffer)
	{
		global $wpdb;
		
		$sql = '';
		$start_pos = 0;
		$i = 0;
		$len= 0;
		$big_value = 2147483647;
		$delimiter_keyword = 'DELIMITER '; // include the space because it's mandatory
		$length_of_delimiter_keyword = strlen($delimiter_keyword);
		$sql_delimiter = ';';
		$finished = false;
		
		$len = strlen($buffer);

		// Grab some SQL queries out of it
		while ($i < $len) 
		{
			$found_delimiter = false;
		        
			// Find first interesting character
			$old_i = $i;
		    
		    // this is about 7 times faster that looking for each sequence i
			// one by one with strpos()
			if (preg_match('/(\'|"|#|-- |\/\*|`|(?i)(?<![A-Z0-9_])' . $delimiter_keyword . ')/', $buffer, $matches, PREG_OFFSET_CAPTURE, $i)) 
			{
				// in $matches, index 0 contains the match for the complete
				// expression but we don't use it

				$first_position = $matches[1][1];
			} 
			else 
			{
				$first_position = $big_value;
			}

	        $first_sql_delimiter = strpos($buffer, $sql_delimiter, $i);
	        if ($first_sql_delimiter === FALSE) 
			{
	            $first_sql_delimiter = $big_value;
	        } 
			else 
			{
	            $found_delimiter = true;
	        }
			
	        // set $i to the position of the first quote, comment.start or delimiter found
	        $i = min($first_position, $first_sql_delimiter);
			//echo "i=[". $i ."]<br />";

	        if ($i == $big_value) 
			{
	            // none of the above was found in the string

	            $i = $old_i;
	            if (!$finished) 
				{
	                break;
	            }
	            
				// at the end there might be some whitespace...
	            if (trim($buffer) == '') 
				{
	                $buffer = '';
	                $len = 0;
	                break;
	            }
	            
				// We hit end of query, go there!
	            $i = strlen($buffer) - 1;
	        }
	
			// Grab current character
	        $ch = $buffer[$i];

	        // Quotes
	        if (strpos('\'"`', $ch) !== FALSE) 
			{
	            $quote = $ch;
	            $endq = FALSE;
	            
				while (!$endq) 
				{
	                // Find next quote
	                $pos = strpos($buffer, $quote, $i + 1);
	            
	    			// No quote? Too short string
	                if ($pos === FALSE) 
					{
	                    // We hit end of string => unclosed quote, but we handle it as end of query
	                    if ($finished) 
						{
	                        $endq = TRUE;
	                        $i = $len - 1;
	                    }

	                    $found_delimiter = false;
	                    break;
	                }

	                // Was not the quote escaped?
	                $j = $pos - 1;

	                while ($buffer[$j] == '\\') $j--;

	                // Even count means it was not escaped
	                $endq = (((($pos - 1) - $j) % 2) == 0);

	                // Skip the string
	                $i = $pos;

	                if ($first_sql_delimiter < $pos) 
					{
	                    $found_delimiter = false;
	                }
	            }

	            if (!$endq) 
				{
	                break;
	            }

	            $i++;

	            // Aren't we at the end?
	            if ($finished && $i == $len) 
				{
	                $i--;
	            } 
				else 
				{
	                continue;
	            }
	        }
	
	        // Not enough data to decide
	        if ((($i == ($len - 1) && ($ch == '-' || $ch == '/'))
	          || ($i == ($len - 2) && (($ch == '-' && $buffer[$i + 1] == '-')
	            || ($ch == '/' && $buffer[$i + 1] == '*')))) && !$finished) {
	            break;
	        }


	        // Comments
	        if ($ch == '#'
	         || ($i < ($len - 1) && $ch == '-' && $buffer[$i + 1] == '-'
	          && (($i < ($len - 2) && $buffer[$i + 2] <= ' ')
	           || ($i == ($len - 1)  && $finished)))
	         || ($i < ($len - 1) && $ch == '/' && $buffer[$i + 1] == '*')
	                ) 
			{
	            // Copy current string to SQL
	            if ($start_pos != $i) 
				{
	                $sql .= substr($buffer, $start_pos, $i - $start_pos);
	            }

	            // Skip the rest
	            $start_of_comment = $i;

	            // do not use PHP_EOL here instead of "\n", because the export 
	            // file might have been produced on a different system
	            $i = strpos($buffer, $ch == '/' ? '*/' : "\n", $i);

	            // didn't we hit end of string?
	            if ($i === FALSE) 
				{
	                if ($finished) 
					{
	                    $i = $len - 1;
	                } 
					else 
					{
	                    break;
	                }
	            }

	            // Skip *
	            if ($ch == '/') 
				{
	                $i++;
	            }

	            // Skip last char
	            $i++;

	            // We need to send the comment part in case we are defining
	            // a procedure or function and comments in it are valuable
	            $sql .= substr($buffer, $start_of_comment, $i - $start_of_comment);

	            // Next query part will start here
	            $start_pos = $i;

	            // Aren't we at the end?
	            if ($i == $len) 
				{
	                $i--;
	            } 
				else 
				{
	                continue;
	            }
	        }

	        // Change delimiter, if redefined, and skip it (don't send to server!)
	        if (strtoupper(substr($buffer, $i, $length_of_delimiter_keyword)) == $delimiter_keyword
	         && ($i + $length_of_delimiter_keyword < $len)) 
			{
				// look for EOL on the character immediately after 'DELIMITER '
				// (see previous comment about PHP_EOL)
				$new_line_pos = strpos($buffer, "\n", $i + $length_of_delimiter_keyword);
				
				// it might happen that there is no EOL
				if (FALSE === $new_line_pos) 
				{
					$new_line_pos = $len;
				}
				
				$sql_delimiter = substr($buffer, $i + $length_of_delimiter_keyword, $new_line_pos - $i - $length_of_delimiter_keyword);
				$i = $new_line_pos + 1;

				// Next query part will start here
				$start_pos = $i;
				continue;
			}

			if ($found_delimiter || ($finished && ($i == $len - 1))) 
			{
	            $tmp_sql = $sql;

	            if ($start_pos < $len) 
				{
	                $length_to_grab = $i - $start_pos;

	                if (! $found_delimiter) 
					{
	                    $length_to_grab++;
	                }

	                $tmp_sql .= substr($buffer, $start_pos, $length_to_grab);
	                unset($length_to_grab);
	            }

	            // Do not try to execute empty SQL
	            if (! preg_match('/^([\s]*;)*$/', trim($tmp_sql))) 
				{
	                $sql = $tmp_sql;
					//echo "sql=[". $sql ."]<br />";
					$ret_db = $wpdb->query($sql);
	
	                $buffer = substr($buffer, $i + strlen($sql_delimiter));
	                // Reset parser:

	                $len = strlen($buffer);
	                $sql = '';
	                $i = 0;
	                $start_pos = 0;

	                // Any chance we will get a complete query?
	                //if ((strpos($buffer, ';') === FALSE) && !$GLOBALS['finished']) {
	                if ((strpos($buffer, $sql_delimiter) === FALSE) && !$finished) 
					{
	                    break;
	                }
	            } 
				else 
				{
	                $i++;
	                $start_pos = $i;
	            }
	        }
	        		        
		}
		
	}
}
