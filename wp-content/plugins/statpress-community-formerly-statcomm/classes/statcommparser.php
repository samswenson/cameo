<?php
/**
 * stacommParser extends the original class with the purpose
 * of modify it as less as possible
 *
 * What do we introduce it?
 * -revamped constructor to make additional data for icons url
 * -proper url locations to the icons for operative system and user agent
 * -the original class UASparser was marked as abstract. In this way, is not possible to instantiate. Be aware.
 * - 20120408: CHANGED: initialization directory include in the extends class, for sake of simplification.
 * the idea is to extend but not modify any longer
 *Example:
 *array
  'typ' => string 'Browser'
  'ua_family' => string 'Chrome'
  'ua_name' => string 'Chrome 16.0.912.63'
  'ua_version' => string '16.0.912.63'
  'ua_url' => string 'http://www.google.com/chrome'
  'ua_company' => string 'Google Inc.'
  'ua_company_url' => string 'http://www.google.com/'
  'ua_icon' => string 'http://localhost/wpr2012/wp-content/plugins/statcomm/images/uas/chrome.png'
  'ua_info_url' => string 'http://user-agent-string.info/list-of-ua/browser-detail?browser=Chrome'
  'os_family' => string 'Windows'
  'os_name' => string 'Windows 7'
  'os_url' => string 'http://en.wikipedia.org/wiki/Windows_7'
  'os_company' => string 'Microsoft Corporation.'
  'os_company_url' => string 'http://www.microsoft.com/'
  'os_icon' => string 'http://localhost/wpr2012/wp-content/plugins/statcomm/images/so/windows-7.png'
 */

class statcommParser extends UASparser
{
	//private $_uaDir = "";
	//private $_soDir ="";
//overriding constructor
/*
1.6.4: Improved constructor. This should solve problems due incorrect constant definitions in some WP installations
*/
    public function __construct($cacheDirectory = null) {
    	if ($cacheDirectory == null)
    	{
    		$cacheDirectory = plugin_dir_path(dirname(__FILE__)) . 'def';
    	}
		parent::__construct($cacheDirectory);
		//since the classes are one lower level, I use two dirnames
		//$this->_soDir = WP_PLUGIN_URL . '/' . dirname(dirname (plugin_basename(__FILE__))) . '/images/so/';
		//$this->_soDir = dirname(plugin_dir_url(__FILE__)) . '/images/so/';
		//$this->_uaDir = WP_PLUGIN_URL . '/' . dirname(dirname (plugin_basename(__FILE__))) . '/images/uas/';
		//$this->_uaDir = dirname(plugin_dir_url(__FILE__)) . '/images/uas/';
     }
//overriding parse method
/**
 * Parse the User Agent String
 * v1.6.70: convert operating system and browser to sprite.
 * Replaced strange character as !
 * @param null $useragent
 * @return array
 */
	public function Parse($useragent = null) {
		//Call the original (avoid Vampires Diaries...spooky)
		$result=parent::Parse($useragent);
		//change icons for correspondent urls.
		//$result['ua_icon'] = $this->_uaDir . $result['ua_icon'] ;
        $uas= str_replace(".","-" ,strtolower($result['ua_icon']));
        $uas=str_replace("!","" ,$uas);
		$result['ua_icon'] = "uas-$uas";

		//$result['os_icon'] = $this->_soDir . $result['os_icon'];
        $os= str_replace(".","-" ,strtolower( $result['os_icon']));
        $os=str_replace("!","" ,$os);
		$result['os_icon'] = "os-$os";
		return $result;
	}

	/**
	 * Used to back-cover: invalid or undefined declaration will be error raised
	 */
	function __get ($property)
	{
		trigger_error("propert <strong>$property</strong> doesn't exist", E_USER_ERROR);
	}

	/*
	 * Used to back-cover: invalid or undefined assignation will be error raised
	 */
	function __set ($property,$value)
	{
		trigger_error("property <strong>$property</strong> doesn't exist", E_USER_ERROR);
	}
}
?>