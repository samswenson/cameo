<?php
/**
 * The following interface resumes all the method that come from Wordpress.
 */
interface dbLayer
{
    static public function get_row($query, $paramsArray=null,$output = OBJECT, $y=0);
    static public function get_row_cached ($param, $keyType);
    static public function set_qry($query, $paramsArray=null);
    static public function get_var($query, $paramsArray=null,$x=0,$y=0);
    static public function get_results($query, $paramsArray=null, $output = OBJECT);
    static public function query($query);
    static public function prefix();
    static public function prepare($qry,$data);
    static public function print_error();
}

/**
 * 1.7.30: rewritten and very simplfied
 * 20120630: Update query and cached counters
 * 20120714: Fixed a serious calculation about site errors. The errors should stay in normal numbers now.
 */
class mySql implements dbLayer
{
    //Retrieve number of visitors, all time
    Const QRY_Visitors      = "SELECT count(DISTINCT ip)  AS visitors FROM #table# WHERE feed='' AND spider=''";
    //Retrieve number of visitors, last month
    Const QRY_VisitorsMonth = "SELECT count(DISTINCT ip) AS visitors FROM #table# WHERE feed='' AND spider='' AND date LIKE '#param#%'";
    //Retrieve number of visitors for a specific date
    Const QRY_VisitorsDay   = "SELECT count(DISTINCT ip)  AS visitors FROM #table# WHERE feed='' AND spider='' AND date = '#param#'";
    //Retrieve general pageview number
    Const QRY_PageViews     = "SELECT count(date) as pageview FROM #table# WHERE feed='' AND spider=''";
    //Retrieve pageview for month
    Const QRY_PageViewsMonth= "SELECT count(date) as pageview FROM #table# WHERE feed='' AND spider='' AND date LIKE '#param#%'";
    //Retrieve pageview for date
    Const QRY_PageViewsDay  =  "SELECT count(date) as pageview FROM #table# WHERE feed='' AND spider='' AND date = '#param#'";
    //Retrieve overall spiders count
    Const QRY_Spiders       = "SELECT count(date) as spiders FROM #table# WHERE feed='' AND spider<>''";
    //Retrieve monthly spiders.
    Const QRY_SpidersMonth  = "SELECT count(date) as spiders FROM #table# WHERE feed='' AND spider<>'' AND date LIKE '#param#%'";
    //Retrieve daily spiders
    Const QRY_SpidersDay    = "SELECT count(date) as spiders FROM #table# WHERE feed='' AND spider<>'' AND date = '#param#'";
    //Retrieve overall feeds
    Const QRY_Feeds         = "SELECT count(date) as feeds FROM #table# WHERE feed<>'' AND spider=''";
    //Retrieve monthly feeds
    Const QRY_FeedsMonth    = "SELECT count(date) as feeds FROM #table# WHERE feed<>'' AND spider='' AND date LIKE '#param#%'";
    //Retrieve daily feeds
    Const QRY_FeedsDaily    = "SELECT count(date) as feeds FROM #table# WHERE feed<>'' AND spider='' AND date = '#param#'";
    //Retrieve max. Pageview from a date to ahead
    Const QRY_MaxPageViews = "SELECT count(date) as pageview, date FROM #table# GROUP BY date HAVING date >= '#param#' ORDER BY pageview DESC LIMIT 1";
    //Retrieve amount users in particular day
    Const QRY_UsersDay     = "SELECT count(DISTINCT ip) AS total FROM #table# WHERE feed='' AND spider='' AND date = '#param#'";
    //Retrieve total page views in a day
    Const QRY_TotPageViews = "SELECT count(date) as total FROM #table# WHERE feed='' AND spider='' AND date = '#param#'";
    //Retrieve total spiders in a day
    Const QRY_TotSpiders   = "SELECT count(ip) AS total FROM #table# WHERE feed='' AND spider<>'' AND date = '#param#'";
    //Retrieve total feeds in a day
    Const QRY_TotFeeds = "SELECT count(ip) AS total FROM #table# WHERE feed<>'' AND spider='' AND date = '#param#'";

