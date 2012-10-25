<?php
class tableOverview extends statcommMustache
{
    private $today;
    private $yesterday;
    private $thisMonth;
    private $lasthMonth;
    private $lastMonthYear;
    private $lastMonthNumber;

    private $cachedLastMonthTotals; //provides visitor,pageview,spider,feed & error
    private $cachedYesterdayTotals; //provides visitor,pageview,spider,feed & error

    private $thisMonthVisitorTotals;
    private $thisMonthPageviewTotals;
    private $thisMonthSpiderTotals;
    private $thisMonthFeedTotals;
    private $thisMonthErrorTotals;

    private $todayVisitors;
    private $todayPageviews;
    private $todaySpiders;
    private $todayFeeds;
    private $todayErrors;

    public function templateName()   { return "tableOverview";}

    public function __construct()
    {
        $this->today           = gmdate('Ymd', current_time('timestamp'));
        $this->yesterday       = gmdate('Ymd', current_time('timestamp') - 86400);;
        $this->thisMonth       = gmdate('Ym', current_time('timestamp'));
        $this->lasthMonth      = utilities::statcommLastMonth();
        $this->lastMonthYear   = mb_substr($this->lasthMonth, 0, 4);
        $this->lastMonthNumber = mb_substr($this->lasthMonth, 4, 2);

        //get calculation based on caching
        //caching is based on yesterday results.
    }

    //201200724:This should be in the constructor, but it does not properly updated counters
    //It seems to be a problem with the way of PHP is working.
    //To solve it we attach the initialize to the first method called
    private function Initialize()
    {
        $this->cachedAllTotals   = mySql::get_row_cached($this->yesterday,"global");
        $this->cachedLastMonthTotals = mySql::get_row_cached($this->lasthMonth,"month");
        $this->cachedYesterdayTotals = mySql::get_row_cached($this->yesterday,"day");

        $this->thisMonthVisitorTotals   = mySql::get_row(mySql::QRY_VisitorsMonth, mysql_real_escape_string($this->thisMonth));
        $this->thisMonthPageviewTotals  = mySql::get_row(mySql::QRY_PageViewsMonth,mysql_real_escape_string($this->thisMonth));
        $this->thisMonthSpiderTotals    = mySql::get_row(mySql::QRY_SpidersMonth,mysql_real_escape_string($this->thisMonth));
        $this->thisMonthFeedTotals      = mySql::get_row(mySql::QRY_FeedsMonth, mysql_real_escape_string($this->thisMonth));
        $this->thisMonthErrorTotals     = mySql::get_row(mySql::QRY_ErrorsMonth, mysql_real_escape_string($this->thisMonth));

        $this->todayVisitors  = mySql::get_row(mySql::QRY_VisitorsDay , mysql_real_escape_string($this->today));
        $this->todayPageviews = mySql::get_row(mySql::QRY_PageViewsDay, mysql_real_escape_string($this->today));
        $this->todaySpiders   = mySql::get_row(mySql::QRY_SpidersDay  , mysql_real_escape_string($this->today));;
        $this->todayFeeds     = mySql::get_row(mySql::QRY_FeedsDaily  , mysql_real_escape_string($this->today));
        $this->todayErrors    = mySql::get_row(mySql::QRY_ErrorsDay   , mysql_real_escape_string($this->today));

    }


    public function unique_color() { return "#114477";}
    public function web_color() { return "#3377B6";}
    public function feed_color() { return "#f38f36";}
    public function spider_color() { return "#83b4d8";}


    //Workaround to get an exact query and cache counter.
    public function title()          {
        $this->Initialize();
        return  __('Overview', 'statcomm');
    }
    public function totalTitle()     { return __('Total', 'statcomm'); }
    public function lastMonthTitle()      { return __('Last month', 'statcomm');}
    public function lastMonthValue() { return gmdate('M, Y', gmmktime(0, 0, 0, $this->lastMonthNumber, 1, $this->lastMonthYear));}
    public function thisMonthTitle() { return __('This month', 'statcomm'); }
    public function thisMonthValue() { return gmdate('M, Y', current_time('timestamp')); }
    public function targetTitle()    { return __('Target this month', 'statcomm'); }
    public function targetValue()    { return  gmdate('M, Y', current_time('timestamp'))  ; }
    public function yesterdayTitle() { return __('Yesterday', 'statcomm'); }
    public function yesterdayValue() { return gmdate('d M, Y', current_time('timestamp') - 86400);}
    public function todaytitle()     { return __('Today', 'statcomm');}
    public function todayValue()     { return gmdate('d M, Y', current_time('timestamp'));}

    /*********VISITORS******/
    public function visitorsTitle()     { return __('Visitors', 'statcomm');}
    public function visitorsTotal()     { return $this->cachedAllTotals->visitors + $this->todayVisitors->visitors ; }
    public function visitorsLastMonth() { return $this->cachedLastMonthTotals->visitors; }
    public function visitorsThisMonth() { return $this->thisMonthVisitorTotals->visitors; }
    public function visitorsMonthlyPercent(){
        return $this->perCent($this->visitorsThisMonth(), $this->visitorsLastMonth());    }
    public function visitorsAverageMonth(){
        return $this->avgMonth($this->visitorsThisMonth(), $this->visitorsLastMonth());   }
    public function visitorsYesterday() { return $this->cachedYesterdayTotals->visitors;  }
    public function visitorsToday()     { return $this->todayVisitors->visitors;}

