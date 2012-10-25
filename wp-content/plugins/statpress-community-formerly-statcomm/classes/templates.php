<?php
/**
 * This is a complement for Mustache template including:
 * - template loading, html caching, Queries counter, Queries cached counter
 * 1.7.30: We expanded to be very easy to use and in this way it can reused for subplugins.
 */

class viewSystem
{
    const QRY_TOTALS="statcomm_qrytotals";
    const QRY_CACHED="statcomm_cachedtotals";
    const TOTAL_TIME="statcomm_totaltime";
    const MAX_TRANSIENT_TIME = 60; //It is unlikely result needs more time thant this

    private $view;
    private $viewPath;

    /**
     * Path: rooth path for xml file, classes and templates, ending with '/'
     * Viewname: file xml where the view is described (WITHOUT xml extension)
     * @param $path
     * @param $viewName
     */
    public function __construct($path,$viewName)
    {
       $filename = $path . $viewName. ".xml";
       //Error if not exists. TODO: IMPROVE use custom Exception
        if (!file_exists($filename))
        {
            trigger_error(sprintf(__("view %s missing","statcomm"),$viewName));
        }
        $this->view=$filename ;
        $this->viewPath=$path;
    }

    /**
     * Loops into the view file and render the views.
     */
    public function render()
    {
        $timer= utilities::startTimer();
        $currenTimer= utilities::startTimer();
        $qryTotals=0;
        $cachedTotals=0;
        $totalTime =0;
        //TODO: catch error here.
        $xml = simplexml_load_file($this->view);
        $results=array();
        foreach($xml->children() as $child)
        {
            $row=array();
            switch ($child->getName()) {
                case "module":
                    //load module, instanciate and render it.
                    $obj= $this->runModule((string)$child);
                    $obj->render($this->viewPath);
                    break;
                default:
                    //Ignore it for now
                    break;
            }
            //We are assumming that the object is always statcommMustache, so those methods are always present
            $row['module'] = (string)$child;
            $row['time']   = $obj->getTimer();
            $row['queries']   = $obj->getQryCounter();
            $row['cached']   = $obj->getQryCached();

            $qryTotals += $obj->getQryCounter();
            $cachedTotals += $obj->getQryCached();
            $totalTime += utilities::stopTimer($currenTimer);
            $currenTimer = utilities::startTimer(); //start measuring next module
            $results[] = $row;
            //Save current data to transient. This is can used by any module
            //in fact is used by footermainpage. Also, using transient allows to dispose temporary data.
            set_transient( self::QRY_TOTALS,$qryTotals,60);
            set_transient( self::QRY_CACHED,$cachedTotals,60);
            set_transient( self::TOTAL_TIME,$totalTime,60);
        }

        $row=array();
        $row['module'] = 'Totals';
        $row['time']   = utilities::stopTimer($timer);
        $row['queries']   = $qryTotals;
        $row['cached']   = $cachedTotals;

        $results[] = $row;
        //This special module gather all the statistics as last step
        //Enable module only if debugging is on.
        if (utilities::FILE_LOG_DEBUG)
        {
            $lastModule = $this->runModule("statsModule",$results);
            $lastModule->render($this->viewPath);
        }
    }

    /**
     * Load module (if exists) and render it. Filename should match class name
     * @param $child
     * @param null $parameters
     * @return object
     * TODO: Custom exception handler.
     */
    private function runModule($child, $parameters = null)
    {
        $timer= utilities::startTimer();
        $timeModules="";

        $filename =$this->viewPath. "classes/$child.php";
        if (!file_exists($filename))
        {
            trigger_error(sprintf(__("class %s missing","statcomm"),$child));
        }
        require_once($filename);
        if (!class_exists($child))
        {
            trigger_error(sprintf(__("couldn't find class inside file %s","statcomm"),$child));
        }
        $reflex = new ReflectionClass($child);
        //We check if we pass parameters to the class. If not just create an instance.
        if (empty($parameters))
        {
            $view = $reflex->newInstance();
        }
        else
        {
            //If class needs parameters, send the array when object is instantiated
            $view = $reflex->newInstanceArgs(array($parameters));
        }
        return $view;
    }
}

    /**
     * This class handles loading template and merging with the respective class.
     * 20120724: It seems there is a bug related with PHP. Constructors calling mysql queries doesnt' increase
     * counters. This buggy behavior is under analysis.
     */
abstract class statcommMustache extends Mustache
{
    abstract function templateName(); //force to return template name
    private $timer;
    private $qryCounter; //These values stores and maintain how many queries were spent and cached
    private $qryCached;

    public function render($viewPath)
    {
        $t=$this->templateName();
        $filename = $viewPath. "templates/$t.mustache";
        if (!file_exists($filename)) //search for template in templates folder. TODO: use Custom Exception
        {
            trigger_error(sprintf(__("template %s missing","statcomm"),$t));
        }
        $templateContent=file_get_contents($filename);  //Get the template
        mySql::resetQryCounter();                       //Reset queries counter (and cached counter also)
        $timer=utilities::startTimer();                 //Start timing
        ob_start();                                     //Start caching
        echo parent::render($templateContent);          //Render the template
        ob_end_flush();                                 //Release results
        $this->qryCounter = mySql::qryCounter();        //store qry counter
        $this->qryCached = mySql::qryCounterCached();   //store cached counter
        $this->timer=utilities::stopTimer($timer);      //store time spent
    }

    public function getTimer()       {  return $this->timer; }
    public function getQryCached()   {  return $this->qryCached; }
    public function getQryCounter()  {  return $this->qryCounter; }
} //170