    //Queries from Main Page
    Const QRY_lastHits      = "SELECT * FROM #table# WHERE (os<>'' OR feed<>'') order by id DESC limit #param#";
    Const QRY_lastSearch    = "SELECT agent,date,time,referrer,urlrequested,search,searchengine FROM #table# WHERE search<>'' ORDER BY id DESC limit #param#";
    Const QRY_lastReferrers = "SELECT date,time,referrer,urlrequested FROM #table# WHERE ((referrer NOT LIKE '#param#') AND (referrer <>'') AND (searchengine='')) ORDER BY id DESC limit #param#";
    Const QRY_lastAgents    = "SELECT date,time,agent,os,browser,spider,urlrequested FROM #table# WHERE (agent <>'') ORDER BY id DESC limit #param#";
    Const QRY_lastPages     = "SELECT date,time,urlrequested,os,browser,spider,agent FROM #table# WHERE (spider='' AND feed='') ORDER BY id DESC limit #param#";
    Const QRY_lastSpiders   = "SELECT date,time,agent,spider,urlrequested,agent FROM #table# WHERE (spider<>'') ORDER BY id DESC limit #param#";

    //Queries from widget
    Const QRY_TopPosts       = "SELECT urlrequested,count(*) as totale FROM #table# WHERE spider='' AND feed='' GROUP BY urlrequested ORDER BY totale DESC LIMIT #param#";

    //Queries for parsing variables
    Const QRY_parseVisits      = "SELECT count(DISTINCT(ip)) as pageview FROM #table# WHERE date = '#param#' and spider='' and feed=''";
    Const QRY_parseTotalVisits = "SELECT count(DISTINCT(ip)) as pageview FROM #table# WHERE spider='' and feed=''";
    Const QRY_parseThisTotalVisits="SELECT count(DISTINCT(ip)) as pageview FROM #table# WHERE spider='' and feed='' AND urlrequested='#param#'";
    Const QRY_parseSince       = "SELECT date FROM #table# ORDER BY date LIMIT 1";
    Const QRY_parseVisitOnline = "SELECT count(DISTINCT(ip)) as visitors FROM #table# WHERE spider='' and feed='' AND timestamp BETWEEN #param# AND #param#";
    Const QRY_parseUsersOnline= "SELECT count(DISTINCT(ip)) as users FROM #table# WHERE spider='' and feed='' AND user<>'' AND timestamp BETWEEN #param# AND #param#";
    Const QRY_parseTopPosts = "SELECT urlrequested,count(*) as totale FROM #table# WHERE spider='' AND feed='' AND urlrequested LIKE '%p=%' GROUP BY urlrequested
	ORDER BY totale DESC LIMIT 1;";
    Const QRY_parseTopBrowser  = "SELECT browser,count(*) as totale FROM #table# WHERE spider='' AND feed='' GROUP BY browser ORDER BY totale DESC LIMIT 1";
    Const QRY_parseTopOs       = "SELECT os,count(*) as totale FROM #table# WHERE spider='' AND feed='' GROUP BY os ORDER BY totale DESC LIMIT 1";
    Const QRY_parsePagesToday  = "SELECT count(ip) as pageview FROM #table# WHERE date = '#param#' and spider='' and feed=''";
    Const QRY_parseThisTotalPages="SELECT count(ip) as pageview FROM #table# WHERE spider='' and feed=''";
    Const QRY_parseLatestHits  = "SELECT search FROM #table# WHERE search <> '' ORDER BY id DESC LIMIT 10";
    Const QRY_parsePagesYesterday ="SELECT count(DISTINCT ip) AS visitsyesterday FROM #table# WHERE feed='' AND spider='' AND date = '#param#'";

    //Auxiliary queries while appending data
    Const QRY_AutoDel = "DELETE FROM #table# WHERE date < '#param#'";
    Const QRY_AutoDelSpider = "DELETE FROM #table# WHERE date < '#param#' AND spider <> ''";

    //Queries for spy feature
    Const QRY_Spy1 = "SELECT id FROM #table# WHERE (spider='' AND feed='') GROUP BY ip";
    Const QRY_Spy2 = "SELECT ip,nation,os,browser,agent FROM #table# WHERE (spider='' AND feed='') GROUP BY ip ORDER BY id DESC LIMIT #param#, #param#";

    //Queries size table
    Const QRY_tableSize="SHOW TABLE STATUS LIKE '#table#'";