    /***********PAGEVIEWS*********/
    public function pageviewsTitle()    { return __('Pageviews', 'statcomm'); }
    public function pageviewsTotal()    { return $this->cachedAllTotals->pageviews +  $this->todayPageviews->pageview; }
    public function pageviewsLastMonth(){ return $this->cachedLastMonthTotals->pageviews ; }
    public function pageviewsThisMonth(){ return $this->thisMonthPageviewTotals->pageview;}
    public function pageviewsMonthlyPercent() {
        return $this->perCent($this->pageviewsThisMonth(), $this->pageviewsLastMonth());}
    public function pageviewsAverageMonth(){
        return $this->avgMonth($this->pageviewsThisMonth(), $this->pageviewsLastMonth());   }
    public function pageviewsYesterday(){ return $this->cachedYesterdayTotals->pageviews; }
    public function pageviewsToday()    { return $this->todayPageviews->pageview; }

    /**********SPIDERS*****************/
    public function spidersTitle()      { return __('Spiders', 'statcomm');}
    public function spidersTotal()      { return $this->cachedAllTotals->spiders +$this->todaySpiders->spiders; }
    public function spidersLastMonth()  { return $this->cachedLastMonthTotals->spiders; }
    public function spidersThisMonth()  { return $this->thisMonthSpiderTotals->spiders; }
    public function spidersMonthlyPercent(){
        return $this->perCent($this->spidersThisMonth(), $this->spidersLastMonth()); }
    public function spidersAverageMonth(){
        return $this->avgMonth($this->spidersThisMonth(), $this->spidersLastMonth());   }
    public function spidersyesterday()  { return $this->cachedYesterdayTotals->spiders ; }
    public function spiderstoday()      { return $this->todaySpiders->spiders;   }

    /**********FEEDS*****************/
    public function feedsTitle()        { return __('feeds', 'statcomm');}
    public function feedsTotal()        { return $this->cachedAllTotals->feeds +  $this->todayFeeds->feeds; }
    public function feedsLastMonth()    { return $this->cachedLastMonthTotals->feeds; }
    public function feedsThisMonth()    { return $this->thisMonthFeedTotals->feeds; }
    public function feedsMonthlyPercent(){
        return $this->perCent($this->feedsThisMonth(), $this->feedsLastMonth()); }
    public function feedsAverageMonth() {
        return $this->avgMonth($this->feedsThisMonth(),  $this->feedsLastMonth());   }
    public function feedsyesterday()    { return $this->cachedYesterdayTotals->feeds ; }
    public function feedstoday()        { return $this->todayFeeds->feeds;   }
    /*************************/

    /**
     * Returns porcentual variation between 2 values (formatted)
     * @param $currVal
     * @param $prevVal
     * @return string
     */
    private function perCent($currVal,$prevVal)
    {
        if ($prevVal==0) return "";
        $pc = round(100 * ($currVal / $prevVal) - 100, 1);
        if ($pc >= 0)
        {
            $pc = "+" . $pc;
        }
        return "<code> (" . $pc . "%)</code>";
    }

    /**
     * Estimation average monthly based on visitors to current day.
     * Formula:  Part 1=(visitor this month)/(seconds this month)* 86400 = this is the visitor average by day
     *           Part 2= Part 1 * days from this month = average estimated based on part 1

     * @param $currVal
     * @param $prevVal
     * @return string
     */
    private function avgMonth($currVal,$prevVal)
    {
        $target = round($currVal / (time() - mktime(0,0,0,date('m'),date('1'),date('Y'))) * (86400 * date('t')));
        return  $target . $this->perCent($target,$prevVal);
    }

    /**
     * Added message if Maxmind database is missing or corrupted
     */
    public function Message()
    {
        $errorMsg  = "<div id='message' class='error'>%s</div>";
        $normalMsg = "<div id='message' class='updated'>%s</div>";
        $optionsLink ="<a href='" .
                       wp_nonce_url(admin_url( "admin.php?page=statComm/options&tab=statcomm_options_advanced" ),"statcomm-options") .
                        "' >" .  __('Options', 'statcomm') .  "</a>";

        $geoLocation = utilities::geoLocationEnabled();
        switch ($geoLocation) {
            case utilities::ERROR_FILE_NOT_FOUND:
                $msgType=$normalMsg;
                $msg =sprintf( __( "Check %s to enable Maxmind database","statcomm"), $optionsLink);
                break;
            case utilities::ERROR_FILE_OPEN:
                $msgType=$errorMsg;
                $msg =sprintf( __("Error opening Maxmind database. Check %s to fix","statcomm") ,$optionsLink);
                break;
            case utilities::ERROR_INCORRECT_VERSION:
                $msgType=$errorMsg;
                $msg =sprintf( __("Unable to get Maxmind database version. (database corrupted?). Check %s to fix","statcomm"),$optionsLink);
                break;
            default:
                $msg ="";
        }
        if (empty($msg)) {return;} //Skip msg if everything is working
        if (is_multisite())
        {
            $msg = __("Maxmind database currently disabled. Check with Network Administrator to enable.");
        }
        return sprintf($msgType,"<p>" . $msg . "</p>");
    }
}
