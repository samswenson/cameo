<?php

/* geoipcity.inc
 *
 * Copyright (C) 2004 Maxmind LLC
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307  USA
 */

/*
 * Changelog:
 *
 * 2005-01-13   Andrew Hill, Awarez Ltd. (http://www.awarez.net)
 *              Formatted file according to PEAR library standards.
 *              Changed inclusion of geoip.inc file to require_once, so that
 *                  this library can be used in the same script as geoip.inc.
 */
/*
 * 20120423: Converted to static class
 */

define("FULL_RECORD_LENGTH",50);

//require_once 'geoip.php'; added in the main include
//require_once 'geoipregionvars.php'; //included in utilities

/**
 * The funny thing is this class is defined and never used in the original code of Maxmind
 */
class geoiprecord {
  public $country_code;
  public $country_code3;
  public $country_name;
  public $region;
  public $region_name; //added to v1.6.60
  public $city;
  public $postal_code;
  public $latitude;
  public $longitude;
  public $area_code;
  public $dma_code;   # metro and dma code are the same. use metro_code
  public $metro_code;
  public $continent_code;
  public $continent_name; //added to v1.6.60
}

/**
 * This class is also not used(?)
 */
class geoipdnsrecord {
    public $country_code;
    public $country_code3;
    public $country_name;
    public $region;
    public $regionname;
    public $city;
    public $postal_code;
    public $latitude;
    public $longitude;
    public $areacode;
    public $dmacode;
    public $isp;
    public $org;
    public $metrocode;
}