    Const QRY_Spy ="Select ip,agent,feed from #table# where id=#param#";

    Const QRY_Summary="SELECT date from #table# group by date order by date";
    Const QRY_ErrorsDay    = "SELECT count(date) as errors FROM #table# WHERE (os<>'' Or feed<>'') and statuscode<>200 and not isnull(statuscode) and date = '#param#'";
    Const QRY_ErrorsMonth  = "SELECT count(date) as errors FROM #table# WHERE statuscode<>200 and not isnull(statuscode) and date LIKE '#param#%'";

    //Improved Global Queries for lazy cache
    Const QRY_Visitors_Global      = "SELECT count(DISTINCT ip)  AS visitors FROM #table# WHERE feed='' AND spider='' and date <= '#param#'";
    Const QRY_PageViews_Global     = "SELECT count(date) as pageview FROM #table# WHERE feed='' AND spider='' and date <= '#param#'";
    Const QRY_Spiders_Global       = "SELECT count(date) as spiders FROM #table# WHERE feed='' AND spider<>'' and date <= '#param#'";
    Const QRY_Feeds_Global         = "SELECT count(date) as feeds FROM #table# WHERE feed<>'' AND spider='' and date <= '#param#'";
    Const Qry_Errors_Global        = "SELECT count(date) as errors FROM #table# WHERE statuscode<>200 and not isnull(statuscode) and date <= '#param#'";

    private static $_qryCounter=0;
    private static $_qryCached=0;

    static public function get_row($query, $paramsArray=null,  $output = OBJECT, $y=0)
    {
        //Note:parameter y not used yet
        global $wpdb;
        $qry=self::getTable($query);
        $qry=self::qryReplace($qry,$paramsArray);
        self::$_qryCounter++;
        return $wpdb->get_row($qry,$output,$y);
    }

    static public function get_row_cached ($param,$keyType)
    {
        $key=new keyCache($param,$keyType);
        if ($key->isCached())
        {
            self::$_qryCached++;
            utilities::fl("COUNTER CACHED:$param,$keyType" );
        }
        else
        {
            //miss hit, but for the last time
            utilities::fl("COUNTER UNCACHED:$param,$keyType" );
            $key->makeKeyCached();
        }
        return $key;
    }

    static  public function set_qry($query,  $paramsArray=null)
    {
        global $wpdb;
        $qry=self::getTable($query);
        $qry=self::qryReplace($qry,$paramsArray);
        self::$_qryCounter++;
        return $wpdb->query($qry);
    }

    static public function get_var($query, $paramsArray=null, $x=0,$y=0)
    {
        global $wpdb;
        $qry=self::getTable($query);
        $qry=self::qryReplace($qry,$paramsArray);
        self::$_qryCounter++;
        return $wpdb->get_var($qry,$x,$y);
    }

    static public function get_results($query, $paramsArray=null, $output = OBJECT)
    {
        global $wpdb;
        $qry=self::getTable($query);
        $qry=self::qryReplace($qry,$paramsArray);
        self::$_qryCounter++;
        return  $wpdb->get_results($qry, $output);
    }

    static public function num_rows()
    {
        global $wpdb;
        return $wpdb->num_rows;
    }

    static function prefix()
    {
        global $wpdb;
        return $wpdb->prefix;
    }

    static function prepare($qry,$data)
    {
        global $wpdb;
        return $wpdb->prepare($qry,$data);
    }

    static function query($query)
    {
        global $wpdb;
        $qry=self::getTable($query);
        self::$_qryCounter++;
        return $wpdb->query($qry);
    }

    static function print_error()
    {
        global $wpdb;
        return $wpdb->print_error();
    }

    /**
     * Replace #table# with current statcomm table.
     * @param $qry
     * @return mixed
     */
    static function getTable($qry)
    {
        $table_name= self::prefix() . "statcomm";
        return str_replace('#table#',$table_name,$qry);
    }

