<?php
/**
 * Created by WpGetReady @2012
 * Author: Fernando Zorrilla de San Martin
 * Date: 16/07/12
 * Time: 02:14 PM
 * From version: 1.7.30
 * File: exportdatacsv.php
 */
class exportDataCsv
{
    /**
     * Renders the view.
     * @static
     * @param $path
     */
    static function statcommExport($path)
    {
        $v=new viewSystem($path, "exportdata");
        $v->render();
    }

    /**
     * Export the data
     * @static
     * FIXED: Very long dates, generate a database error memory exhausted.
     * TODO: Improve validation message instead saving an error.
     */
    static function exportNow()
    {
        @set_time_limit(0);
        $from =$_GET['from'];
        $to   =$_GET['to'];
        $filename = "statcomm_csv_" . $_GET['from'] . "-" . $_GET['to'] . ".csv";
        header('Content-Description: File Transfer');
        header("Content-Disposition: attachment; filename=$filename");
        header('Content-Type: text/plain charset=' . get_option('blog_charset'), true);

        if (!is_numeric($from)) { print (__("Invalid From Date","statcomm")); die(); }
        if (!is_numeric($to)) { print (__("Invalid To Date","statcomm")); die(); }

        $fromDay= (date("Ymd", strtotime(mb_substr ($from, 0, 8))));
        $lastDay= (date("Ymd", strtotime(mb_substr ($to, 0, 8)))) ;

        //Validation. We can do better than this...
        if (!migrationTable::dateValid($fromDay)) { print (__("Invalid From Date","statcomm")); die(); }
        if (!migrationTable::dateValid($lastDay)) { print (__("Invalid To Date","statcomm")); die();}
        if ($fromDay>$lastDay) {print (__("From Day needs to be set before Last Day!","statcomm")); die();}

        $columns="";
        $fields=mySql::getStatcommFields();           //Get all fields on the table
        $del =mb_substr ($_GET['del'], 0, 1);
        //Create the header for results. Another workaround: return array and to use join
        //Since that would imply use the same function in two different ways, we decide to stick to
        //don't get the code complex and to use the same function in the same way.

        foreach($fields as $f) {$columns .= $f->Field . $del ; }
        $columns = mb_strcut($columns,0,strlen($columns)-1) . "\n";
        print $columns;

        //Approach: make a loop day by day to get the data without stressing memory.
        //This workaround attempts to avoid memory errors when getting very long date ranges.
        $thisDay=$fromDay; //set starting date
        utilities::fl("this Day",$thisDay);
        while ($thisDay<=$lastDay)
        {
            //Get associative array of data
            $qry = mySql::get_results("SELECT * FROM #table# WHERE date='$thisDay'",null,ARRAY_A);
            foreach ($qry as $rk)
            {
                $row="";
                foreach($fields as $f)  {   $row .= '"' .  $rk[$f->Field] . '"' . $del; }
                $row = mb_strcut($row,0,strlen($row)-1) . "\n";
                print $row;
            }

            $thisDay=self::nextDay($thisDay);
        }
            die();
    }

    static function nextDay($thisDay)
    {
		$fDate =substr($thisDay,0,4 ) . "-" . substr($thisDay,4,2) . "-" . substr($thisDay,6,2);
        return date ("Ymd", strtotime("$fDate +1 day"));
    }
}//55,82,75,79,87
