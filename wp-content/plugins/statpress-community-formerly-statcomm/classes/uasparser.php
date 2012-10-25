<?php
/**
 * PHP version 5
 *
 * @package    UASparser
 * @author     Jaroslav Mallat (http://mallat.cz/)
 * @copyright  Copyright (c) 2008 Jaroslav Mallat
 * @copyright  Copyright (c) 2010 Alex Stanev (http://stanev.org)
 * @copyright  Copyright (c) 2012 Martin van Wingerden (http://www.copernica.com)
 * @copyright  Copyright (c) 2012 Fernando Zorrilla de San Martin (http://wpgetready.com)
 * @version    0.6
 * @license    http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @link       http://user-agent-string.info/download/UASparser
 *
 *
 * 20120404: Considerations
 * - cache folder needs write configuration to work properly.
 * - Return info with following format:
 * Array
(
    [typ] => browser
    [ua_family] => Firefox
    [ua_name] => Firefox 3.0.8
    [ua_url] => http://www.mozilla.org/products/firefox/
    [ua_company] => Mozilla Foundation
    [ua_company_url] => http://www.mozilla.org/
    [ua_icon] => firefox.png
    [ua_info_url] => http://user-agent-string.info/list-of-ua/browser-detail?browser=Firefox
    [os_family] => Windows
    [os_name] => Windows XP
    [os_url] => http://www.microsoft.com/windowsxp/default.mspx
    [os_company] => Microsoft Corporation.
    [os_company_url] => http://www.microsoft.com/
    [os_icon] => windowsxp.png
)
 * 20120410: v1.6.31; disabled _downloaddata because the solution does not scale:
 * 			 a new solution is in development, to be launched in v1.6.4
 * 20120418: v1.6.5 Complete rewrite of _loadData and _downloadData
 *                 Replaced by _masterDownload
 *                 Advantages:
 *                      -Much improved control over download process.
 *                      -Use of HTTP API
 *                      -Documented process
 *                      -Using a new service providing more reliability than user-agent-string.info
 *                      and avoiding blacklisting IP.
 *                      -Zip process file, means even faster download.
 * To do: - Control Panel
 *        - Ajax control
 *        - Options control.
 * 20120509: v1.6.8: Deprecated _loadData in favor manual updating.
 * 20120513: v1.6.81:fix activating the feature.
 **/

 // view source this file and exit
//if ($_GET['source'] == "y") {     show_source(__FILE__);     exit; }


abstract class UASparser
{
    //private static $_zip_url    =   'http://statcomm.wpgetready.com/statservice/getfile';
    //private static $_ver_url    =   'http://statcomm.wpgetready.com/statservice/version';
    //private static $_md5_url    =   'http://statcomm.wpgetready.com/statservice/md5';
    private static $_info_url   =   'http://user-agent-string.info';
    //private static $TIMEOUT     = 3; //general timeout for operations.
    //private static $plugin_version =   "v1.6.70";

    private $_cache_dir         =   null;
    private $_data              =   null;

    /**
     *  Constructor with an optional cache directory
     * @param null $cacheDirectory base directory
     * @internal param \cache $string directory to be used by this instance
     */
    public function __construct($cacheDirectory = null) {
        if ($cacheDirectory) $this->SetCacheDir($cacheDirectory);
        //$options=settingsAPI::getOptions();
    }

