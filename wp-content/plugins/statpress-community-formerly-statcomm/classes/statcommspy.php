<?php
/**
 * Created by WpGetReady n @2012
 * Author: Fernando Zorrilla de San Martin
 * Date: 5/1/12
 * Time: 5:48 PM
 * v1.6.70: Rewriting of spy feature.
 * v1.6.90: added CNT_REC_ID and site link
 */

Class statcommSpy
{
    Const LINE_SPLIT=38;
    //Constants used for replacing tooltip information.
    Const C_FLAG="#CNT_FLAG#";
    Const C_COUNTRY="#CNT_COUNTRY#";
    Const C_CONTINENT= "#CNT_CONTINENT#";
    Const C_REGION="#CNT_REGION#";
    Const C_CITY="#CNT_CITY#";
    Const C_AGENT="#CNT_AGENT#";
    Const C_RA_TITLE="#CNT_RA_TITLE#";
    Const C_ACTIVITY="#CNT_ACTIVITY#";
    Const C_LATLONG_TITLE="#CNT_LATLONG_TITLE#";
    Const C_LAT ="#CNT_LAT#";
    Const C_LONG="#CNT_LONG#";
    Const C_HOST_TITLE="#CNT_HOST_TITLE#";
    Const C_HOST="#CNT_HOST#";
    Const C_REC_ID="#CNT_REC_ID#";
    Const C_LOGO ="#CNT_LOGO#";
    Const C_SITE ="#CNT_SITE#";
    Const C_SITE_TITLE="#CNT_SITE_TITLE#";

    /**
     * This basically is the spy page improved to use maxmind. hostip.info can say goodbye
     * @static
     * @return mixed
     */
    static function spy()
    {
        //Check if Geoloctaion is enabled
        $geoLocation=utilities::geolocationEnabled();
        $gi=null;

        print "<div class='wrap'><h2>" . __('Spy', 'statcomm') . "</h2>";

        switch ($geoLocation){
            case utilities::ERROR_FILE_NOT_FOUND:
                print "<b>" . __("Maxmind database needs to enabled . Check the plugins options." ,"statcomm") . "</b>";
                return;
                break;
            case utilities::ERROR_FILE_OPEN:
                print "<b>" . __("Unable to open Maxmind database (corrupted?) . Check the plugins options." ,"statcomm") . "</b>";
                return;
                break;

            case utilities::ERROR_INCORRECT_VERSION:
                print "<b>" . __("Unable to get Maxmind database version (corrupted?) . Check the plugins options." ,"statcomm") . "</b>";
                return;
                break;
        }

        $options = settingsAPI::getOptions();
        $howManyDays=isset($options['cmb_spy_back'])?$options['cmb_spy_back']:2;
        $LIMIT      =isset($options['cmb_spy_results'])?$options['cmb_spy_results']:5;

        $mySql= new mySql();
        $page =isset($_GET['pn'])?$_GET['pn']:1;
        $page = ($page<=0)?1:$page;
        // Create MySQL Query String
        $query = mySql::get_results(mySql::QRY_Spy1);
        //TODO:check is numRows is effectively working
        //$NumOfPages = ($mySql->numRows()) / $LIMIT;
        $NumOfPages = 10;
        $LimitValue = ($page * $LIMIT) - $LIMIT;

        // Spy
        $today = gmdate('Ymd', current_time('timestamp'));
        $yesterday = gmdate('Ymd', current_time('timestamp') - $howManyDays*86400);
        //Note: We only need ip to make it happen
        $qry = mySql::get_results(mySql::QRY_Spy2,array($LimitValue,$LIMIT));
        ?>
    <script>
        function ttogle(thediv){
            if (document.getElementById(thediv).style.display=="inline") {
                document.getElementById(thediv).style.display="none"
            } else {document.getElementById(thediv).style.display="inline"}
        }
    </script>
    <div align="center">
        <div id="paginating" align="center">Pages:
            <?php

            // Check to make sure we're not on page 1 or Total number of pages is not 1
            if($page == ceil($NumOfPages) && $page != 1) {
                for($i = 1; $i <= ceil($NumOfPages)-1; $i++) {
                    // Loop through the number of total pages
                    if($i > 0) {
                        // if $i greater than 0 display it as a hyperlink
                        echo '<a href="' . $_SERVER['SCRIPT_NAME'] . '?page=statComm/spy&pn=' . $i . '">' . $i . '</a> ';
                    }
                }
            }

            $startPage =($page == ceil($NumOfPages))? $page:1;

            for ($i = $startPage; $i <= $page+6; $i++) {
                // Display first 7 pages
                if ($i <= ceil($NumOfPages)) {
                    // $page is not the last page
                    if($i == $page) {
                        // $page is current page
                        echo " [{$i}] ";
                    } else {
                        // Not the current page Hyperlink them
                        echo '<a href="' . $_SERVER['SCRIPT_NAME'] . '?page=statComm/spy&pn=' . $i . '">' . $i . '</a> ';
                    }
                }
            }
            if ($geoLocation == utilities::ERROR_NONE ) { $gi = utilities::geoLocationOpen();}
            $parser= new statcommParser();
            //$ua=$parser->Parse($hit->agent);
            ?>
        </div>
        <table id="mainspytab" width="99%" border="0" cellspacing="0" cellpadding="4">
            <?php

            foreach ($qry as $rk)
            {
                print "<tr><td colspan='2' bgcolor='#dedede'><div align='left'>";
                //get Geolocation info
                utilities::fl("ip:",$rk->ip);
                $record =GeoIpCity_Ctrl::GeoIP_info_by_addr($gi, $rk->ip);
                utilities::fl("record:",$record);
                //get User Agent info.
                $ua =$parser->Parse($rk->agent);
                print utilities::make_flag_icon($record);
                //print "<IMG SRC='http://api.hostip.info/flag.php?ip=" . $rk->ip . "' border=0 width=18 height=12>";
                print " <strong><span><font size='2' color='#7b7b7b'>" . $rk->ip . "</font></span></strong> ";
                print "<span style='color:#006dca;cursor:pointer;border-bottom:1px dotted #AFD5F9;font-size:8pt;' onClick=ttogle('" . $rk->ip . "');>" . __('more info', 'statcomm') . "</span></div>";
                print "<div id='" . $rk->ip . "' name='" . $rk->ip . "'>" ;// . $rk->os . ", " . $rk->browser;
                //    print "<br><iframe style='overflow:hide;border:0px;width:100%;height:15px;font-family:helvetica;paddng:0;' scrolling='no' marginwidth=0 marginheight=0 src=http://showip.fakap.net/txt/".$rk->ip."></iframe>";
                //print "<br><iframe style='overflow:hide;border:0px;width:100%;height:60px;font-family:helvetica;paddng:0;' scrolling='no' marginwidth=0 marginheight=0 src=http://api.hostip.info/get_html.php?ip=" . $rk->ip . "></iframe>";
                print   utilities::make_os_icon ($ua['os_icon']) . __(" Operative System (family / type): ") . $ua['os_family'] . " / " . $ua['os_name'] . "<br/>" ;
                print   utilities::make_uas_icon($ua['ua_icon']) . __(" Browser (family / type): ") . $ua['ua_family'] . " / " . $ua['ua_name'] .  "<br/>" ;
                if (empty($record))
                {
                    print __("Continent / Country / Region / City: ") . __("Unknown","statcomm") . " / " . __("Unknown","statcomm") .
                        " / " . __("Unknown","statcomm") . " / " . __("Unknown","statcomm") . "<br/>" ;
                    print __("Latitude / Longitude : ") . __("Unknown","statcomm") . " / " . __("Unknown","statcomm") . "<br/>" ;

                }
                else
                {
                    print __("Continent / Country / Region / City: ") . $record->continent_name . " / " . $record->country_name .
                        " / " . $record->region_name . " / " . $record->city . "<br/>" ;
                    print __("Latitude / Longitude : ") . $record->latitude . " / " . $record->longitude . "<br/>" ;
                }
                print __("Host: ") . "<small>" . gethostbyaddr($rk->ip) . "</small>" . "<br/>" ;
                print __("Agent: ") . "<small>" . $rk->agent . "</small>" . "<br/>" ;

                print "</div>";
                print "<script>document.getElementById('" . $rk->ip . "').style.display='none';</script>";
                print "</td></tr>";
                //Give me activity between 48 hours and limit it by 10
                $qry3param=                  "SELECT * FROM #table# WHERE ip='" . $rk->ip . "' AND (date BETWEEN '$yesterday' AND '$today') order by id LIMIT 10";
                $qry2 = mySql::get_results($qry3param);
                foreach ($qry2 as $details)
                {
                    print "<tr>";
                    print "<td valign='top' width='151'><div><font size='1' color='#3B3B3B'><strong>" . utilities::conv2Date($details->date) . " " . $details->time . "</strong></font></div></td>";
                    print "<td><div><a href='" . utilities::irigetblogurl() .
                        ((strpos($details->urlrequested, 'index.php') === FALSE) ? $details->urlrequested : '') .
                        "' target='_blank'>" .
                        utilities::outUrlDecode($details->urlrequested) . "</a>";
                    if ($details->searchengine != '')
                    {
                        print "<br><small>" . __('arrived from', 'statcomm') . " <b>" . $details->searchengine . "</b> " . __('searching', 'statcomm') . " <a href='" . $details->referrer . "' target=_blank>" . urldecode($details->search) . "</a></small>";
                    }
                    //TODO: FIX IT!
                    elseif ($details->referrer != '' && strpos($details->referrer, get_option('home')) === false)
                    {
                        print "<br><small>" . __('arrived from', 'statcomm') . " <a href='" . $details->referrer . "' target=_blank>" . $details->referrer . "</a></small>";
                    }
                    print "</div></td>";
                    print "</tr>\n";
                }
            }
            ?>
        </table>
    </div>
    <?php
        if ($geoLocation != utilities::ERROR_NONE ){ GeoIP_Ctlr::geoip_close($gi); }
    }

    /**
     * Makes the html code for tooltip spy information
     * Improved data management.
     * v1.6.90:watchout $record can return empty(null)
     * @static
     * @param $recId
     */
    static function makeTooltipMsg($recId)
    {

        //Check if Geoloctaion is enabled
        $geoLocation=utilities::geolocationEnabled();
        $gi=null;
        if ($geoLocation != utilities::ERROR_NONE)
        {
            print "<b>" . __("Maxmind database disabled. Check the plugins options to activate it.","statcomm") . "</b>";
            return;
        }
        
        $options = settingsAPI::getOptions();
        $howManyDays=isset($options['cmb_spy_back'])?$options['cmb_spy_back']:2;
        $limit      =isset($options['cmb_spy_results'])?$options['cmb_spy_results']:5;

        //Get timeframe to set a search limit
        $today = gmdate('Ymd', current_time('timestamp'));
        $previousDays = gmdate('Ymd', current_time('timestamp') - $howManyDays*86400);

        $qry = mySql::get_row(mySql::QRY_Spy,$recId); //Retrieve info for one record on the table
        if(!$qry) //if we don't get results, quit
        {
            echo "<b>" . printf(  __("Error: no info for rec: %s","statcomm") , $recId) . "</b>";
            return;
        }
        //Return activity between today and x day back  and limit it by 10
        //Limit also should be controlled
        $qryParam = "SELECT * FROM #table# WHERE ip='" . $qry->ip . "' AND (date BETWEEN '$previousDays' AND '$today') order by id LIMIT $limit";
        $qry2 = mySql::get_results($qryParam);

        $parser= new statcommParser();
        $ua=$parser->Parse($qry->agent);
        //This should be unified and simplified once for all.
        if ($geoLocation == utilities::ERROR_NONE){$gi= utilities::geoLocationOpen();}

        $record =GeoIpCity_Ctrl::GeoIP_info_by_addr($gi, $qry->ip);

        //First version: tables
        //This should be enhanced a lot.
       //echo "so far so good"; return;
        utilities::fl("qry:", $qry);
        utilities::fl("qry2:", $qry2);
        utilities::fl("User agent:", $ua);
        utilities::fl("record:" , $record);
        
        //Improved: tooltip format is constructed in a html page
        utilities::fl("file:" , dirname(__FILE__) .    "/js/sc-tooltip.htm");

        //Fill the blanks with geoLocation info
        $tooltipFormat=self::geoLocationFormat($recId,$record,gethostbyaddr( $qry->ip));

        //Type classification
        //0[] = "Browser" / 1[] = "Offline Browser" / 3[] = "Mobile Browser" /4[] = "Email client"
        //5[] = "Library"/ 6[] = "Wap Browser" /10[] = "Validator" /15[] = "Feed Reader" /
        //18[] = "Multimedia Player"/ 20[] = "Other" /50[] = "Useragent Anonymizer" /Robot/ unknown

        //$parsing="";

        $feed= isset($qry->feed)?$qry->feed:"";
        $isFeed= ($feed !="")?true:false;
        switch (strtolower($ua['typ'])) {
            case 'robot':
                $parsing = "<td>" . __("Not available (robot)","statcomm")  .  "</td>";
                break;
            case 'other':
                $parsing = "<td>" . utilities::make_uas_icon($ua['ua_icon']) . " " . $ua['ua_name'] .  "</td>";
                //No OS
                $parsing .=  "<td>" . __("Other: libraries, WebKit, monitor network,etc. ","statcomm")  .  "</td>";
                break;
            case 'feed reader':
                $parsing = "<td>" . utilities::make_uas_icon($ua['ua_icon']) . " " . $ua['ua_name'] .  "</td>";
                //No OS
                $parsing .= "<td>" . __("Feed Reader","statcomm")  .  "</td>";
                break;
            case 'unknown':                 //Try to guess: did the user come for RSS?
                if ($isFeed)
                {
                    $parsing = "<td colspan='2' class='tooltip-aligncenter'>" . sprintf( __("Feed Reader (%s)","statcomm"),$feed)  .  "</td>";
                }
                else
                {
                    $parsing = "<td colspan='2' class='tooltip-aligncenter'>" . __("OS & Browser: Not enough data","statcomm")  .  "</td>";
                }
                break;
            default:
                $parsing = "<td>" . utilities::make_uas_icon($ua['ua_icon']) . " " . $ua['ua_name'] .  "</td>";
                $parsing .=  "<td>" . utilities::make_os_icon($ua['os_icon']) . " " . $ua['os_name'] .  "</td>";
        }

        //display browser and os
        $tooltipFormat = str_replace(self::C_AGENT, $parsing,$tooltipFormat);

        //If there is no other activity, stop right here
        if (count($qry2)<=1)
        {
            $tooltipFormat = str_replace(self::C_RA_TITLE, sprintf(__("No other activity since last %s days<br/> (max.%s results) ","statcomm"),$howManyDays,$limit), $tooltipFormat );
            //Delete activity
            $tooltipFormat = str_replace(self::C_ACTIVITY, "", $tooltipFormat );
            echo $tooltipFormat;
            return;
        }
        //display Activity title
        $tooltipFormat = str_replace(self::C_RA_TITLE, sprintf(__("Recent Activity in the last %s days<br/> (%s max.results)","statcomm"),$howManyDays,$limit), $tooltipFormat );

        $activity ="";
        foreach ($qry2 as $details)
        {
            $activity .=  "<tr>";
            $activity .= "<td class='tooltip-time'>" . utilities::conv2Date($details->date) . " " . $details->time . "</td>";
            $activity .= "<td class='tooltip-url'><a href='" . utilities::irigetblogurl() .
                ((strpos($details->urlrequested, 'index.php') === FALSE) ? $details->urlrequested : '') .
                "' target='_blank'>" .
                utilities::outUrlDecode($details->urlrequested) . "</a>";
            $activity .= "</td></tr>";
            $activity .= "<tr>";

            //Check if we need to show arrived

            $detEmpty=empty($details->searchengine);
            $refEmpty=empty($details->referrer) or (($details->searchengine != '') and $detEmpty);
            utilities::fl("detEmpty is $detEmpty and refEmpty is $refEmpty");
            if (!$isFeed and !$refEmpty  )
            {
                $activity .= "<td class='tooltip-alignright'>" . __('arrived from', 'statcomm') . "</td>";

                if ($details->searchengine != '')
                {
                    $activity .= "<td class='tooltip-searchengine'>" . $details->searchengine . "</b> " . __('searching', 'statcomm') . "</td>";
                    $activity .= "</tr>";
                    $activity .= "<tr>";
                    $activity .= "<td colspan='2' class='tooltip-referrer'><a href='" .  $details->referrer . "' target=_blank>" . urldecode($details->search) . "</a></td>";
                }
                //TODO: FIX IT!
                elseif ($details->referrer != '' && strpos($details->referrer, get_option('home')) === false)
                {
                    $activity .= "<td class='tooltip-referrer'><a href='" . $details->referrer . "' target=_blank>" . self::splitReferrer( $details->referrer) . "</a></td>";
                }
            }
            $activity .= "</tr>\n";
        }

        $tooltipFormat = str_replace(self::C_ACTIVITY, $activity, $tooltipFormat );
        //echo "so far so good"; return;
        //utilities::fl("tt so far:" , $tooltipFormat);
        echo $tooltipFormat;
        //This go on the end
        if ($geoLocation == utilities::ERROR_NONE) { GeoIP_Ctlr::geoip_close($gi);}
    }

    /**
     * Divide the url referrer into multiples lines
     * @static
     * @param $urlReferrer
     * @return string
     */
    static function splitReferrer($urlReferrer)
    {
        if (strlen($urlReferrer)>self::LINE_SPLIT)
        {
            return chunk_split($urlReferrer,self::LINE_SPLIT, "<br/>");
        }
        return $urlReferrer;
    }

    /**
     * Adds geoLocation information to the tooltip
     * @static
     * @param $recId
     * @param $record
     * @param $hostip
     * @return mixed|string
     */
    static function geoLocationFormat($recId,$record,$hostip)
    {
        $tooltipFormat=@file_get_contents(dirname(plugin_dir_path(__FILE__)) .    "/js/sc-tooltip.htm");
        if ($tooltipFormat===FALSE)
        {
            echo __("error returning tooltip format sc-tooltip.htm not found. Aborting...","statcomm");
            return '';
        }
        $tooltipFormat =str_replace(self::C_LOGO, dirname(plugin_dir_url(__FILE__)) .    "/images/statcomm-32x32.png" , $tooltipFormat);
        $tooltipFormat =str_replace(self::C_SITE, "http://www.wpgetready.com/?scname=" . utilities::PLUGIN_VERSION  , $tooltipFormat);
        $tooltipFormat =str_replace(self::C_SITE_TITLE, __("Site News"),$tooltipFormat);
        $tooltipFormat = str_replace(self::C_LATLONG_TITLE, __("Latitude/Longitude","statcomm"),$tooltipFormat);
        $tooltipFormat = str_replace(self::C_HOST_TITLE, __("Host:","statcomm"),$tooltipFormat);

        if (empty($record))
        {
            $tooltipFormat = str_replace(self::C_FLAG,
                "(Id:" . $recId . ") " .   __("No Data for geolocation","statcomm"), $tooltipFormat);
            $tooltipFormat = str_replace(self::C_CONTINENT,"", $tooltipFormat);
            $tooltipFormat = str_replace(self::C_COUNTRY,"", $tooltipFormat);
            $tooltipFormat = str_replace(self::C_REGION, "", $tooltipFormat);
            $tooltipFormat = str_replace(self::C_CITY, "",$tooltipFormat);
            $tooltipFormat = str_replace(self::C_LAT, "?",$tooltipFormat);
            $tooltipFormat = str_replace(self::C_LONG, "?",$tooltipFormat);
            $tooltipFormat = str_replace(self::C_HOST_TITLE, __("Host:","statcomm"),$tooltipFormat);
            $tooltipFormat = str_replace(self::C_HOST, "?",$tooltipFormat);
            $tooltipFormat = str_replace(self::C_REC_ID, "",$tooltipFormat);
            return $tooltipFormat;
        }
        //Display flag
        $tooltipFormat = str_replace(self::C_FLAG,
            "(Id:" . $recId . ") " .   utilities::make_flag_icon($record, true), $tooltipFormat);

        //Display continent
        $tooltipFormat = str_replace(self::C_CONTINENT,
            $record->country_name . " (" . $record->continent_name . ")", $tooltipFormat);

        //Display country name
        $tooltipFormat = str_replace(self::C_COUNTRY,
            $record->country_name . " (" . $record->country_code . ")", $tooltipFormat);
        //Display Region
        $tooltipFormat = str_replace(self::C_REGION, $record->region_name, $tooltipFormat);
        //Display City
        $tooltipFormat = str_replace(self::C_CITY, $record->city,$tooltipFormat);

        //Display Latitude
        $tooltipFormat = str_replace(self::C_LAT, $record->latitude,$tooltipFormat);
        $tooltipFormat = str_replace(self::C_LONG, $record->longitude,$tooltipFormat);

        //Display Host Title and host
        $tooltipFormat = str_replace(self::C_HOST, $hostip,$tooltipFormat);

        //Replace Rec_Id
        $tooltipFormat = str_replace(self::C_REC_ID, $recId,$tooltipFormat);

        return $tooltipFormat;
    }

}