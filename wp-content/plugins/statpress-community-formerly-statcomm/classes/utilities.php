<?php
/*
 * Created on 15/04/2012
 *
 * Version 1.6.4: main purpose: common STATIC methods, without mysql unvoke (well, it is impossible)
 */

/*Useful to test in environments
Enable ONLY in office network. Is there a way to find out when it is a network and when is not?
Sure it does.

*/
/*
define( 'WP_PROXY_HOST', 'lfwproxy' );
define( 'WP_PROXY_PORT', '3128' );
define( 'WP_PROXY_USERNAME', 'xxxxx' );
 define( 'WP_PROXY_PASSWORD', 'xxxx' );
*/
 class utilities
 {
     const FILE_LOG_DEBUG   = false;
     const PLUGIN_VERSION   = "v1.7.41";
     const UAPARSER_URL     = 'http://statcomm.wpgetready.com/statservice/getfile';
     const UAPARSER_VERSION = 'http://statcomm.wpgetready.com/statservice/version';
     const UAPARSER_MD5     = 'http://statcomm.wpgetready.com/statservice/md5';
     const TIMEOUT =10;
     const statcommSummary   = 9999999; //10m -1 starting point
//Constants used in viewSystem
     const ERROR_REPORT_DAY ="statcomm_error_report_day";

     const ERROR_NONE = 0;
     const ERROR_FILE_NOT_FOUND = 1;
     const ERROR_FILE_OPEN = 2;
     const ERROR_INCORRECT_VERSION = 3;


    /**
     * @static
     * @return string
     */
      static function requestUrl()
      {
          //Request parameters
          $urlRequested = (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');
          //If they are empty...

          if ($urlRequested == "")
          {
              // give me the page and structure
              $urlRequested = (isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : '');
          }
          //Has it /? on start
          if (mb_substr ($urlRequested, 0, 2) == '/?')
          {
              $urlRequested = mb_substr ($urlRequested, 2); //suppress it
          }
          //if it is only /
          if ($urlRequested == '/')
          {
              $urlRequested = ''; //suppress it
          }
          return $urlRequested;
      }

     /**
      * Convert date to default WP format
      * @static
      * @param string $dt
      * @return mixed
      */
      static function conv2Date($dt = "00000000")
      {
          //TODO: FIX IT!
          return mysql2date(get_option('date_format'), mb_substr ($dt, 0, 4) . "-" . mb_substr ($dt, 4, 2) . "-" . mb_substr ($dt, 6, 2));
      }

    /**
     * @static
     * @param $out_url
     * @return string
     */
      static public function outUrlDecode($out_url)
      {
      	if(utilities::permalinksEnabled())
      	{
	          if ($out_url == '')
	          {
	              $out_url = __('Page', 'statcomm') . ": Home";
	          }
	          if (mb_substr ($out_url, 0, 4) == "cat=")
	          {
	              $out_url = __('Category', 'statcomm') . ": " . get_cat_name(mb_substr ($out_url, 4));
	          }
	          if (mb_substr ($out_url, 0, 2) == "m=")
	          {
	              $out_url = __('Calendar', 'statcomm') . ": " . mb_substr ($out_url, 6, 2) . "/" . mb_substr ($out_url, 2, 4);
	          }
	          if (mb_substr ($out_url, 0, 2) == "s=")
	          {
	              $out_url = __('Search', 'statcomm') . ": " . mb_substr ($out_url, 2);
	          }
	          if (mb_substr ($out_url, 0, 2) == "p=")
	          {
	              $post_id_7 = get_post(mb_substr ($out_url, 2), ARRAY_A);
	              $out_url = $post_id_7['post_title'];
	          }
	          if (mb_substr ($out_url, 0, 8) == "page_id=")
	          {
	              $post_id_7 = get_page(mb_substr ($out_url, 8), ARRAY_A);
	              $out_url = __('Page', 'statcomm') . ": " . $post_id_7['post_title'];
	          }
	        }
	        else
	        {
	        	if ($out_url == '')
	          {
	              $out_url = __('Page', 'statcomm') . ": Home";
	          }
	          else if (mb_substr ($out_url, 0, 9) == "category/")
	          {
	              $out_url = __('Category', 'statcomm') . ": " . get_cat_name(mb_substr ($out_url, 9));
	          }
	          else if (mb_substr ($out_url, 0, 8) == "//") // not working yet
	          {
	              //$out_url = __('Calendar', 'statcomm') . ": " . mb_substr ($out_url, 4, 0) . "/" . mb_substr ($out_url, 6, 7);
	          }
	          else if (mb_substr ($out_url, 0, 2) == "s=")
	          {
	              $out_url = __('Search', 'statcomm') . ": " . mb_substr ($out_url, 2);
	          }
	          else if (mb_substr ($out_url, 0, 2) == "p=") // not working yet
	          {
	              $post_id_7 = get_post(mb_substr ($out_url, 2), ARRAY_A);
	              $out_url = $post_id_7['post_title'];
	          }
	          else if (mb_substr ($out_url, 0, 8) == "page_id=") // not working yet
	          {
	              $post_id_7 = get_page(mb_substr ($out_url, 8), ARRAY_A);
	              $out_url = __('Page', 'statcomm') . ": " . $post_id_7['post_title'];
	          }
	        }
          return $out_url;
      }

     /**
      * Detect and return if permalinks are enabled.
      * 1.6.4 Improved, simplified and moved to utilities class
      * @static
      * @return bool
      */
	  static function permalinksEnabled()
	  {
	   return ( get_option('permalink_structure') != '' );
	  }


     /**
      * Return feed type
      * @static
      * @param $url
      * @return string
      */
	  static function feedType($url) {
	   if (stristr($url,get_bloginfo('comments_atom_url')) != FALSE) { return 'COMMENT ATOM'; }
	   elseif (stristr($url,get_bloginfo('comments_rss2_url')) != FALSE) { return 'COMMENT RSS'; }
	   elseif (stristr($url,get_bloginfo('rdf_url')) != FALSE) { return 'RDF'; }
	   elseif (stristr($url,get_bloginfo('atom_url')) != FALSE) { return 'ATOM'; }
	   elseif (stristr($url,get_bloginfo('rss_url')) != FALSE) { return 'RSS'; }
	   elseif (stristr($url,get_bloginfo('rss2_url')) != FALSE) { return 'RSS2'; }
	   elseif (stristr($url,'wp-feed.php') != FALSE) { return 'RSS2'; }
	   elseif (stristr($url,'/feed') != FALSE) { return 'RSS2'; }
	   return '';
	}

    /**
     * @return string
     */
      static function irigetblogurl()
      {
      	$prsurl = parse_url(get_bloginfo('url'));
      	return $prsurl['scheme'] . '://' . $prsurl['host'] . ((!utilities::permalinksEnabled()) ? $prsurl['path'] . '/?' : '');
      }

     /**
      * To improve: can't resolve ip6 adresses
      * improved: replacing ereg by preg_match
      * @static
      * @param $ip
      * @return string
      */
      static function iriDomain($ip)
      {
          $host = gethostbyaddr($ip);
          if (preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $host))
          {
              return "";
          }
          else
          {
              return mb_substr (strrchr($host, "."), 1);
          }
      }

     /**
      * File logging, used for analysis
      * The file will be stored in the def folder
      * Enable/Disable using FILE_LOG_DEBUG true/false
      * Examples: fl('value of var is',$var), fl('array value:', var_dump($myvariable)),fl('result,print_r($test)
      * @param $msg
      * @param null $variable
      */
     static function fl($msg,$variable=NULL)
     {
         if (utilities::FILE_LOG_DEBUG==FALSE) return;
         if (($fh=fopen(dirname(dirname(__FILE__)). '/def/debug.log','a')) === FALSE)
         {
             die("Error while opening log file");
         }
         fwrite($fh,date("H:i:s "));
         if ($variable==NULL)
         {
             fwrite($fh,  $msg . "\n");
         }
         else
         {
             fwrite($fh, utilities::var_log($variable, $msg));
         }
         fclose($fh);
     }

    /**
     * Output variable detail
     * Reference: http://php.net/manual/en/function.var-dump.php
     * @param $varInput
     * @param string $var_name
     * @param string $reference
     * @param string $method
     * @param bool $sub
     * @return string
     */
    static function var_log(&$varInput, $var_name='', $reference='', $method = '=', $sub = FALSE) {

        static $output ;
        static $depth ;

        if ( $sub == FALSE ) {
            $output = '' ;
            $depth = 0 ;
            $reference = $var_name ;
            $var = serialize( $varInput ) ;
            $var = unserialize( $var ) ;
        } else {
            ++$depth ;
            $var =& $varInput ;

        }

        // constants
        $nl = "\n" ;
        $block = 'a_big_recursion_protection_block';

        $c = $depth ;
        $indent = '' ;
        while( $c -- > 0 ) {
            $indent .= '|  ' ;
        }

        // if this has been parsed before
        if ( is_array($var) && isset($var[$block])) {

            $real =& $var[ $block ] ;
            $name =& $var[ 'name' ] ;
            $type = gettype( $real ) ;
            $output .= $indent.$var_name.' '.$method.'& '.($type=='array'?'Array':get_class($real)).' '.$name.$nl;

            // havent parsed this before
        } else {

            // insert recursion blocker
            $var = Array( $block => $var, 'name' => $reference );
            $theVar =& $var[ $block ] ;

            // print it out
            $type = gettype( $theVar ) ;
            switch( $type ) {

                case 'array' :
                    $output .= $indent . $var_name . ' '.$method.' Array ('.$nl;
                    $keys=array_keys($theVar);
                    foreach($keys as $name) {
                        $value=&$theVar[$name];
                        utilities::var_log($value, $name, $reference.'["'.$name.'"]', '=', TRUE);
                    }
                    $output .= $indent.')'.$nl;
                    break ;

                case 'object' :
                    $output .= $indent.$var_name.' = '.get_class($theVar).' {'.$nl;
                    foreach($theVar as $name=>$value) {
                        utilities::var_log($value, $name, $reference.'->'.$name, '->', TRUE);
                    }
                    $output .= $indent.'}'.$nl;
                    break ;

                case 'string' :
                    $output .= $indent . $var_name . ' '.$method.' "'.$theVar.'"'.$nl;
                    break ;

                default :
                    $output .= $indent . $var_name . ' '.$method.' ('.$type.') '.$theVar.$nl;
                    break ;
            }

            // $var=$var[$block];

        }

        -- $depth ;
        if( $sub == FALSE )
            return $output ;
        return "";
    }


     /**
      * ungzip a file, putting the results on destination
      * @static
      * @param $gzipFile
      * @param $destFile
      * @return bool
      */
     static function ungzipFile($gzipFile,$destFile)
     {
         //Open handle to writing
         $fp = fopen($destFile, "w");
         //get only the filename
         $dfFilename=pathinfo($destFile,PATHINFO_BASENAME);
         if ($fp == FALSE)
         {
             self::msg(__("gunzip error when opening $dfFilename for writing","statcomm"));
             utilities::fl("Error when try to writing in file ", $destFile);
             return FALSE;
         }
         //Open gzip for reading
         $zp = gzopen($gzipFile, "r");
         //get only the gzip filename
         $gzFilename = pathinfo($gzipFile,PATHINFO_BASENAME);
         if ($zp == FALSE)
         {
             self::msg(__("gunzip error when opening $gzFilename for reading","statcomm"));
             utilities::fl("Error when try reading file ", $gzipFile);
             return FALSE;
         }
         //or $this->error_exit(__('unzip error: gzopen opening GeoLiteCity.dat.gz file', 'visitor-maps'));
         while (!gzeof($zp)) {
             $buff1 = gzgets ($zp, 4096);
             fputs($fp, $buff1);
         }
         gzclose($zp);
         fclose($fp);
         //        if ($this->setting['chmod']) chmod($this->setting['geolite_path'] . 'GeoLiteCity.dat', 0644) or $this->error_exit(__('unzip error: Chmod 0644 GeoLiteCity.dat file failed', 'visitor-maps'));

         //delete gzip file
         unlink($gzipFile);
         return TRUE;
     }

     /**
      * Provide ongoing information directly to browser, flushing every message sent.
      * @static
      * @param $msg
      * @param bool $time
      * @param bool $flag
      */
     static function msg($msg,$time=TRUE, $flag=TRUE)
     {
         if ($time) {
            echo date('Y-m-j h:i:s A') . " - ";
         }
         echo $msg . "<br/>";
         if ($flag)
         {
             @ob_flush(); //ob_flush will warn if there is nothing to flush
             flush();
         }
     }


     static function uaParserEnabled()
     {
         $uasData=file_exists(plugin_dir_path(dirname(__FILE__)) . 'def/uasdata.ini');
         $uasIni=file_exists(plugin_dir_path(dirname(__FILE__)) . 'def/cache.ini');
         return $uasData && $uasIni ;
     }
     /**
      * Make an Icon flag from the recordset
      * 20120505: We need to figure it out a way to zoom a sprite image.
      * @static
      * @param $record
      * @return string
      */
     static  function  make_flag_icon($record, $path='')
     {
        if(empty($path))
        {
            $path=dirname(plugin_dir_url(__FILE__));
        }
         //<img src="blank.gif" class="flag flag-cz" alt="República Checa" />
        if (empty($record))
        {
            return "<img src='$path/images/blank.gif' class='flag flag-unknown' alt='unknown'  />";
        }
        $ccode=strtolower($record->country_code);
        return "<img src='" . dirname(plugin_dir_url(__FILE__)). "/images/blank.gif' class='flag flag-$ccode' alt='{$record->country_name}'  />";

     }

     static function make_os_icon($style,$path='')
     {
         if(empty($path))
         {
             $path=dirname(plugin_dir_url(__FILE__));
         }
        return "<img src='$path/images/blank.gif' class='os {$style}' alt='' />";
     }

     static function make_uas_icon($style,$path='')
     {
         if(empty($path))
         {
             $path=dirname(plugin_dir_url(__FILE__));
         }
         return "<img src='$path/images/blank.gif' class='uas {$style}' alt='' />";
     }

     /**
      * @static
      * @static
      * @param $url
      * @param $Timeout
      * @return array|void|WP_Error
      */
     //TODO:this class is prone to fail when the plugin uses $wp_version in some sites.Find it and fix it.
     static function remoteRequest($url, $Timeout)
     {
         global $wp_version;
         $response= wp_remote_request($url,array('timeout'=>$Timeout,
             'user-agent' =>  "WordPress/$wp_version;" . get_bloginfo('url') . ";" . self::PLUGIN_VERSION));
         return $response;
     }


    /**
     * Is the local UAS database up to date?
     * Compare with the server version and return true if it is, false if not.
     * Potential problem: if the server for some reason is down and it return error, the database should return
     * always true, if not it will continously retry. So we have a problem here: we cannot just say true or false,
     * We need to provide a contention plan for exceptional cases.
     * In case of server error or checking error, you'll never try to download the uas database.
     * first idea: return an array, with a boolean and a status.
     * @static
     * @param bool $showMsg
     * @return array
     */
     static function UASIsUpdated($showMsg=FALSE)
    {
        $uaParserCache= plugin_dir_path(dirname(__FILE__)) . 'def/cache.ini';

        utilities::fl("file {$uaParserCache} ok, checking if we need to update","");
        //Do we need to update? compare current version with server version.
        //If it is different, update. The server will always try to be up-to-date.
        $cacheIni = parse_ini_file($uaParserCache);
        $localversion = isset($cacheIni['localversion'])?$cacheIni['localversion']:0;
        $msgVersion= utilities::remoteRequest(self::UAPARSER_VERSION,self::TIMEOUT);
        $isError=utilities::isError($msgVersion,
            __("Client error when getting version(%d) - %s","statcomm"),
            __("Server error when getting version(%d) - %s","statcomm"),
            $showMsg
        );
        //isError already handle error situation so we don't need to do anything else.
        if (!$isError)
        {
            //Evaluate the current version against the last version
            $version=wp_remote_retrieve_body($msgVersion);
            if ($version != $localversion) //Version differs, download it.
            {
                if ($showMsg)
                {
                    utilities::msg(__("New version found in server, starting download...","statcomm"));
                }
                return array("status"=>FALSE,"msg"=>"");
            }
            {
                if ($showMsg)
                {
                    utilities::msg(__("You already have the last version. No need to update.","statcomm"));
                }
            }
        }
        else
        {
            //an error happened
            $error=$msgVersion->get_error_message();
            return array("status"=>TRUE,"msg"=> $error) ;
        }
        //Return a flag and a msg. if msg <>"" is because an error
        return array("status"=>TRUE,"msg"=>"");
    }

     /**
      * Find if there is an error in the request. Show error msg if needed.
      * @static
      * @param $request object from wp_remote_request
      * @param $clientError
      * @param $serverError
      * @param bool $showMsg
      * @return bool
      */
     static function isError($request,$clientError,$serverError,$showMsg=TRUE)  {
         //$clientError=__('Client error when retrieving file:(%d) - %s'  , 'statcomm')
         //$serverError=__('Server error when retrieving file:(%d) - %s'  , 'statcomm');
         $code   = wp_remote_retrieve_response_code($request);
         $errMsg = wp_remote_retrieve_response_message($request);
         if(is_wp_error($request) or ($code>399))
         {
             //Analyze error type

             utilities::fl("error code:" , $code);
             utilities::fl("error detail:",$request);
             utilities::fl("error msg", $errMsg);
             //utilities::fl("error msg", $errMsg->errors);

             if ($code>=500) //Request failed of a client error
             {
                 utilities::fl("Server error $code: while getting version: ", $errMsg);
                 if($showMsg)
                 {
                     utilities::msg( '<b>'.sprintf ($serverError,$code,$errMsg).'</b>');
                 }
                 return TRUE;
             }
             else //Request failed because a server error
             {
                 if ($errMsg=='')
                 {
                     //Try to get the description
                     $errMsg=$request->get_error_message();
                 }

                 utilities::fl("Client error $code while getting version: " , $errMsg);
                 if ($showMsg)
                 {
                     utilities::msg( '<b>'. sprintf( $clientError ,$code,$errMsg).'</b>');
                 }
                 return TRUE;
             }
         }
         return FALSE;
     }



    //1-Improved: we can check various files. Useful for test cases.
    //OK, I think we can't return true/false since we need to explain many possible error scenarios.
    //First attempt: return 0 if no error, !=0 if there is an error condition.
    //Is the always problem: I have to return an error code and a message.
    //The other way: implement throw error.

    //Improved: close file
    static function geoLocationEnabled($defaultFile="")
    {
         if (empty($defaultFile))
         {
             $defaultFile = plugin_dir_path(dirname(__FILE__)) . 'def/GeoLiteCity.dat';
         }
        //Check 1: File has to exist.
        if (!file_exists($defaultFile))
        {
            return utilities::ERROR_FILE_NOT_FOUND; //File doesn'exist
        }
        //Check 2: the file can be opened it without trouble. NULL if there was a problem.
        //Usually it should be very rare to see this error, since we are only opening
        $gi=utilities::geoLocationOpen($defaultFile);
        if (empty($gi))
        {
            return utilities::ERROR_FILE_OPEN; //Impossible to open file. Maybe corrupt
        }
        //Check Version. This condition is likely to happen on corrupted files.
        $versionMaxMind=GeoIP_Ctlr::geoipVersion($gi);
        if (empty($versionMaxMind))
        {
            utilities::geoLocationClose($gi);
            return utilities::ERROR_INCORRECT_VERSION; //Impossible to get a version file, possibly corrupt.
        }
        utilities::geoLocationClose($gi);
        return utilities::ERROR_NONE; //Everything is ok.
    }

    /**
     * 1.7.40: Added error control. If were unable to open the file because the file is corrupt
     * we return null. That condition should be handled by the calling method, disabling
     * Also no process should ever try to open a location directly, for that use geoLocationEnabled
     * Maxmind functionality.
     * @static
     * @return GeoIP|null
     */
     static function geoLocationOpen($defaultFile="")
     {
         if (empty($defaultFile))
         {
             $defaultFile = plugin_dir_path(dirname(__FILE__)) . 'def/GeoLiteCity.dat';
         }

         try
         {
             $gi=GeoIP_Ctlr::geoip_open($defaultFile, GeoIP_Ctlr::GEOIP_STANDARD);
             return $gi;
         }
         catch (Exception $e)
         {
             //TODO: Add error to new array msg
            utilities::fl("Generic error when opening GeoLocation Database:" , $e);
             return NULL;
         }
     }

    /**
     * 1.7.40
     * Correct handle geoLocationOpen should never call geoLocation Close
     * Anyway, we handle any possible error.
     * @static
     *
     * @param $gi
     */
    static function geoLocationClose($gi)
     {
         try
         {
             return GeoIP_Ctlr::geoip_close($gi);
         }
         catch (Exception $e)
         {
             return false;
         }
     }

     /**
      * Timer functions
      * @static
      * @return array|mixed
      */
     static function startTimer()
     {
         $time = microtime();
         $time = explode(' ', $time);
         $time = $time[1] + $time[0];
         return $time;
     }

     /**
      * Timer functions
      * @static
      * @param $start
      * @return float
      */
      static function stopTimer($start)
      {
          $time = microtime();
          $time = explode(' ', $time);
          $time = $time[1] + $time[0];
          $finish = $time;
          return round(($finish - $start), 4);
      }

     /**
      * Calculate statpress table and optionally display lenght and rows
      * Return how many rows to process.
      * @static
      * @param bool $display
      * @return mixed
      */
     static function statpressTableSize($display=FALSE)
     {
         $data_length="";
         $data_rows="";
         $res = mySql::get_results("SHOW TABLE STATUS LIKE '" . mySql::prefix() . "statpress' ");
         foreach ($res as $fstatus)
         {
             $data_length = $fstatus->Data_length;
             $data_rows = $fstatus->Rows;
         }
         if($display)
         {
             echo number_format(($data_length / 1024 / 1024), 2, ",", " ") . " MB ($data_rows records)";
         }
         return $data_rows;
     }

     /**
      * Attempt to extract search parameters if the referrer is a search engine.
      */

     /**
      * @static
      * @param $referrer
      * @param $pathSearchTerm
      * @return array|bool
      */
     static function searchTerm( $referrer, $pathSearchTerm ) {
         if ( empty( $referrer ) || ! file_exists( $pathSearchTerm ) )
             return FALSE;

         //Process searchterms and sections
         $data = parse_ini_file( $pathSearchTerm , TRUE );
         foreach ( $data as $url => $info ) {
             //if doesn't match try the next one
             if ( strpos( $referrer, $url ) === FALSE )
                 continue;

             //We get a  partial match,get only the query to process
             $qryStr = parse_url( $referrer, PHP_URL_QUERY );
             //empty? end the analysis
             if (empty($qryStr))
                 return FALSE;
             //decode properly and get an parameter array
             $qryParts = explode( '&', html_entity_decode( urldecode( $qryStr ) ) );
             $i = count( $qryParts );
             //Examine the array
             while ( $i-- ) {
                 //get every variable to examination $parts[0] variable name, $part[1] variable value
                 $parts = explode( '=', $qryParts[$i] );
                 //There are few cases where engine uses different search term parameters.
                 //As matter of fact only google , see searchterm.ini
                 if ( is_array( $info['query'] ) ) {
                     $queries = count( $info['query'] );
                     while ( $queries-- ) {
                         if ( $parts[0] == $info['query'][$queries] )
                             return array( $info['engine'], htmlentities( $parts[1], ENT_QUOTES ) );
                     }
                 }
                 else {
                     //if variable name matches , return array with engine name, and searched value
                     if ( $parts[0] == $info['query'] )
                         return array( $info['engine'], htmlentities( $parts[1], ENT_QUOTES ) );
                 }
             }

         }
         return FALSE;
     }

     /**
      * Converts seconds to format HH:MM:SS
      * @static
      * @param $secs
      * @return string
      */
        static function secsToTime($secs)
        {
            return sprintf('%02u:%02u:%02u', $secs/3600, $secs%3600/60, $secs%60);
        }

     static function triggerError($message, $errno=E_USER_ERROR) {


         if(isset($_GET['action']))
         {
             $_GET['action'] ='error_scrape';
             echo '<strong>' . $message . '</strong>';
             exit;
         } else {
            // trigger_error($message, $errno);
             trigger_error($message, $errno);
         }
    }

      /**
      *Adds ... to end text
      * @param $s
      * @param $c
      * @return string
      */
     static function makeEllipsis($s, $c)
     {
         $res = "";
         if (strlen($s) > $c)
         {
             $res = "...";
         }
         return mb_substr ($s, 0, $c) . $res;
     }

     static function statcommLastMonth()
     {
         $ta = getdate(current_time('timestamp'));

         $year = $ta['year'];
         $month = $ta['mon'];

         // go back 1 month;
         $month = $month - 1;

         if ($month === 0)
         {
             // if this month is Jan
             // go back a year
             $year  = $year - 1;
             $month = 12;
         }

         // return in format 'YYYYMM'
         return sprintf($year . '%02d', $month);
     }
 }



?>