    /**
     *  Parse the useragent string if given otherwise parse the current user agent
     * @param null $useragent
     * @return array
     */
    public function Parse($useragent = null) {
       // fl("backtrace:", var_dump(debug_backtrace()));
        // intialize some variables
        $browser_id = $os_id = null;
        $result = array();

        // initialize the return value
        $result['typ']              = 'unknown';
        $result['ua_family']        = 'unknown';
        $result['ua_name']          = 'unknown';
        $result['ua_version']       = 'unknown';
        $result['ua_url']           = 'unknown';
        $result['ua_company']       = 'unknown';
        $result['ua_company_url']   = 'unknown';
        $result['ua_icon']          = 'unknown.png';
        $result["ua_info_url"]      = 'unknown';
        $result["os_family"]        = 'unknown';
        $result["os_name"]          = 'unknown';
        $result["os_url"]           = 'unknown';
        $result["os_company"]       = 'unknown';
        $result["os_company_url"]   = 'unknown';
        $result["os_icon"]          = 'unknown.png';

        // if no user agent is supplied process the one from the server vars
        if (!isset($useragent) && isset($_SERVER['HTTP_USER_AGENT'])){
            $useragent = $_SERVER['HTTP_USER_AGENT'];
        }

        // if we haven't loaded the data yet, do it now

        if(!$this->_data) {
            $this->_data = $this->_loadData();
        }

        // we have no data or no valid user agent, just return the default data
        if(!$this->_data || !isset($useragent)) {
            return $result;
        }

        // crawler
        foreach ($this->_data['robots'] as $test) {
            if ($test[0] == $useragent) {
                $result['typ']                            = 'Robot';
                if ($test[1]) $result['ua_family']        = $test[1];
                if ($test[2]) $result['ua_name']          = $test[2];
                if ($test[3]) $result['ua_url']           = $test[3];
                if ($test[4]) $result['ua_company']       = $test[4];
                if ($test[5]) $result['ua_company_url']   = $test[5];
                if ($test[6]) $result['ua_icon']          = $test[6];
                if ($test[7]) { // OS set
                    $os_data = $this->_data['os'][$test[7]];
                    if ($os_data[0]) $result['os_family']       =   $os_data[0];
                    if ($os_data[1]) $result['os_name']         =   $os_data[1];
                    if ($os_data[2]) $result['os_url']          =   $os_data[2];
                    if ($os_data[3]) $result['os_company']      =   $os_data[3];
                    if ($os_data[4]) $result['os_company_url']  =   $os_data[4];
                    if ($os_data[5]) $result['os_icon']         =   $os_data[5];
                }
                if ($test[8]) $result['ua_info_url']      = self::$_info_url.$test[8];
                return $result;
            }
        }

        // find a browser based on the regex
        foreach ($this->_data['browser_reg'] as $test) {
            if (@preg_match($test[0],$useragent,$info)) { // $info may contain version
                $browser_id = $test[1];
                break;
            }
        }

        // a valid browser was found
        if ($browser_id) { // browser detail
            $browser_data = $this->_data['browser'][$browser_id];
            if ($this->_data['browser_type'][$browser_data[0]][0]) $result['typ']    = $this->_data['browser_type'][$browser_data[0]][0];
            if (isset($info[1]))    $result['ua_version']     = $info[1];
            if ($browser_data[1])   $result['ua_family']      = $browser_data[1];
            if ($browser_data[1])   $result['ua_name']        = $browser_data[1].(isset($info[1]) ? ' '.$info[1] : '');
            if ($browser_data[2])   $result['ua_url']         = $browser_data[2];
            if ($browser_data[3])   $result['ua_company']     = $browser_data[3];
            if ($browser_data[4])   $result['ua_company_url'] = $browser_data[4];
            if ($browser_data[5])   $result['ua_icon']        = $browser_data[5];
            if ($browser_data[6])   $result['ua_info_url']    = self::$_info_url.$browser_data[6];
        }

        // browser OS, does this browser match contain a reference to an os?
        if (isset($this->_data['browser_os'][$browser_id])) { // os detail
            $os_id = $this->_data['browser_os'][$browser_id][0]; // Get the os id
            $os_data = $this->_data['os'][$os_id];
            if ($os_data[0])    $result['os_family']      = $os_data[0];
            if ($os_data[1])    $result['os_name']        = $os_data[1];
            if ($os_data[2])    $result['os_url']         = $os_data[2];
            if ($os_data[3])    $result['os_company']     = $os_data[3];
            if ($os_data[4])    $result['os_company_url'] = $os_data[4];
            if ($os_data[5])    $result['os_icon']        = $os_data[5];
            return $result;
        }

        // search for the os
        foreach ($this->_data['os_reg'] as $test) {
            if (@preg_match($test[0],$useragent)) {
                $os_id = $test[1];
                break;
            }
        }

        // a valid os was found
        if ($os_id) { // os detail
            $os_data = $this->_data['os'][$os_id];
            if ($os_data[0]) $result['os_family']       = $os_data[0];
            if ($os_data[1]) $result['os_name']         = $os_data[1];
            if ($os_data[2]) $result['os_url']          = $os_data[2];
            if ($os_data[3]) $result['os_company']      = $os_data[3];
            if ($os_data[4]) $result['os_company_url']  = $os_data[4];
            if ($os_data[5]) $result['os_icon']         = $os_data[5];
        }
        return $result;
    }

    /**
     * Load the file, if we need to donwload data delegate the task to _masterDownload
     * @return array|string
     */
    private function _loadData(){
        $PARSER_FILE  = $this->_cache_dir .'/uasdata.ini';

        if (file_exists($PARSER_FILE))
        {
            return @parse_ini_file($PARSER_FILE,true);
        }
        else
        {
            utilities::fl("there was an error parsing the uasdata.ini file");
        }
        return '';
    }

    /**
     *  Set the cache directory
     *  @param string
     */
    public function SetCacheDir($cache_dir) {

        // perform some extra checks
        if (!is_writable($cache_dir) || !is_dir($cache_dir)){
            trigger_error('ERROR: Cache dir('.$cache_dir.') is not a directory or not writable');
            return;
        }

        // store the cache dir
        $cache_dir = realpath($cache_dir);
        $this->_cache_dir = $cache_dir;
    }
}
?>