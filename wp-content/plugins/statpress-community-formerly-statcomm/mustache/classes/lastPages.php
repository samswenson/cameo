<?php
class lastPages extends statcommMustache
{
    public function templateName(){return "lastPages";}

    public function title()     { return __('Last pages' , 'statcomm');}
    public function date()      { return __('Date'       , 'statcomm');}
    public function time()      { return __('Time'       , 'statcomm');}
    public function spider()    { return __('Spider'       , 'statcomm');}
    public function page()      { return __('Page'       , 'statcomm');}
    public function os()        { return __('OS'         , 'statcomm');}
    public function browser()   { return __('Browser'    , 'statcomm');}
    public function brversion() { return __('Br.Version' , 'statcomm');}

    /**
     * @return array
     */
    public function rows()
    {
        $parser= new statcommParser();
        $qry = mySql::get_results(mySql::QRY_lastPages,20);
        $results=array();
        foreach ($qry as $rk)
        {
            $ua= $parser->Parse($rk->agent);
            $row= array();
            $row['date'] = utilities::conv2Date($rk->date);
            $row['time'] =  $rk->time;
            $row['url']  =   utilities::outUrlDecode($rk->urlrequested);
            $row['url_shortened'] = utilities::makeEllipsis(utilities::outUrlDecode($rk->urlrequested), 60);
            if ($ua['os_name']!='unknown')
            {
                $row['os_icon'] = utilities::make_os_icon($ua['os_icon']);
                $row['os_url']  = $ua['os_url'];
                $row['os_name'] = $ua['os_name'];
            }
            if ($ua['ua_family']!='unknown')
            {
                $row['ua_icon'] = utilities::make_uas_icon($ua['ua_icon']);
                $row['ua_info_url'] = $ua['ua_info_url'];
                $row['ua_family'] = $ua['ua_family'];
            }
            $row['agent']      = $rk->agent;
            $row['ua_version'] = $ua['ua_version'];
            $results[]=$row;
        }
        return $results;
    }
}
