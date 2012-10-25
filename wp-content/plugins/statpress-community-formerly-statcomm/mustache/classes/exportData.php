<?php
class exportData extends statcommMustache
{
    public function templateName()  { return "exportData";}
    public function title()         { return __('Export stats to text file (csv)'   , 'statcomm');}
    public function from()          { return __('From'                              , 'statcomm');}
    public function to()            { return __('To'                                , 'statcomm');}
    public function format()        { return __('YYYYMMDD'                          , 'statcomm');}
    public function delimiters()    { return __('Fields delimiter'                  , 'statcomm');}
    public function export()        { return __('Export'                            , 'statcomm');}
}
