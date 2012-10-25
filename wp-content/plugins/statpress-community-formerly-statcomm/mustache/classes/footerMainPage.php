<?php
class footerMainPage extends statcommMustache
{

    public function templateName() { return "footerMainPage";}

    public function databaseTableSize() { return __('Database Table size:', 'statcomm'); }
    public function databaseTableSizeValue() { return $this->iritablesize(); }

    public function queriesExecuted()   { return __('Queries executed:', 'statcomm'); }
    public function queriesExecutedValue()   {
        $qn= get_transient(viewSystem::QRY_TOTALS);
        if ($qn===false)
        {
            return _("(not measured)","statcomm");
        }
        return $qn;
    }

    public function queriesCached() { return __('Queries cached:', 'statcomm');}
    public function queriesCachedValue() {
        $qc= get_transient(viewSystem::QRY_CACHED);
        if ($qc===false)
        {
            return _("(not measured)","statcomm");
        }
        return $qc;
    }

    public function rss2() {return __('RSS2 url:', 'statcomm');}
    public function rss2Url() {return get_bloginfo('rss2_url');}

    public function atom() { return  __('ATOM url:', 'statcomm');}
    public function atomUrl() { return  get_bloginfo('atom_url');}

    public function rss() {return __('RSS url:', 'statcomm');}
    public function rssUrl() {return  get_bloginfo('rss_url');}

    public function commentRss2() {return __('Comment RSS2 url:', 'statcomm');}
    public function commentRss2Url() {return  get_bloginfo('comments_rss2_url');}

    public function commentAtom() {return __('Comment ATOM url:', 'statcomm');}
    public function commentAtomUrl() {return  get_bloginfo('comments_atom_url');}

    public function processTime() {return __('Process time:','statcomm)'); }
    public function processTime2() {return __('seconds','statcomm'); }

    public  function timer()
    {
        $time=get_transient(viewSystem::TOTAL_TIME);
        if ($time === false)
        {
            return _("(not measured)","statcomm");
        }
        return $time;
    }

    /**
     * Return table size and record number
     * 20120408 1.6.3:Improved
     * @return string
     */
    private function iritablesize()
    {
        $data_length=0;
        $data_rows=0;
        $res = mySql::get_results(mySql::QRY_tableSize);
        foreach ($res as $fstatus)
        {
            $data_length = $fstatus->Data_length;
            $data_rows = $fstatus->Rows;
        }
        return number_format(($data_length / 1024 / 1024), 2, ",", " ") . " MB ($data_rows records)";
    }

    function extractFeedReq($url)

    {
        if(!strpos($url, '?') === FALSE)
        {
            list($null, $q) = explode("?", $url);
            list($res, $null) = explode("&", $q);
        }
        else
        {
            $prsurl = parse_url($url);
            $path=(isset($prsurl['path'])? $prsurl['path']:'');
            $query=(isset($prsurl['query'])? $prsurl['query']:'');
            $res = $path . $query;
        }
        return $res;
    }

}