class GeoIpCity_Ctrl
{
function getrecordwithdnsservice($str){
  $record = new geoipdnsrecord;
  $keyvalue = explode(";",$str);
  foreach ($keyvalue as $keyvalue2){
    list($key,$value) = explode("=",$keyvalue2);
    if ($key == "co"){
      $record->country_code = $value;
    }
    if ($key == "ci"){
      $record->city = $value;
    }
    if ($key == "re"){
      $record->region = $value;
    }
    if ($key == "ac"){
      $record->areacode = $value;
    }
    if ($key == "dm" || $key == "me" ){
      $record->dmacode   = $value;
      $record->metrocode = $value;
    }
    if ($key == "is"){
      $record->isp = $value;
    }
    if ($key == "or"){
      $record->org = $value;
    }
    if ($key == "zi"){
      $record->postal_code = $value;
    }
    if ($key == "la"){
      $record->latitude = $value;
    }
    if ($key == "lo"){
      $record->longitude = $value;
    }
  }
  $number = $GLOBALS['GEOIP_COUNTRY_CODE_TO_NUMBER'][$record->country_code];
  $record->country_code3 = $GLOBALS['GEOIP_COUNTRY_CODES3'][$number];
  $record->country_name = $GLOBALS['GEOIP_COUNTRY_NAMES'][$number];
  if ($record->region != "") {
    if (($record->country_code == "US") || ($record->country_code == "CA")){
      $record->regionname = $GLOBALS['ISO'][$record->country_code][$record->region];
    } else {
      $record->regionname = $GLOBALS['FIPS'][$record->country_code][$record->region];
    }
  }
  return $record;
}

function _get_record_v6($gi,$ipnum){
  $seek_country = GeoIP_Ctlr::_geoip_seek_country_v6($gi,$ipnum);
  if ($seek_country == $gi->databaseSegments) {
    return NULL;
  }
  return GeoIpCity_Ctrl::_common_get_record($gi, $seek_country);
}

function _common_get_record($gi, $seek_country){
  // workaround php's broken substr, strpos, etc handling with
  // mbstring.func_overload and mbstring.internal_encoding
  $enc = mb_internal_encoding();
  mb_internal_encoding('ISO-8859-1'); 

  $record_pointer = $seek_country + (2 * $gi->record_length - 1) * $gi->databaseSegments;
  
  if ($gi->flags & GeoIP_Ctlr::GEOIP_MEMORY_CACHE) {
    $record_buf = substr($gi->memory_buffer,$record_pointer,FULL_RECORD_LENGTH);
  } elseif ($gi->flags & GeoIP_Ctlr::GEOIP_SHARED_MEMORY){
    $record_buf = @shmop_read($gi->shmid,$record_pointer,FULL_RECORD_LENGTH);
  } else {
    fseek($gi->filehandle, $record_pointer, SEEK_SET);
    $record_buf = fread($gi->filehandle,FULL_RECORD_LENGTH);
  }
  $record = new geoiprecord;
  $record_buf_pos = 0;
  $char = ord(substr($record_buf,$record_buf_pos,1));
    $record->country_code = $gi->GEOIP_COUNTRY_CODES[$char];
    $record->country_code3 = $gi->GEOIP_COUNTRY_CODES3[$char];
    $record->country_name = $gi->GEOIP_COUNTRY_NAMES[$char];
  $record->continent_code = $gi->GEOIP_CONTINENT_CODES[$char];
  $record_buf_pos++;
  $str_length = 0;
    // Get region
  $char = ord(substr($record_buf,$record_buf_pos+$str_length,1));
  while ($char != 0){
    $str_length++;
    $char = ord(substr($record_buf,$record_buf_pos+$str_length,1));
  }
  if ($str_length > 0){
    $record->region = substr($record_buf,$record_buf_pos,$str_length);
  }
  $record_buf_pos += $str_length + 1;
  $str_length = 0;
    // Get city
  $char = ord(substr($record_buf,$record_buf_pos+$str_length,1));
  while ($char != 0){
    $str_length++;
    $char = ord(substr($record_buf,$record_buf_pos+$str_length,1));
  }
  if ($str_length > 0){
    $record->city = substr($record_buf,$record_buf_pos,$str_length);
  }
  $record_buf_pos += $str_length + 1;
  $str_length = 0;
    // Get postal code
  $char = ord(substr($record_buf,$record_buf_pos+$str_length,1));
  while ($char != 0){
    $str_length++;
    $char = ord(substr($record_buf,$record_buf_pos+$str_length,1));
  }
  if ($str_length > 0){
    $record->postal_code = substr($record_buf,$record_buf_pos,$str_length);
  }
  $record_buf_pos += $str_length + 1;
  $str_length = 0;
    // Get latitude and longitude
  $latitude = 0;
  $longitude = 0;
  for ($j = 0;$j < 3; ++$j){
    $char = ord(substr($record_buf,$record_buf_pos++,1));
    $latitude += ($char << ($j * 8));
  }
  $record->latitude = ($latitude/10000) - 180;
  for ($j = 0;$j < 3; ++$j){
    $char = ord(substr($record_buf,$record_buf_pos++,1));
    $longitude += ($char << ($j * 8));
  }
  $record->longitude = ($longitude/10000) - 180;
  if (GeoIP_Ctlr::GEOIP_CITY_EDITION_REV1 == $gi->databaseType){
    $metroarea_combo = 0;
    if ($record->country_code == "US"){
      for ($j = 0;$j < 3;++$j){
        $char = ord(substr($record_buf,$record_buf_pos++,1));
        $metroarea_combo += ($char << ($j * 8));
      }
      $record->metro_code = $record->dma_code = floor($metroarea_combo/1000);
      $record->area_code = $metroarea_combo%1000;
    }
  }
  mb_internal_encoding($enc);
  return $record;
}

function GeoIP_record_by_addr_v6 ($gi,$addr){
  if ($addr == NULL){
     return 0;
  }
  $ipnum = inet_pton($addr);
  return GeoIpCity_Ctrl::_get_record_v6($gi, $ipnum);
}

function _get_record($gi,$ipnum){
  $seek_country =  GeoIP_Ctlr::_geoip_seek_country($gi,$ipnum);
  if ($seek_country == $gi->databaseSegments) {
    return NULL;
  }
  return GeoIpCity_Ctrl::_common_get_record($gi, $seek_country);
}

static function GeoIP_record_by_addr ($gi,$addr){
  if ($addr == NULL){
     return null;
  }
  $ipnum = ip2long($addr);
  return GeoIpCity_Ctrl::_get_record($gi, $ipnum);
}

/**
 * v1.6.60: Additional functionality toward getting more info.
 * v1.6.90: Improved checking
 * v1.7.40: Added exception control. Added return if gi is null
 */
static function GeoIP_info_by_addr($gi,$addr)
{
    //return object geoiprecord type
    try
    {
        if (empty($gi)) {return NULL;}
        $record = GeoIpCity_Ctrl::GeoIP_record_by_addr($gi,$addr);
    }
    catch (Exception $e)
    {
        utilities::fl("Unexpected error trying to get data from Maxmind:" . $e);
        return null;
    }
    if (empty($record))
        return null;
    //Improve type
    $record->country_code  =isset($record->country_code)?$record->country_code:"";
    $record->country_code3 =isset($record->country_code3)?$record->country_code3:"";
    $record->country_name  =isset($record->country_name)?$record->country_name:"";
    $record->region        =isset($record->region)?$record->region:"";
    $record->region_name="";
    if(isset($record->country_code) && isset($record->region))
    {
        if (isset(GeoIpRegionVars::$GEOIP_REGION_NAME[$record->country_code][$record->region]))
        {
            $record->region_name=GeoIpRegionVars::$GEOIP_REGION_NAME[$record->country_code][$record->region];
            $record->region_name= htmlentities($record->region_name);
        }
    }
    $record->city          =isset($record->city)?$record->city:"";
    $record->city          = htmlentities($record->city);
    $record->postal_code   =isset($record->postal_code)?$record->postal_code:"";
    $record->latitude      =isset($record->latitude)?$record->latitude:"0";
    $record->longitude     =isset($record->longitude)?$record->longitude:"0";
    $record->area_code     =isset($record->area_code)?$record->area_code:"0";
    $record->dma_code      =isset($record->dma_code)?$record->dma_code:"0";
    $record->metro_code    =isset($record->metro_code)?$record->metro_code:"0";
    $record->continent_code=isset($record->continent_code)?$record->continent_code:"";
    $record->continent_name=isset($record->continent_code)?GeoIpCity_Ctrl::get_continent_name($record->continent_code):"";

//See http://www.geekality.net/2011/08/21/country-names-continent-names-and-iso-3166-codes-for-mysql/
//http://cloford.com/resources/codes/index.htm
//http://stackoverflow.com/questions/713671/best-way-to-store-country-codes-names-and-continent-in-java
//http://www.iso.org/iso/country_codes/iso_3166_code_lists.htm
    return $record;
}

static function get_continent_name($continent_code)
{
    $continents = array ('AF' => __('Africa','statcomm'),
                         'AS' => __('Asia','statcomm'),
                         'EU' => __('Europe', 'statcomm'),
                         'NA' => __('North America','statcomm'),
                         'SA' => __('South America','statcomm'),
                         'OC' => __('Oceania','statcomm'),
                         'AN' => __('Antartica','statcomm')
        );
    if (array_key_exists($continent_code,$continents))
    {
        return $continents[$continent_code];
    }
    return "---";
}

}

?>
