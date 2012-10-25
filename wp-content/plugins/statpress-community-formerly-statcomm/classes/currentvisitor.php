<?php
/*
 * Represent the current visitor in the site
 */


class statcommCurrentUser
{
    //			"(date,time,ip,urlrequested,agent,referrer,search,nation,os,browser,searchengine,spider,feed,user,
    //threat_score,threat_type,timestamp,statuscode) " .

    private $_date;
    private $_time;
    private $_ip;
    private $_urlrequested;
    private $_agent;
    private $_referrer;
    private $_search;
    private $_nation;
    private $_os;
    private $_browser;
    private $_searchengine;
    private $_spider;
    private $_feed;
    private $_user;
    private $_threat_score;
    private $_threat_type;
    private $_timestamp;
    private $_language;
    private $_statuscode;
    private $_userAgent;
    private $_geoLocation;


    public function setAgent($agent)
    {
        
        $this->_agent = $agent;
    }

    public function getAgent()
    {
        return $this->_agent;
    }

    public function setBrowser($browser)
    {
        $this->_browser = $browser;
    }

    public function getBrowser()
    {
        return $this->_browser;
    }

    public function setDate($date)
    {
        $this->_date = $date;
    }

    public function getDate()
    {
        return $this->_date;
    }

    public function setFeed($feed)
    {
        $this->_feed = $feed;
    }

    public function getFeed()
    {
        return $this->_feed;
    }

    public function setIp($ip)
    {
        $this->_ip = $ip;
    }

    public function getIp()
    {
        return $this->_ip;
    }

    public function setNation($nation)
    {
        $this->_nation = $nation;
    }

    public function getNation()
    {
        return $this->_nation;
    }

    public function setOs($os)
    {
        $this->_os = $os;
    }

    public function getOs()
    {
        return $this->_os;
    }

    public function setReferrer($referrer)
    {
        $this->_referrer = $referrer;
    }

    public function getReferrer()
    {
        return $this->_referrer;
    }

    public function setSearch($search)
    {
        $this->_search = $search;
    }

    public function getSearch()
    {
        return $this->_search;
    }

    public function setSearchengine($searchengine)
    {
        $this->_searchengine = $searchengine;
    }

    public function getSearchengine()
    {
        return $this->_searchengine;
    }

    public function setSpider($spider)
    {
        $this->_spider = $spider;
    }

    public function getSpider()
    {
        return $this->_spider;
    }

    public function setStatuscode($statuscode)
    {
        $this->_statuscode = $statuscode;
    }

    public function getStatuscode()
    {
        return $this->_statuscode;
    }

    public function setThreatScore($threat_score)
    {
        $this->_threat_score = $threat_score;
    }

    public function getThreatScore()
    {
        return $this->_threat_score;
    }

    public function setThreatType($threat_type)
    {
        $this->_threat_type = $threat_type;
    }

    public function getThreatType()
    {
        return $this->_threat_type;
    }

    public function setTime($time)
    {
        $this->_time = $time;
    }

    public function getTime()
    {
        return $this->_time;
    }

    public function setTimestamp($timestamp)
    {
        $this->_timestamp = $timestamp;
    }


    public function getTimestamp()
    {
        return $this->_timestamp;
    }

    public function setLanguage($language)
    {
        $this->_language = $language;
    }

    public function getLanguage()
    {
        return $this->_language;
    }

    public function setUrlrequested($urlrequested)
    {
        $this->_urlrequested = $urlrequested;
    }

    public function getUrlrequested()
    {
        return $this->_urlrequested;
    }

    public function setUser($user)
    {
        $this->_user = $user;
    }

    public function getUser()
    {
        return $this->_user;
    }

    public function setUserAgent($userAgent)
    {
        $this->_userAgent = $userAgent;
    }

    public function getUserAgent()
    {
        return $this->_userAgent;
    }

    public function setGeoLocation($geoLocation)
    {
        $this->_geoLocation = $geoLocation;
    }

    public function getGeoLocation()
    {
        return $this->_geoLocation;
    }
}

?>