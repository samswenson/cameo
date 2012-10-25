<?php
class drawChart extends statcommMustache
{
    public function templateName() {return "drawChart";}

    public function unique_color() { return "#114477";}
    public function web_color()    { return "#3377B6";}
    public function rss_color()    { return "#f38f36";}
    public function spider_color() { return "#83b4d8";}
    public function error_color()  { return "#cc2d2d";}

    public function visitorsText()  { return __('visitors'  , 'statcomm');}
    public function pageviewsText() { return __('pageviews' , 'statcomm');}
    public function spidersText()   { return __('spiders'   , 'statcomm');}
    public function feedsText()     { return __('feeds'     , 'statcomm');}
    public function errorsText()    { return __('errors'    , 'statcomm');}

    public function rows()
    {
        $options = settingsAPI::getOptions();
        $gdays = $options['cmb_overview_graph']; //get day numbers. Watchout data has to be numeric.
        $showErrors= empty($options['chk_errors'])?false:true;

        $gdays = ($gdays == 0)? 20: $gdays;
        $start_of_week = get_option('start_of_week'); //TODO: FIX IT!
        $qry =mySql::get_row(mySql::QRY_MaxPageViews,gmdate('Ymd', current_time('timestamp') - 86400 * $gdays));

        $maxxday =isset($qry->pageview)?$qry->pageview:0;
        $maxxday = max(1,$maxxday);
        // Y
        $gd = (90 / $gdays) . '%';

        //Loop for every day depending on settings
        $results=array();
        for ($gg = $gdays - 1; $gg >= 0; $gg--)
        {
            $row=array();
            $currentDay = gmdate('Ymd', current_time('timestamp') - 86400 * $gg);
            $qryCached =mySql::get_row_cached($currentDay,"day");
            $row['px_visitors']  = round($qryCached->visitors * 100 / $maxxday);
            $row['px_pageviews'] = round($qryCached->pageviews * 100 / $maxxday);
            $row['px_spiders']   = round($qryCached->spiders * 100 / $maxxday);
            $row['px_feeds']     = round($qryCached->feeds * 100 / $maxxday);
            $row['px_errors']    = ($showErrors)?round($qryCached->errors * 100 / $maxxday):0;

            $row['px_white']     = 100 - $row['px_feeds'] - $row['px_spiders']
                - $row['px_pageviews'] - $row['px_visitors'] ;

            if ($showErrors)
            {
                $row['px_white'] = $row['px_white'] - $row['px_errors'];
            }
            if ($start_of_week == gmdate('w', $currentDay))
            {
                $row['start_of_week'] = ' style="border-left:2px dotted gray;"';
            }
            $row['column_width'] =$gd; //this is constant should be calculated outside.

            $row['visitors'] = $qryCached->visitors;
            $row['pageviews'] = $qryCached->pageviews;
            $row['spiders'] = $qryCached->spiders;
            $row['feeds'] = $qryCached->feeds;
            $row['errors'] = $qryCached->errors;

            $title= $qryCached->visitors . " " . __('visitors', 'statcomm') . "\n" .
                $qryCached->pageviews . " " . __('pageviews', 'statcomm') . "\n" .
                $qryCached->spiders . " " . __('spiders', 'statcomm') . "\n" .
                $qryCached->feeds . " " . __('feeds', 'statcomm') . "\n" ;
            if ($showErrors==true)
            {
                $title .=   $qryCached->errors . " " . __('errors', 'statcomm') . "\n" ;
            }

            $row['column_label'] = $title;
            $row['day_number'] =gmdate('d', current_time('timestamp') - 86400 * $gg);
            $row['month_name'] =gmdate('M', current_time('timestamp') - 86400 * $gg);

            //1.7.30:Filter to add additional text to columns
            $eR=$this->reportLink($currentDay);
            $row['linkReport']=(empty($eR))?"":"<a href='$eR'>ER</a>";

            $results[]=$row;
        }
        return $results;
    }

    //Create link to report if subplugin report is activated.abstract
    //This is NOT the way we are looking for,expecting to improve this.abstract
    //Ideally we should use a filter to make it work.
    function reportLink($currentDay)
    {
        //How we detect if one specific subplugin is active?
        if (subPlugins::is_subplugin_active('error-report/error_report.php'))
        {
            $link = wp_nonce_url(site_url("/wp-admin/admin.php?page=statComm/errorreport&day=$currentDay"));

            return $link;
        }
        return "";
    }
}
