<?php
class lastHits extends statcommMustache
{
    public function templateName() {return "lastHits";}

    public function lastHits()    { return __('Last hits', 'statcomm');}
    public function lastHitsMsg() { return __("(hover IP to get spy information)","statcomm"); }
    public function date()        { return __('Date', 'statcomm'); }
    public function time()        { return __('Time', 'statcomm'); }
    public function ip()          { return __('IP', 'statcomm'); }
    public function flag()        { return __('Flag', 'statcomm'); }
    public function region()      { return __('Region', 'statcomm'); }
    public function city()        { return __('City', 'statcomm'); }
    public function domain()        { return __('Domain', 'statcomm'); }
    public function page()        { return __('Page', 'statcomm'); }
    public function os()        { return __('OS', 'statcomm'); }
    public function browser()        { return __('Browser<br/>Engine', 'statcomm'); }
    public function version()        { return __('Version<br/>Number', 'statcomm'); }
    public function feed()        { return __('Feed', 'statcomm'); }
    public function status()        { return __('Status', 'statcomm'); }

    public function rows()
    {
        $geoLocation=utilities::geolocationEnabled();
        $gi=NULL;
        $parser= new statcommParser();
        $lastHits = mySql::get_results(mySql::QRY_lastHits,200);

        if ($geoLocation == utilities::ERROR_NONE )  { $gi = utilities::geoLocationOpen(); }

        $counter=1; //v1.6.60: bug correction
        $results=array();
        foreach ($lastHits as $hit) {
            $row=array();
            $ua=$parser->Parse($hit->agent); //Get info from agent
            if ($ua['typ']=='Robot') continue;
            //If the feed isn't empty then we can ignore OS and Browser Version
            $isFeed = (empty($hit->feed))?0:1; //PHP incapable of such simple evaluations?
            $isError=($hit->statuscode!="200" and !empty($hit->statuscode))?"scError":"";

            $row['isError'] = $isError;
            $row['date']    = utilities::conv2Date($hit->date);
            $row['time']    = $hit->time;
            $row['id']      = $hit->id;
            $row['ip']      = $hit->ip;

            if ($geoLocation == utilities::ERROR_NONE){
                $record =GeoIpCity_Ctrl::GeoIP_info_by_addr($gi, $hit->ip);
                if (!empty($record)){
                    $row['country_name'] =$record->country_name;
                    $row['flag_icon']    = utilities::make_flag_icon($record);
                    $row['region_name']  = $record->region_name;
                    $row['city']         = $record->city;
                }
                else{
                    $row['country_name'] = '?';
                    $row['flag_icon']    = utilities::make_flag_icon(null);
                    $row['region_name']  = 'NO DATA';
                    $row['city']         = 'NO DATA';
                }
            }

            $row['nation'] = $hit->nation;
            $row['url'] = utilities::outUrlDecode($hit->urlrequested);
            $row['url_requested'] = $hit->urlrequested;
            $row['url_ellipsis'] = utilities::makeEllipsis($row['url'], 40);

            if (!$isFeed){
                if ($hit->os != 'unknown'){
                    $row['os_icon'] = utilities::make_os_icon($ua['os_icon']);
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
            if ($counter>20)break;
        }

        if($geoLocation == utilities::ERROR_NONE){ utilities::geoLocationClose($gi); }

        return $results;
    }

}
