<?php
class lastAgents extends statcommMustache
{
    public function templateName() { return "lastAgents";}

    public function title() {return __('Last agents', 'statcomm'); }
    public function date() {return __('Date', 'statcomm'); }
    public function time() {return __('Time', 'statcomm'); }
    public function agent() {return __('Agent', 'statcomm'); }
    public function agent_type() {return __('Agent Type', 'statcomm'); }
    public function os() {return __('OS', 'statcomm'); }
    public function browser() {return __('Browser/Engine', 'statcomm'); }
    public function browser_version() {return __('Br.Version', 'statcomm'); }
    public function spider() {return __('Spider', 'statcomm'); }
    public function page()        { return __('Page', 'statcomm'); }

    public function rows()
    {
        $parser= new statcommParser();
        $qry = mySql::get_results(mySql::QRY_lastAgents,20);
        $results=array();
        foreach ($qry as $rk)
        {
            $row=array();
            $ua= $parser->Parse($rk->agent);
            $row['date'] = utilities::conv2Date($rk->date);
            $row['time'] = $rk->time;
            $row['agent'] = $rk->agent;
            $row['agent_ellipsis'] = utilities::makeEllipsis($rk->agent,60);
            $row['ua_type'] = $ua['typ'];

            if ($ua['os_name']!='unknown')
            {
                $row['os_icon'] =  utilities::make_os_icon($ua['os_icon']);
            }
            if ($ua['os_name']!='unknown')
            {
                $row['os_url'] = $ua['os_url'];
                $row['os_name'] = $ua['os_name'];
            }
            $row['ua_icon'] = utilities::make_uas_icon($ua['ua_icon']);
            $row['url'] = utilities::outUrlDecode($rk->urlrequested);
            $row['url_requested'] = $rk->urlrequested;
            $row['url_ellipsis'] = utilities::makeEllipsis($row['url'], 40);
            $row['ua_info_url'] = $ua['ua_info_url'];
            $row['ua_family'] = $ua['ua_family'];

            if ($ua['ua_version']!='unknown')
            {
                $row['ua_version'] = $ua['ua_version'];
            }
            $row['spider'] = $rk->spider;
            $results[] =$row;
        }
        return $results;
    }
}