    /**
     * Replaces every parameter with its value
     * @param $qry
     * @param $parameters
     * @return mixed
     */
    private function qryReplace($qry,$parameters)
    {
        //If there are no parameters return the query
        if (empty($parameters)) return $qry;
        if (!is_array($parameters))
        {
            //is one data, convert it to array
            $parameters = array($parameters);
        }
        //The param to be replaced has the form #param#. It is important to keep the order!
        $param ='/\#param\#/';
        //Count how many matches do we have.
        $result=preg_match_all( $param,$qry, $results ); //$results has no use, but it is not optional until PHP 5.4.0
        //If match number is <> array lenght, there is an error.
        if (count($parameters)!=$result)
        {
            utilities::fl("parameters:", $parameters);
            utilities::fl("qry:", $qry);
            trigger_error("Number of parameters mismatch in <br/>$qry", E_USER_ERROR);
        }

        foreach($parameters as $p)
        {
            $qry=preg_replace($param,$p,$qry,1);
        }
        return $qry;
    }

    /**
     * Check the database. Only called on activation
     * v1.6.90: updated to contain statuscode
     * v1.7.00: country deleted, it had not sense. All varchar fields changed to TINYTEXT for standarization.
     * @return mixed
     * v1.7.10: detected problem with index creation and DbDelta function. It is not that smart though...
     * Check index if exist and if it does, delete them. After that execute dbDelta
     * v1.7.20: check also if user can create table or if the tables was created and display message if it can't do it.
     * In case of error, abort and display error message.
     * To improve: check if possible to drop indexes.
     * @param $baseName
     */
    static public function checkTable($baseName)
    {
        utilities::fl("checking Table...");
        $statTable=self::getTable("SHOW TABLES LIKE '#table#'");
        $tableName=self::getTable("#table#");
        $tableExists=( self::get_var($statTable) == $tableName)?true:false;

        if ($tableExists)
        {
            //If the table will be updated, proceed to drop index first, since dbDelta is not very good dealing with
            //indexes changes.
            utilities::fl("table statcomm exists, proceed to check indexes");
            self::dropIndex("index_ip");
            self::dropIndex("index_date");
            self::dropIndex("index_feed");
        }

        $sql = "CREATE TABLE " . self::prefix() . "statcomm (
  id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
  date varchar(8) NOT NULL,
  time varchar(8) NOT NULL,
  ip  varchar(15) NOT NULL,
  urlrequested TEXT,
  agent TEXT,
  referrer TEXT,
  search TEXT,
  nation TINYTEXT,
  os TINYTEXT,
  browser TINYTEXT,
  searchengine TINYTEXT,
  spider varchar(30) NOT NULL DEFAULT '',
  feed varchar(7) NOT NULL DEFAULT '',
  user TINYTEXT,
  timestamp TINYTEXT,
  language TINYTEXT DEFAULT NULL,
  threat_score SMALLINT DEFAULT 0,
  threat_type SMALLINT DEFAULT 0,
  statuscode SMALLINT DEFAULT 0,
  UNIQUE KEY id (id),
  KEY index_ip (ip) USING BTREE,
  KEY index_date (date) USING BTREE,
  KEY index_feed (feed) USING BTREE
  );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        //Try to catch any problem in task involving dbDelta
        //201200621: In some hosts table creation is not allowed. In other cases, dbDelta could fail creating
        //indexes or task related. This trick can capture dbDelta errors to advise user.
        //This settings is for extremely long tables (>150k). It won't work in sites with time_limit disabled.
        @set_time_limit(0);
        ob_start();
        dbDelta($sql);
        $errors = ob_get_contents();
        ob_end_clean();
        $error_creation = preg_match( '/^(CREATE|INSERT|UPDATE)/m', $errors );
        $error_others = preg_match( '/^ALTER TABLE.*ADD (?!KEY|PRIMARY KEY|UNIQUE KEY)/m', $errors );
        $error_dbdelta = $error_creation || $error_others;
        if ( $errors && $error_dbdelta ) {
            $errors = "<h3>". __("Error when creating table. StatComm is unable to continue:", "statcomm" ) .
                "</h3><br/>" . $errors;
            trigger_error( $errors, E_USER_ERROR );
        }
        //20120621: In some host, the user is unable to create tables. After creating the table, check if the table exists.
        //We checked dbDelta can give NO error and even so, the table never was created.

