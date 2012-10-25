<?php
class lastReferrers extends statcommMustache
{
    public function templateName(){return "lastReferrers";}

    public function title()     { return __('Last referrers', 'statcomm'); }
    public function date()      { return __('Date', 'statcomm'); }
    public function time()      { return __('Time', 'statcomm'); }
    public function from()      { return __('From', 'statcomm'); }
    public function result()    { return __('Result', 'statcomm'); }

    public function rows()
    {
        $results=array();
        $qry = mySql::get_results(mySql::QRY_lastReferrers,array(get_option('home'), 20));
        foreach ($qry as $rk)
        {
            $row= array();
            $row['url'] = utilities::irigetblogurl() .
                ((strpos($rk->urlrequested, 'index.php') === FALSE) ? $rk->urlrequested : '');
            $row['url_ellipsis'] = utilities::makeEllipsis($row['url'], 70);
            $row['date'] = utilities::conv2Date($rk->date);
            $row['time'] = $rk->time;
            $row['referrer'] = $rk->referrer;
            $row['referrer_ellipsis'] = utilities::makeEllipsis($rk->referrer, 70);
            $results[]=$row;
        }
        return $results;
    }
}
