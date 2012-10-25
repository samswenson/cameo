<?php
class lastSearchTerms extends statcommMustache
{
    public function templateName() {return "lastSearchTerms";}
    public function lastsearchterms() {return __('Last search terms', 'statcomm'); }
    public function date()  { return __('Date', 'statcomm'); }
    public function time()  { return __('Time', 'statcomm'); }
    public function terms() { return __('Terms', 'statcomm'); }
    public function engine(){ return __('Engine', 'statcomm'); }
    public function result(){ return __('Result', 'statcomm'); }

    public function rows()
    {
        $qry = mySql::get_results(mySql::QRY_lastSearch,20);
        $results=array();
        foreach ($qry as $rk)
        {
            $row=array();
            $row['url_requested'] = utilities::irigetblogurl() . ((strpos($rk->urlrequested, 'index.php') === FALSE) ? $rk->urlrequested : '');
            $row['url_req_ellipsis'] = utilities::makeEllipsis($row['url_requested'],80);
            $row['date'] = utilities::conv2Date($rk->date);
            $row['time'] = $rk->time;
            $row['referrer'] = $rk->referrer;
            $row['search'] = urldecode($rk->search);
            $row['searchengine'] = $rk->searchengine;
            $results[]=$row;
        }
        return $results;
    }
}
