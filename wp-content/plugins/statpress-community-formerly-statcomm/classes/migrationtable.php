<?php
/**
 * Created by WpGetReady n @2012
 * Author: Fernando Zorrilla de San Martin
 * Date: 5/23/12
 * Time: 9:34 PM
 * Update: first version is outstandingly slow, consuming 20 seconds by record (!!!!) (23 days for 100.000 records, not very good)
 * Found it: unless strictly necessary iriDomain NEVER HAS TO BE USED, since it will take a LONG TIME to process (20 seconds or more!)
 * UPDATE: Date and time inferred from timestamp. Simplified
 */

/**
 * Handles the complexity of table migration
 * It also handle normalization process.
 */
class migrationTable
{
    Const ERROR_IP=0;
    Const ERROR_TS=1;
    Const ERROR_QRY=2;

    function __construct()
    {
        //nothing to do yet
    }

    /**
     * migrate statpress tables from outdated plugins to statcomm
     * First version is for starters. Migrate the table without consider statcomm data.
     * Assumed: capturing data is disabled, also is disabled for the other plugin
     * Even if the other plugin is activated, the migration would go on without problems.
     * TODO: can be faster?
     * TODO: migration counter when applying spider does not match with the results.
     * DONE TODO: in the example if date is 20120227, the system skips processing
     * UPDATE 20120530: Reduced mandatory fields to timestamp
     */
    public function startMigration()
    {
        //IN PROGRESS:install statpress in some site and collect data and later migrate.
        //TODO: Introduce simulation mode. To check if there is any problem.
        //1.7.xx: we decided not to migrate country field. Is 'deductible' and in later stages will be removed.
        //TODO:There is an important error: the method does not calculate correctly how much records will be processed.
        //TODO: Serious bug: NumRows doesn't work, it works only in the query statement(!!!!!)

        self::migrationHeader();
        $mySql=new mySql();

        utilities::msg( __("Starting migration process...","statcomm"));
        /**PRE-VALIDATION***/
        if (!$mySql->checkStatPressTable())
        {
            echo __("The Statpress table isn't there, aborting...","statcomm");
            self::migrationFooter();
            return ;
        }

        if (!self::validateFields($mySql))
        {
            utilities::msg(__("Missing fields process aborted","statcomm"));
            self::migrationFooter();
            return;
        }
        /**END PRE-VALIDATION***/

        @set_time_limit(0); //set time to infinite to process without timeout
        $parser= new statcommParser(); //Enable Agent parser

        $migOptions=settingsMigrationAPI::getOptions(); //check options to supress certain records
        $filterSpiders=empty($migOptions['chk_delspiders'])?false:true; //on if are running , empty if not
        $cuttingDate=empty($migOptions['cutting_date'])?'':$migOptions['cutting_date'];

        utilities::msg(__("Current settings:","statcomm"));
        utilities::msg( "<strong>". sprintf (__("Filter Spiders: %s" ,"statcomm"), $filterSpiders?"ON":"OFF" ) . "</strong>");
        if (!empty($cuttingDate))
        {
            utilities::msg("<strong>". sprintf (__("Cutting date: %s" ,"statcomm"), $cuttingDate ) . "</strong>" );
            $numRows=$mySql->getRecNumber("date>='$cuttingDate'");
        }
        else
        {
            $numRows=$mySql->getRecNumber();
        }


        //Get how many row to process
        $counter_base=1000; //define every how much records show progress
        $iteration= intval($numRows/$counter_base) +1; //Calculate how many  iterations do we need

        echo sprintf(__("Initializing migration: %d records, %d iterations","statcomm"),$numRows,$iteration) . "<br/>";
        utilities::msg(__("Clearing destination table...","statcomm"));
        mySql::query("Truncate #table#");
        utilities::msg(__("Done. Starting migration...","statcomm"));

        //Common variables initialization
        //$threat_score=0; //not used yet
        //$threat_type=0;
        $errorCounter= array();
        $errorCounter[self::ERROR_IP]= 0;
        $errorCounter[self::ERROR_QRY]= 0;
        $errorCounter[self::ERROR_TS]= 0;
        //$statuscode='';
        //$counter=0;
        //$counter_progress=0;
        $totalTime=0;
        $spiderCount=0;
        $totalProcessed=0;
        $totalCounter=0;

        //Optional fields are flagged  here.
        $isNation     = $mySql->columnExists("nation");
        $isLanguage   = $mySql->columnExists("language");
        $isStatusCode = $mySql->columnExists("statuscode");
        $isThreatScore=$mySql->columnExists("threat_score");
        $isThreatType = $mySql->columnExists("threat_type");

        $mainTimer=utilities::startTimer();
        for($i=0;$i<$iteration;$i++)
        {
            $secondTimer=utilities::startTimer();
            $current=($i+1) * $counter_base;
            $percent= intval( (100*$current)/$numRows);
            if ($percent>100) $percent=100;
            $qry= $mySql->getStatpressData($i,$counter_base,$cuttingDate);
            //if qry returns no rows, we already finished
            if (count($qry)==0) break;
            utilities::msg( sprintf(__("Processing %d...(%d%%) (from %d)","statcomm"),$current,$percent,$numRows));

            //Main loop
            foreach ($qry as $rec)
            {
                $os ='';
                $browser='';
                $feed='';
                $spider='';
                $searchengine='';
                //$search_phrase='';
                $totalCounter++;
                //optional fields to migrate
                $nation=$isNation?$rec->nation:'';
                $language=$isLanguage?$rec->language:'';
                $statuscode=$isStatusCode?$rec->statuscode:'';
                $threat_score=$isThreatScore?$rec->threat_score:0;
                $threat_type =$isThreatType?$rec->threat_type:0;

                $ua=$parser->Parse($rec->agent);
                if ($ua['typ'] ==statPressCommunity::ROBOT)
                {
                    $spider = $ua['ua_family'];
                    $spiderCount++;
                    if ($filterSpiders) continue; //skip this record if spider filter is activated
                }
                else
                {
                    //This step means you cannot import a table from other site (could be useful for testing)
                    //Maybe this should be improved/changed
                    $prsurl = parse_url(get_bloginfo('url'));
                    //return feedType if it is a feed, empty if not
                    $feed = utilities::feedType($prsurl['scheme'] . '://' . $prsurl['host'] . $_SERVER['REQUEST_URI']);
                    // Get OS and browser
                    $os = $ua['os_name'];
                    $browser = $ua['ua_family']; //or it can be ua_version but it has far more details
                    //get search_phrase using searchterms
                    $pathDefinitionsFile=plugin_dir_path(__FILE__) . 'def';
                    list($searchengine, $search_phrase) = utilities::searchTerm($rec->referrer,$pathDefinitionsFile . '/searchterm.ini');
                }

                //Every mandatory field is checked. If error, counter is incremented and saving is skipped
                if (!self::timestampValid($rec->timestamp))
                {
                    $errorCounter[self::ERROR_TS]++;
                    continue;
                }

                if (!self::ipValid($rec->ip))
                {
                    $errorCounter[self::ERROR_IP]++;
                    continue;
                }

                $insert= "Insert into #table#
                          (date,time,ip,urlrequested,agent,referrer,search,nation,os,browser,
                          searchengine,spider,feed,user,threat_score,threat_type,timestamp,language,statuscode)
                          values (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s,
                          %s, %s, %s, %s, %d, %d, %d, %s, %d) ";


                $data=array();
                $data[]=gmdate("Ymd", $rec->timestamp);
                $data[]=gmdate("H:i:s", $rec->timestamp);;
                $data[]=$rec->ip;
                $data[]=mysql_real_escape_string($rec->urlrequested);
                $data[]=mysql_real_escape_string(strip_tags($rec->agent));
                $data[]=mysql_real_escape_string($rec->referrer);
                $data[]=mysql_real_escape_string(strip_tags($rec->search));
                $data[]=$nation;
                $data[]=mysql_real_escape_string($os);
                $data[]=mysql_real_escape_string($browser);

                $data[]=$searchengine;
                $data[]=$spider;
                $data[]=$feed;
                $data[]=$rec->user;
                $data[]=$threat_score;
                $data[]=$threat_type;
                $data[]=$rec->timestamp;
                $data[]=$language;
                $data[]=$statuscode;

                $result=$mySql->qryPrepare($insert,$data);
                if (!empty($result))
                {
                    $errorCounter[self::ERROR_QRY]++;
                    utilities::msg(("DB Error:" . $result));
                }
                else
                {
                    $totalProcessed++;
                }
            }
            //Retrieve time in seconds
            $secondTimer=utilities::stopTimer($secondTimer);
            //Estimate records/bysecond
            $recBySecond=$counter_base/$secondTimer;
            $totalTime= $totalTime + $secondTimer;
            //$currentAverage= $totalTime/($i+1);
            //$completionEstimate= $currentAverage * ($iteration-$i);

            //Estimate average time
            echo "<strong>";
            echo sprintf(__("Average: %d rec/sec | Time elapsed: %s","statcomm"),
                        $recBySecond,utilities::secsToTime($totalTime)) . "<br/>";
            echo "</strong>";
        }
        echo "<p/>";
        echo __("Migration completed!!!","statcomm") . "<p/>";
        echo "<h3>" . __("Migration resume:","statcomm") . "</h3><br/>";
        $endTime=utilities::stopTimer($mainTimer);
        echo "<strong>" ;
        echo sprintf ( __("Final total time:%s , %d records processed, %d migrated ","statcomm"),utilities::secsToTime($endTime), $totalProcessed,$totalCounter);
        echo "<br/></strong>" ;
        if ($filterSpiders)
        {
            echo sprintf ( __("%d spider records, skipped (about %d %% of total) ","statcomm"),$spiderCount, ($spiderCount*100)/$totalCounter);
        }

        self::errorProcess($errorCounter);
        self::migrationFooter();
    }