        $tableExists=(self::get_var($statTable) == $tableName)?true:false;
        if (!$tableExists)
        {
            $errors = sprintf(__("StatComm was unable to create the table <b>%s</b> for saving data.<br/>" .
                "Check if current database user has enough privileges to do so.<br/>" .
                "Click back in your browser to go the options settings.","statcomm") , $tableName ) ;
            //wp_die($errors);
            deactivate_plugins( $baseName);
            //For the moment , i didn't find a proper way to display and continue.
            wp_die($errors);
        }
    }

    /**
     * 1.7.20: added database specification to the query.
     * @param string $indexName
     */
    private function dropIndex($indexName)
    {
        $dbname= DB_NAME;

        $isIndex="SELECT COUNT(1) FROM information_schema.statistics WHERE
                  table_schema='$dbname' AND
                  table_name ='#table#'   AND index_name = '$indexName'";

        if (self::get_var($isIndex))
        {
            utilities::fl ("$indexName found, deleting...");
            utilities::fl("indexname:$indexName");
            utilities::fl("isIndex:$isIndex");
            $qry="drop index $indexName on #table#";
            self::get_var($qry);
        }
    }

    /**
     * Check statpress table existance
     * @return bool
     */
    static public function checkStatPressTable()
    {
        $table= self::prefix() . "statpress";
        if (self::get_var("SHOW TABLES LIKE '$table'") == $table)
        {
            return true;
        }
        return false;
    }

    /**
     * Get the list of fields from Statpress table
     * @return mixed
     */
    static public function getStatpressFields()
    {
        $table= self::prefix() . "statpress";
        return  self::get_results("Describe $table");
    }

    static public function getStatcommFields($output = OBJECT)
    {
        $table= self::prefix() . "statcomm";
        return  self::get_results("Describe $table",null,$output );
    }

    static public function getStatpressData($iteration,$base,$cuttingDate)
    {
        $start=$iteration*$base;
        $table= self::prefix() . "statpress";
        $qry="Select * from $table Limit $start,$base";
        if(!empty($cuttingDate))
        {
            $qry="Select * from $table where date>='$cuttingDate' Limit $start,$base";
        }
        return self::get_results($qry);
    }

    /**
     * @param string $filter
     * @return mixed
     */
    static public function getRecNumber($filter='')
    {
        $table= self::prefix() . "statpress";
        $qry="Select count(*) as cnt from $table";
        if (!empty($filter))
        {
            $qry .=  " where $filter";
        }
        $myvar=self::get_results($qry);
        return $myvar[0]->cnt;
    }

    /**
     * Query to prepare with data to be used. Usually for Inserts
     * @param $qry: Query with #table# being the table name to be replaced
     * @param $data: array with data to use
     * @return string: empty if everything was ok, error details otherwise
     */
    static public function qryPrepare($qry,$data)
    {
        $qry=self::getTable($qry); //Replace #table# for table name
        utilities::fl("data:" , $data);
        $result = self::query(self::prepare($qry,$data));
        if ($result===FALSE)
        {
            utilities::msg("it GIVES an error...");
            return self::print_error();
        }
        return '';
    }

    /**
     * Checks if a column exist in origin. Defaults to table to migrate from
     * @param $col
     * @param string $t
     * @return bool
     */
    static public function columnExists($col, $t='statpress')
    {
        $table= self::prefix() . $t;
        $qry = "show columns from $table like '$col'";
        $result= self::set_qry($qry);
        return ($result==0)?false:true;
    }

    /**
     * Reset Summary Table
     */
    static function resetSummaryTable()
    {
        $keys=get_post_custom_keys(utilities::statcommSummary);
        if (empty($keys))
        {
            utilities::fl("Keys empty,nothing to do");
            return;
        }
        // if (empty($keys)) return;
        utilities::fl("keys" , $keys);

        foreach ($keys as $k)
        {
            delete_post_meta(utilities::statcommSummary,$k);
        }
        utilities::fl("Reseted statcomm summary");

    }

    /**
     * Return how many cached queries are currently made
     * @return int
     */
    static public function qryCounterCached()
    {
        return self::$_qryCached;
    }

    /**
     * Return how many queries were executed since last reset
     * @return int
     */
    static public function qryCounter()
    {
        return self::$_qryCounter;
    }

    /**
     * Resets query and cached counters.
     */
    static  public function resetQryCounter()
    {
        self::$_qryCounter=0;
        self::$_qryCached=0;
    }
}