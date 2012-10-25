<?php

/**
 * errorDay is almost a clone of Last Hits.
 * In Statcomm 1.7.30 we cannot take advantage of icons and tooltip in subplugins, a issue we'll fix in next versions.
 */
class errorDay extends statcommMustache
{
    private $current_day;
    public function  __construct()
    {
        $this->current_day=get_transient(utilities::ERROR_REPORT_DAY);
        if (empty($this->current_day))
        {
            $timestamp = current_time('timestamp');
            $this->current_day = gmdate("Ymd", $timestamp);
        }
    }

    public function templateName() {return "errorDay";}

    //Added to the form data input
    public function select_day()   { return __('Type a date see an error report (YYYYMMDD)','statcomm');}
    public function get_report()   { return __('Get Report','statcomm');}
    public function action()       { return wp_nonce_url(site_url('/wp-admin/admin.php'));}
    public function pageMenu()     { return 'statComm/errorreport';}
    public function default_date() { return $this->current_day; }

    //public function action() { return wp_nonce_url(site_url('/wp-admin/admin.php?page=statComm/errorreport'));}

    public function lastHits()    {
        $day=get_transient(utilities::ERROR_REPORT_DAY);
        $dayFormat=substr($day,0,4 ) . "-" .  substr($day,4,2) . "-" . substr($day,6,2);
        return sprintf(__('Last Errors Report on date %s', 'statcomm'),$dayFormat);
    }
    //public function lastHitsMsg() { return __("(hover IP to get spy information)","statcomm"); }
    public function date()        { return __('Date', 'statcomm'); }
    public function errorNumber()        { return '#'; }
    public function time()        { return __('Time', 'statcomm'); }
    public function ip()          { return __('IP', 'statcomm'); }
    public function flag()        { return __('Flag', 'statcomm'); }
    public function region()      { return __('Region', 'statcomm'); }
    public function city()        { return __('City', 'statcomm'); }
    public function domain()      { return __('Domain', 'statcomm'); }
    public function page()        { return __('Page', 'statcomm'); }
    public function os()          { return __('OS', 'statcomm'); }
    public function browser()     { return __('Browser<br/>Engine', 'statcomm'); }
    public function version()     { return __('Version<br/>Number', 'statcomm'); }
    public function feed()        { return __('Feed', 'statcomm'); }
    public function status()      { return __('Status', 'statcomm'); }

    public function rows()
    {
        //Modification since we are in a subplugin
        $path=WP_PLUGIN_DIR;
        $geoLocation=utilities::geolocationEnabled();
        $gi=null;
        $parser= new statcommParser();
        $errorReport   = "SELECT * FROM #table# WHERE (os<>'' OR feed<>'') and statuscode<>200 and date='#param#' order by id DESC limit #param#";
        $lastHits = mySql::get_results($errorReport,array($this->current_day,500)); //only last 200 errors from today.
        //The result is stored in $wpdb->num_rows
        $recordCount=mySql::num_rows();

        if ($geoLocation == utilities::ERROR_NONE)  {$gi = utilities::geoLocationOpen(); }

        $counter=1; //v1.6.60: bug correction
        $results=array();
        foreach ($lastHits as $hit) {
            $row=array();
            $ua=$parser->Parse($hit->agent); //Get info from agent
            if ($ua['typ']=='Robot') continue;
            //If the feed isn't empty then we can ignore OS and Browser Version
            $isFeed = (empty($hit->feed))?0:1; //PHP incapable of such simple evaluations?
            $isError=($hit->statuscode!="200" and !empty($hit->statuscode))?"scError":"";

            $row['errorNumber'] = $recordCount;
            $recordCount--;
            $row['isError'] = $isError;
            $row['date']    = utilities::conv2Date($hit->date);
            $row['time']    = $hit->time;
            $row['id']      = $hit->id;
            $row['ip']      = $hit->ip;

            if ($geoLocation == utilities::ERROR_NONE ){
                $record =GeoIpCity_Ctrl::GeoIP_info_by_addr($gi, $hit->ip);
                if (!empty($record)){
                    $row['country_name'] =$record->country_name;
                    $row['flag_icon']    = utilities::make_flag_icon($record,$path);
                    $row['region_name']  = $record->region_name;
                    $row['city']         = $record->city;
                }
                else{
                    $row['country_name'] = '?';
                    $row['flag_icon']    = utilities::make_flag_icon(null,$path);
                    $row['region_name']  = 'NO DATA';
                    $row['city']         = 'NO DATA';
                }
            }

            $row['nation'] = $hit->nation;
            $row['url'] = utilities::outUrlDecode($hit->urlrequested);
            //Correction url
            if (empty($hit->urlrequested))
            {
                $row['url_requested'] = site_url();
                if (is_multisite())
                {
                    $row['url_requested'] = network_site_url();
                }
            }
            else
            {
                $row['url_requested'] = $hit->urlrequested;
            }
            //$row['url_requested'] = (empty($hit->urlrequested))?:$hit->urlrequested;
            $row['url_ellipsis'] = utilities::makeEllipsis($row['url'], 40);

            if (!$isFeed){
                if ($hit->os != 'unknown'){
                    $row['os_icon'] = utilities::make_os_icon($ua['os_icon'],$path);
                }
            }

            $row['os_url'] = $ua['os_url'];
            if (!$isFeed){
                if ($hit->os!='unknown'){
                    $row['os'] = $hit->os;
                }
            }

            if ($ua['ua_family']!='unknown'){
                $row['ua_icon'] = utilities::make_uas_icon($ua['ua_icon']);
            }
            $row['agent'] = $hit->agent;

            if ($ua['ua_family']!='unknown'){
                $row['ua_info_url'] = $ua['ua_info_url'];
                $row['ua_family'] = $ua['ua_family'];
            }

            if ($ua['ua_version']!='unknown'){
                $row['ua_version'] = $ua['ua_version'];
            }

            $row['feed'] = $hit->feed;
            if  ($hit->statuscode !=200){
                $row['statuscode'] = $hit->statuscode;
            }
            $results[] = $row;
            $counter++;
            if ($counter>500)break;
        }

        if($geoLocation == utilities::ERROR_NONE){ utilities::geoLocationClose($gi); }
        return $results;
    }
}