    static function errorProcess($ec)
    {
        $isError=false;

        if ($ec[self::ERROR_IP]!=0)
        {
            echo "<div style='color:red'>" .
                sprintf(__("%d records skipped due incorrect ip", "statcomm"),$ec[self::ERROR_IP])
                . "</div>";
            $isError=true;
        }

        if ($ec[self::ERROR_TS]!=0)
        {
            echo "<div style='color:red'>" .
                sprintf(__("%d records skipped due incorrect timestamp", "statcomm"),$ec[self::ERROR_TS])
            . "</div>";
            $isError=true;
        }

        if ($ec[self::ERROR_QRY]!=0)
        {
            echo "<div style='color:red'>" .
                sprintf(__("%d records skipped due database errors", "statcomm"),$ec[self::ERROR_QRY])
            . "</div>";
            $isError=true;
        }

        if (!$isError)
        {
            echo "<div style='color:green'>" .  __("No errors found.","statcomm") . "</div>";
        }
        echo "<p/>";
    }

    static function migrationHeader()
    {
        echo "<div class='wrap'>";
        echo "<div id='icon-statcomm' class='icon32'><p/></div><h2>Migration In Progress...</h2>";
    }

    static function migrationFooter()
    {
//        echo '<br/> <a class="button-primary" href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm' ),'statcomm'). '" >'. __('Go to graphic view', 'statcomm'). '</a>';
//        echo '<a class="button-primary" href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm/options' ),'statcomm-options'). '" >'. __('Go to Main Options', 'statcomm'). '</a>';
        echo '<a class="button-primary" href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm/migration' ),'statcomm-migration'). '" >'. __('Go to Migration Options and start over', 'statcomm'). '</a>';
        echo '</div>';
    }

    static function dateValid($date)
    {
        if (strlen(trim($date))!=8)
        {
            return false;
        }
        if (!is_numeric($date))
        {
            return false;
        }

        return checkdate(substr($date,4,2),substr($date,6,2),substr($date,0,4 ));
    }

    static function timeValid($time)
    {
        //make a fake date to test
        $date="11/11/2011 " . $time;
        if (strtotime($date)===FALSE)
        {
            return false;
        }
        return true;
    }

    static function ipValid ($ip)
    {
        if (preg_match('/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/',$ip))
        {
           return true;
        }
        return false;
    }

    static function timestampValid($ts)
    {
     return   ( is_numeric($ts) && (int)$ts == $ts );
    }

    /**
     * Ennumerate all the fields needed to initiate the migration
     * If one is missing the process is aborted
     * 20120530: Updated. Date and time are not longer mandatory, inferred from timestamp
     * @static
     * @return mixed
     */
    static function validateFields()
        {
            //Examine the table
            //We would need at least the following fields:
            //date,time,ip,urlrequested,agent,referrer,search,timestamp
            //inferred fields (not needed): nation,os,browser,searchengine,spider,feed


            //Get an array of fields from the table
            $fields=mySql::getStatpressFields();
//            $isDate= self::isField($fields,"date")?true:false;
//            $isTime= self::isField($fields,"time")?true:false;
            $isIp  = self::isField($fields,"ip")?true:false;
            $isUrlRequested =self::isField($fields,"urlrequested")?true:false;
            $isAgent=self::isField($fields,"agent")?true:false;
            $isReferrer=self::isField($fields,"referrer")?true:false;
            $isSearch=self::isField($fields,"search")?true:false;
            $isTimestamp=self::isField($fields,"timestamp")?true:false;
            $isUser=self::isField($fields,"user")?true:false;
           //Nation is not mandatory but it is optional. Also language
           // $isNation=$isUser=self::isField($fields,"nation")?true:false;

            if(!$isIp)
            {
                utilities::msg(__("Field Ip is missing in Statpress table, aborting...","statcomm"));
                return false;
            }

            if(!$isUrlRequested)
            {
                utilities::msg(__("Field Urlrequested is missing in Statpress table, aborting...","statcomm"));
                return false;
            }

            if(!$isAgent)
            {
                utilities::msg(__("Field Agent is missing in Statpress table, aborting...","statcomm"));
                return false;
            }

            if(!$isReferrer)
            {
                utilities::msg(__("Field Referrer is missing in Statpress table, aborting...","statcomm"));
                return false;
            }

            if(!$isSearch)
            {
                utilities::msg(__("Field Search is missing in Statpress table, aborting...","statcomm"));
                return false;
            }

            if(!$isTimestamp)
            {
                utilities::msg(__("Field Timestamp is missing in Statpress table, aborting...","statcomm"));
                return false;
            }

            if(!$isUser)
            {
                utilities::msg(__("Field User is missing in Statpress table, aborting...","statcomm"));
                return false;
            }

            /*
            if(!$isNation)
            {
                utilities::msg(__("Field Nation is missing in Statpress table, aborting...","statcomm"));
                return false;
            }
            */
            return true;
        }


    /**
     * Return true if the fields is in the collection false othwerwise.
     * @static
     * @param $fields
     * @param $name
     * @return bool
     */
        static function isField($fields,$name)

        {
            foreach($fields as $f)
            {
                if ($f->Field == $name)
                {
                    return true;
                }
            }
            return false;
        }


}