<?php
/*
 * KeyCache implements a cache method to shrink order o(k.n) into o(1) (specifically o(5n) to o(1))
 * KeyCache has an exception: it is forbidden to cache current day values. That is because current day is always changing.
 * KeyCache works with HISTORY and the history never changes.
 */
class keyCache
{
public $visitors;
public $pageviews;
public $spiders;
public $feeds;
public $errors;
private $key;
private $keyType; //if the keys is invalid, get out
private $today;
private $thisMonth;

    public function __construct($key,$keyType)
    {
    $this->key=$key;
    $this->keyType=$keyType;
    $this->today = gmdate('Ymd', current_time('timestamp'));
    $this->thisMonth = gmdate('Ym', current_time('timestamp'));
    }

    /*
    * returns if it is cached .If true, load the data into the properties.
    * 201200607: Introduced global. Global are general queries involving ALL records.
    * We deal with this data, splitting the results as Total=Total(yesterday) + Total (today). Total until yesterday can be cached.
    * also the key has to be different from day case.
    * The global settings has to be called using yesterday date. Calculations using today info will be ignored.
     * 20120723: slight simplification on caching. the key now is keytype + key, simplifying the global caching.
     * There is no need to make exception on global values.
    */
    public function isCached()
    {
        //Retrieve day from metadata if exists
        //get_post_meta is a little obscure, so we deal directly with arrays instead relying in array inner working
        //Assumptions:
        //Key for different data never is repeated:
        //this is true for: day: YYYYMMDD, every day has different coded day
        //                  month: YYYYMM, same reasons
        //                  generic values: prefixed global to avoid confusion with day keys.

        $keyCache=get_post_meta(utilities::statcommSummary,$this->keyType . $this->key,true);

        //False if we can't find the key on cache
        if (empty($keyCache)) return false;
        //get today and this month.

        switch ($this->keyType) {
            case "day":

                //Rule: current day avoids cache.
                if ($this->key==$this->today )  return false;
                break;
            case "month":

                //Rule:current month avoids cache.
                //Remember this month can be discomposed as
                //Totals(1 to yesterday)+Totals(today). The trick is total(today) should be faster than total(this month)
                if ($this->key ==$this->thisMonth) return false;
                break;

            case "global": //special case. this global has the text 'global', prefixed. There are no exceptions
                if ($this->key == $this->today) return false;
                break;
           default:
               trigger_error("Type <strong>{$this->keyType}</strong> not implemented for method isCached", E_USER_ERROR);
        }
        //key cached, fetch it
        $this->visitors = $keyCache['visitors'];
        $this->pageviews = $keyCache['pageviews'];
        $this->spiders = $keyCache['spiders'];
        $this->feeds = $keyCache['feeds'];
        $this->errors = $keyCache['errors'];
        return true;
   }

    /**
    * make the cache.
    * Return true if successfully made the cache, and false if we tried to make the cache for current day.
    * @return bool
    */
    public function makeKeyCached()
    {
        $acceptedTypes=array("day","month","global");
        if(!in_array($this->keyType, $acceptedTypes))
        {
            trigger_error("Type <strong>$acceptedTypes</strong> doesn't exist", E_USER_ERROR);
        }

        switch ($this->keyType) {
            case "day": //in case day the key is in the format YYYYMMDD
                $qryVisitors=  mySql::get_row(mySql::QRY_VisitorsDay,$this->key);
                $qryPageViews= mySql::get_row(mySql::QRY_PageViewsDay,$this->key);
                $qrySpiders=   mySql::get_row(mySql::QRY_SpidersDay,$this->key);
                $qryFeeds=     mySql::get_row(mySql::QRY_FeedsDaily,$this->key);
                $qryErrors=    mySql::get_row(mySql::QRY_ErrorsDay,$this->key);
                break;
            case "month": //month: the key is in the format YYYYMM
                $qryVisitors=  mySql::get_row(mySql::QRY_VisitorsMonth,$this->key);
                $qryPageViews= mySql::get_row(mySql::QRY_PageViewsMonth,$this->key);
                $qrySpiders=   mySql::get_row(mySql::QRY_SpidersMonth,$this->key);
                $qryFeeds=     mySql::get_row(mySql::QRY_FeedsMonth,$this->key);
                $qryErrors=    mySql::get_row(mySql::QRY_ErrorsMonth,$this->key);
                break;
            case "global": //global: the key is in the format YYYYMMDD
                $qryVisitors=  mySql::get_row(mySql::QRY_Visitors_Global,$this->key);
                $qryPageViews= mySql::get_row(mySql::QRY_PageViews_Global,$this->key);
                $qrySpiders=   mySql::get_row(mySql::QRY_Spiders_Global,$this->key);
                $qryFeeds=     mySql::get_row(mySql::QRY_Feeds_Global,$this->key);
                $qryErrors=    mySql::get_row(mySql::Qry_Errors_Global,$this->key);
                break;
            default:
                trigger_error("Type <strong>{$this->keyType}</strong> not implemented yet", E_USER_ERROR);
        }

        $fields=array();
        $fields['visitors'] = $qryVisitors->visitors;
        $fields['pageviews']= $qryPageViews->pageview;
        $fields['spiders']  = $qrySpiders->spiders;
        $fields['feeds']    = $qryFeeds->feeds;
        $fields['errors']   = $qryErrors->errors;

        $this->visitors=$qryVisitors->visitors;
        $this->pageviews=$qryPageViews->pageview;
        $this->spiders=$qrySpiders->spiders;
        $this->feeds =$qryFeeds->feeds;
        $this->errors=$qryErrors->errors;


        //Skip to save in case today or this month

        if ($this->key==$this->today)
        {
            if (in_array($this->keyType,array("day","global")))
            {
                utilities::fl("Can't be cached: {$this->key}, {$this->keyType} , today is:{$this->today} ");
                return;
            }
        }
        else
        {
         utilities::fl("Cached: {$this->key}, {$this->keyType} , today is:{$this->today} ");
         update_post_meta(utilities::statcommSummary, $this->keyType . $this->key , $fields, true);
         return;
        }

        if ($this->key ==$this->thisMonth)
        {
            if($this->keyType=="month")
            {
                utilities::fl("Can't be cached month:{$this->key}, {$this->keyType} ");
                return;
            }
        }
        else
        {
            utilities::fl("Cached Month: {$this->key}, {$this->keyType} , today is:{$this->today} ");
            update_post_meta(utilities::statcommSummary, $this->keyType . $this->key , $fields, true);
            return;
        }
    }
}
?>