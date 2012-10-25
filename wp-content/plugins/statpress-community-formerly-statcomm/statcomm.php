<?php
/*
   Plugin Name: StatComm (StatPress Community) Multisite Edition
   Plugin URI: http://www.wpgetready.com/downloads
   Description: Powerful statistics & traffic for your blog. With <strong>Instant Spy Tool + Google Maps</strong> , <strong>Error Tracking & Report</strong> , <strong>Custom Actions & Filters</strong>, <strong>subplugins</strong> and more!!!.
   Version: 1.7.41
   Author: WPGetReady
   Author URI: http://www.wpgetready.com

   Introduction: StatPress Community is a plugin for web statistics, based in their StatPress sucessors.

v1.7.41

* Fix MaxMind error when opening.
* Improved Report error now with correct graphics.

*/
     include(dirname(__FILE__). '/classes/mysql.php'); 			      //database interface class
     include(dirname(__FILE__). '/classes/exportdatacsv.php');        //export data process.
     include(dirname(__FILE__). '/classes/widget_toppost.php');       //widget class
     include(dirname(__FILE__). '/classes/widget_print.php'); 	      //widget class
     include(dirname(__FILE__). '/classes/uasparser.php'); 		      //parser class (abstract, do not modify)
     include(dirname(__FILE__). '/classes/statcommparser.php');       //parser class based on uasparser
     include(dirname(__FILE__). '/classes/settings.php'); 		      //settings class based on Settings API
	 include(dirname(__FILE__). '/classes/settingsnetwork.php'); 	  //settings class for network-wide settings.
	 include(dirname(__FILE__). '/classes/migrationsettings.php');    //settings class based for table migration
	 include(dirname(__FILE__). '/classes/migrationtable.php');       //migration table
	 include(dirname(__FILE__). '/classes/utilities.php'); 		      //common methods used in many classes.
     include(dirname(__FILE__). '/classes/geoipupdate.php'); 	      //GeoCityLite updater
     include(dirname(__FILE__). '/classes/statcommspy.php'); 	      //Statcomm Spy function  + Ajax
     include(dirname(__FILE__). '/classes/uaparserupdate.php');       //os & browser detection
     include(dirname(__FILE__). '/classes/languagedetection.php');    //language detection from visitor
     include(dirname(__FILE__). '/classes/currentvisitor.php'); 	  //common methods used in many classes.
     include(dirname(__FILE__). '/classes/cache.php'); 	              //Lazy cache system
	 include(dirname(__FILE__). '/classes/geoip.php'); 		          //Geolocation classes(I).
	 include(dirname(__FILE__). '/classes/geoipcity.php'); 		      //Geolocation classes(II).
	 include(dirname(__FILE__). '/classes/geoipregionvars.php'); 	  //Geolocation classes(III).
	 include(dirname(__FILE__). '/classes/mustache.php'); 	          //Template system (mustache without changes)
	 include(dirname(__FILE__). '/classes/templates.php'); 	          //Extended template system
     include(dirname(__FILE__). '/classes/subplugins.php'); 	      //Subplugin system
	 include(dirname(__FILE__). '/classes/wpsubpluginslisttable.php');//Extended table for subplugin system

  $SPCM= new statPressCommunity();

  class statPressCommunity
  {
	const ROBOT = 'Robot'; //type of agent that identifies a robot bot/crawler
    //Define directory where the agents data and search terms are located
	//Plugin constructor & initialization
    //v1.6.90: changed event sendheaders to template_redirect to catch errors. Check online documentation
    //(in a few days on this channell :) .Seriously.
    //v1.7.00: added control for update, since activation does not fire updates.
    //v1.7.20: added network-wide multisite options.
    private $settings;
    private $subplugins;
    private $subpNetMenu; //used to enabled subplugins in Network Menu
    private $viewPath;   //Default folder for system view based on Mustache

   function __construct()
   {


        add_action('init', 			        array(&$this,'loadTextDomain')); //add translation
        add_action('init',                  array(&$this,'subPluginsLoader'));

        add_action('admin_menu', 	        array(&$this,'addPages'));       //add menu pages
        add_action('template_redirect', 	array(&$this,'statcommAppend')); //activate append records

		add_action('admin_init',            'settingsMigrationAPI::statcomm_migration_admin_init'); //Settings API for migration

		add_action('admin_init',            array(&$this,'addDetailsStyle'));    //style initialization

       // register_activation_hook(__FILE__,  array(&$this, 'initTableAndOptions')); //Table creation and Options init
        //v1.7.00: used when upgrades for update plugin values and table
        add_action('plugins_loaded',        array(&$this, 'updateCheck'));

        add_action('plugins_loaded',        array (&$this, 'initSettings'));

        add_action('wp_ajax_sctooltip_action', array(&$this, 'statcommSpyTooltip') );//Action for tooltip

        //network menu for multisite.

        if (is_multisite())
        {
            utilities::fl("statcomm_network_page in effect");
            add_action('network_admin_menu' ,array (&$this, 'subPluginsNetworkMenu'));
        }

		$exportnow = (isset($_GET['statcomm_action']) ? $_GET['statcomm_action'] : '');
		if ($exportnow=='exportnow')
          {
              exportDataCsv::exportNow();
          }

       //$this->viewPath = plugin_dir_path(dirname(__FILE__)) . "mustache/";
       $this->viewPath = plugin_dir_path(__FILE__) . "mustache/";
   }




      function subPluginsNetworkMenu()
      {
        $this->subpNetMenu = new settingsNetwork();
        $this->subpNetMenu->statcomm_network_page();
      }

      function subPluginsLoader()
      {
          $this->subplugins= new subPlugins();
          $this->subplugins->subplugin_loader();
      }

      function initSettings()
      {
          $this->settings=new settingsAPI();
      }


  /**
   * Add pages to the main menu
   * 1.6.2: Updated menu to be compatible with WP 3.0
   * 1.6.5: Adding stylesheet to Details page
   */
  function addPages()
  {
      // add submenu
	  $options=settingsAPI::getOptions();
      $mincap = $options["cmb_capability"];
      if ($mincap == '')
      {
          $mincap = 'activate_plugins'; //admin by default minimum capability . Reference:http://codex.wordpress.org/Roles_and_Capabilities#Summary_of_Roles
      }

      $statcomm_style=add_menu_page('StatPress Community', 'StatComm', $mincap, "statComm",array(&$this, 'statcommMain'), plugins_url('images/statcomm-bw-16x16.png', __FILE__));
      add_submenu_page('statComm', __('Details'       ,'statcomm'), __('Details'       ,'statcomm'), $mincap, 'statComm/details',array (&$this, 'statcommDetails'));
	  $statcomm_spy  =add_submenu_page('statComm', __('Spy'           ,'statcomm'), __('Spy'           ,'statcomm'), $mincap, 'statComm/spy',array(&$this, 'statcommSpy'));
      add_submenu_page('statComm', __('Search'        ,'statcomm'), __('Search'        ,'statcomm'), $mincap, 'statComm/search', array(&$this, 'statcommSearch'));
      add_submenu_page('statComm', __('Export'        ,'statcomm'), __('Export'        ,'statcomm'), $mincap, 'statComm/export',array(&$this, 'statcommExport'));
      add_submenu_page('statComm', __('Options'       ,'statcomm'), __('Options'       ,'statcomm'), $mincap, 'statComm/options', array(&$this, 'statcommOptions'));
      add_submenu_page('statComm', __('User Agents'   ,'statcomm'), __('User Agents'   ,'statcomm'), $mincap, 'statComm/agents', array(&$this, 'statcommAgents'));
      add_submenu_page('statComm', __('HELP & Support','statcomm'), __('HELP & Support','statcomm'), $mincap, 'statComm/help', array(&$this, 'statcommHelp'));
//1.7.00
      add_submenu_page('statComm', __('Migration','statcomm'), __('Migration','statcomm'), $mincap, 'statComm/migration', array(&$this, 'statcommMigrationOptions'));

      //TESTING
      //add_submenu_page('statComm', __('Test summary create','statcomm'), __('Summary create','statcomm'), $mincap, 'statComm/summarycreate', array(&$this, 'statcommSummaryCreate'));
      //add_submenu_page('statComm', __('Test summary reset','statcomm'), __('Summary reset','statcomm'), $mincap, 'statComm/summaryreset', array(&$this, 'statcommSummaryReset'));


      add_action( 'admin_print_styles-' . $statcomm_style, 'statPressCommunity::statcommAdminStyle' );
      add_action( 'admin_print_styles-' . $statcomm_spy, 'statPressCommunity::statcommAdminStyle' );

      //Add js codes only for special pages
      add_action( 'admin_print_scripts-' . $statcomm_style, array(&$this,'statPressCommunity::jsScriptsPage' ));

      /*For testing purposes
      $statcomm_spytest=add_submenu_page('statComm', __('Spy Testing','statcomm'), __('Spy Testing','statcomm'), $mincap, 'statComm/testspy', array(&$this, 'statcommSpyTooltip'));
      add_action( 'admin_print_styles-' . $statcomm_spytest, array(&$this,'statcommAdminStyle' ));
        */

      //1.6.60:special page only accesible when update Geolitecity.dat
      add_submenu_page('statComm/options', __('Options'       ,'statcomm'), __('Options'       ,'statcomm'), $mincap, 'statComm/glcupdate', array(&$this, 'statcommGLCUpdate'));
      //1.6.80:special page only accesible when updating UAS parser
      add_submenu_page('statComm/options', __('UAS parser update'       ,'statcomm'), __('Options'       ,'statcomm'), $mincap, 'statComm/uasupdate', array(&$this, 'statcommUASUpdate'));
      //1.7.00:special page only accesible when migration table
      add_submenu_page('statComm/options', __('Statpress migration'       ,'statcomm'), __('Options'       ,'statcomm'), $mincap, 'statComm/statmigration', array(&$this, 'statcommMigration'));

  }

      /**
       * Fixed: javascript loaded in admin instead Statcomm page.
       */
      static function jsScriptsPage()
      {
          //Tooltip style. To keep functionality in one place, we decide to put this css with javascript
          $qtip = plugin_dir_url(__FILE__) . 'js/jquery.qtip.min.css';
          //Note: strictly speaking, we don't pass null, we should pass array(null). Don't it: if you do, the script won't work.
          wp_register_style( 'statcomm-tooltip', $qtip , null, '1.0', 'screen' );
          wp_enqueue_style( 'statcomm-tooltip' );

          //Javascript for tooltip. jquery automatic loading since it is needed.
          wp_enqueue_script('qtip', plugin_dir_url(__FILE__) . 'js/jquery.qtip.min.js', array('jquery'), false, true);
          //Javascript detection
          wp_enqueue_script('sc-qtip', plugin_dir_url(__FILE__) . 'js/sc-tooltip.js', array('qtip'), false, true);
          //v1.6.90 google maps
          wp_register_script('googlemaps', 'http://maps.google.com/maps/api/js?sensor=false', false, true);
          wp_enqueue_script('googlemaps');
      }

      function statcommSummaryReset()
      {
          $t=utilities::startTimer();
          mySql::resetSummaryTable();
          utilities::msg("Lazy cache reset in:" . utilities::stopTimer($t) . " seconds");
      }

    /**
     * Update page for Maxmind database updating
     */
    function statcommGLCUpdate()
    {
        utilities::fl("Begin updating Maxmind...");
        $gu= new geoliteUpdater();
        $gu->update(); //try to update
    }

      /**
       * Update page for UAS database updating
       */
    function statcommUASUpdate()
    {
        utilities::fl("Begin updating UAS database...");
        $gu= new uaparserUpdater();
        $gu->update(); //try to update
    }

      function statcommMigration()
      {
          //Before start stop capturing data
          remove_action('template_redirect', array(&$this,'statcommAppend')); //activate append records
          utilities::fl("Begin statpress migration data...");
          $mt=new migrationTable();
          $mt->startMigration();
          //Reactivate capturing data
          add_action('template_redirect', array(&$this,'statcommAppend')); //activate append records
      }

      /**
       * Update page for tooltip
       */
      function statcommSpyTooltip()
      {
        $id= isset($_GET['id'])?$_GET['id']:"";
        if ($id=="")
        {
            echo "<b>". __("Error: not IP provided","statcomm") ."</b>";
            die();
        }
            
        statcommSpy::makeTooltipMsg($id);
        die();
      }

  /*
   * 1.6.5:add custom stylesheet for Details Page ONLY.
   * This style is used for displaying flags properly
   * 1.7.10: moved common statcomm style to appropiate pages.
   */
  function addDetailsStyle()
  {
      /** Register */
      wp_register_style( 'statcomm-flags',      plugins_url('css/statcomm-flags.css', __FILE__), array());
      wp_register_style( 'statcomm-os',         plugins_url('css/statcomm-os.css',    __FILE__), array());
      wp_register_style( 'statcomm-uas',        plugins_url('css/statcomm-uas.css',   __FILE__), array());
      wp_register_style( 'statcomm-admin-style',plugins_url('css/statcomm.css',       __FILE__), array() );
  }

      /**
       * 1.6.5: Register custom style for proper flag into Details page.
       * 1.7.50: converted to static
       */
  static function statcommAdminStyle()
  {
      wp_enqueue_style('statcomm-flags');
      wp_enqueue_style('statcomm-os');
      wp_enqueue_style('statcomm-uas');
      wp_enqueue_style('statcomm-admin-style');
  }

    /**
     * Shows help.
     */
  function statcommHelp()
  {
  ?>
  <div class='wrap'><h2><?php _e('HELP', 'statcomm');?></h2><?php require_once("help-page/help.php"); ?></div>
  <?php
  }

  /**
  *  1.6.4:Improved Options using Settings API
  */
  function statcommOptions()
  {
     $this->settings->statcomm_option_page();
	return;
  }

  function statcommMigrationOptions()
  {
      settingsMigrationAPI::statcomm_migration_option_page();
      return;
  }
/**
 * Export data to CSV format.
 * To improve from 1.6.4
 */
      function statcommExport()
      {
          exportDataCsv::statcommExport($this->viewPath); //we don't need to be here any longer.
      }

/**
 * Constants definitions
 * Update: statcommMain organized in much more manageable modules. Much improved documentation.
 * 20120604: Query counter added
 * 20120606: Added caching for header and graphics.
 * 20120714: Improved view system to be more flexible.
 */
      function statcommMain()
      {
          $v=new viewSystem($this->viewPath, "statcomm");
          $v->render();
          return;
      }

		/**
		 * Provide statistical information about all visitors.
		 */
      function statcommDetails()
      {

          // Top days
          $this->iriValueTable("date", __('Top days', 'statcomm'), 5);

          // O.S.
          $this->iriValueTable("os", __('O.S.', 'statcomm'), 0, "", "", "AND feed='' AND spider='' AND os<>''");

          // Browser
          $this->iriValueTable("browser", __('Browser', 'statcomm'), 0, "", "", "AND feed='' AND spider='' AND browser<>''");

          // Feeds
          $this->iriValueTable("feed", __('Feeds', 'statcomm'), 5, "", "", "AND feed<>''");

          // SE
          $this->iriValueTable("searchengine", __('Search engines', 'statcomm'), 10, "", "", "AND searchengine<>''");

          // Search terms
          $this->iriValueTable("search", __('Top search terms', 'statcomm'), 20, "", "", "AND search<>''");

          // Top referrer
          $this->iriValueTable("referrer", __('Top referrer', 'statcomm'), 10, "", "", "AND referrer<>'' AND referrer NOT LIKE '%" . get_bloginfo('url') . "%'");

          // Countries
          $this->iriValueTable("nation", __('Countries (domains)', 'statcomm'), 10, "", "", "AND nation<>'' AND spider=''");

          // Spider
          $this->iriValueTable("spider", __('Spiders', 'statcomm'), 10, "", "", "AND spider<>''");

          // Top Pages
          $this->iriValueTable("urlrequested", __('Top pages', 'statcomm'), 5, "", "urlrequested", "AND feed='' and spider=''");


          // Top Days - Unique visitors
          $this->iriValueTable("date", __('Top Days - Unique visitors', 'statcomm'), 5, "distinct", "ip", "AND feed='' and spider=''");

          // Top Days - Pageviews
          $this->iriValueTable("date", __('Top Days - Pageviews', 'statcomm'), 5, "", "urlrequested", "AND feed='' and spider=''");

          // Top IPs - Pageviews
          $this->iriValueTable("ip", __('Top IPs - Pageviews', 'statcomm'), 5, "", "urlrequested", "AND feed='' and spider=''");
      }

	/**
	Main Spy functionality , to be improved.
	20120404: Performance problem detected mainly loop over hostip.info
	20120407:1.6.31 patch: bug when navigating pages. New approach is much more simple. Corrected.
	*/

      function statcommSpy()
      {
        statcommSpy::spy();
      }


      /**
       * Search inside the data.
       * to improve and simplify (A LOT!)
       */
      function statcommSearch()
      {

          global $wpdb;
  		  $table_name= $wpdb->prefix . "statcomm";

          $f['urlrequested'] = __('URL Requested', 'statcomm');
          $f['agent'] = __('Agent', 'statcomm');
          $f['referrer'] = __('Referrer', 'statcomm');
          $f['search'] = __('Search terms', 'statcomm');
          $f['searchengine'] = __('Search engine', 'statcomm');
          $f['os'] = __('Operative system', 'statcomm');
          $f['browser'] = __('Browser', 'statcomm');
          $f['spider'] = __('Spider', 'statcomm');
          $f['ip'] = __('IP', 'statcomm');
          $f['statuscode'] = __('Status Code','statcomm');
?>
  <div class='wrap'><h2><?php
          _e('Search', 'statcomm');
?></h2>
  <form method=get><table>
  <?php
          for ($i = 1; $i <= 3; $i++)
          {
              print "<tr>";
              print "<td>" . __('Field', 'statcomm') . " <select name=where$i><option value=''></option>";
              foreach (array_keys($f) as $k)
              {
                  print "<option value='$k'";
                  $w=isset($_GET["where$i"])?$_GET["where$i"]:"";
                  if ($w == $k)
                  {
                      print " SELECTED ";
                  }
                  print ">" . $f[$k] . "</option>";
              }
              $g=isset($_GET["groupby$i"])?$_GET["groupby$i"]:"";
              $s=isset($_GET["sortby$i"])?$_GET["sortby$i"]:"";
              $w=isset($_GET["what$i"])?$_GET["what$i"]:"";
              print "</select></td>";
              print "<td><input type=checkbox name=groupby$i value='checked' $g> " . __('Group by', 'statcomm') . "</td>";
              print "<td><input type=checkbox name=sortby$i value='checked' $s> " . __('Sort by', 'statcomm') . "</td>";
              print "<td>, " . __('if contains', 'statcomm') . " <input type=text name=what$i value='$w'></td>";
              print "</tr>";
          }
?>
  </table>
  <br>
  <table>
  <tr>
    <td>
      <table>
        <tr><td><input type=checkbox name=oderbycount value=checked <?php
          $o=isset($_GET['oderbycount'])?$_GET['oderbycount']:"";
          print $o;
?>> <?php
          _e('sort by count if grouped', 'statcomm');
?></td></tr>
        <tr><td><input type=checkbox name=spider value=checked <?php
          $sp=isset($_GET['oderbycount'])?$_GET['oderbycount']:"";
          print $sp;
?>> <?php
          _e('include spiders/crawlers/bot', 'statcomm');
?></td></tr>
        <tr><td><input type=checkbox name=feed value=checked <?php
          $f=isset($_GET['feed'])?$_GET['feed']:"";
          print $f;
?>> <?php
          _e('include feed', 'statcomm');
?></td></tr>
<tr><td><input type=checkbox name=distinct value=checked <?php
		  $d=isset($_GET['distinct'])?$_GET['distinct']:"";
          print $d
?>> <?php
          _e('SELECT DISTINCT', 'statcomm');
?></td></tr>
      </table>
    </td>
    <td width=15> </td>
    <td>
      <table>
        <tr>
          <td><?php
          _e('Limit results to', 'statcomm');
?>
            <select name=limitquery><?php
		  $l=isset($_GET['limitquery'])?$_GET['limitquery']:"";
          if ($l > 0)
          {
              print "<option>" . $l . "</option>";
          }
?><option>1</option><option>5</option><option>10</option><option>20</option><option>50</option><option>100</option><option>250</option><option>500</option></select>
          </td>
        </tr>
        <tr><td>&nbsp;</td></tr>
        <tr>
          <td align=right><input type=submit value=<?php
          _e('Search', 'statcomm');
?> name=searchsubmit></td>
        </tr>
      </table>
    </td>
  </tr>
  </table>
  <input type=hidden name=page value='statComm/search'><input type=hidden name=statcomm_action value=search>
  </form><br>
<?php
		  $s=isset($_GET["searchsubmit"])?$_GET['searchsubmit']:"";
          if ($s)
          {
              // query builder
              //$qry = "";
              // FIELDS
              $fields = "";
              for ($i = 1; $i <= 3; $i++)
              {
				$w=isset($_GET["where$i"])?$_GET["where$i"]:"";
                  if ($w != '')
                  {
                      $fields .= $w . ",";
                  }
              }
              $fields = rtrim($fields, ",");
              // WHERE
              $where = "WHERE 1=1";
              $s=isset($_GET['spider'])?$_GET['spider']:"";
              $f=isset($_GET['feed'])?$_GET['feed']:"";
              if ($s != 'checked')
              {
                  $where .= " AND spider=''";
              }
              if ($f != 'checked')
              {
                  $where .= " AND feed=''";
              }
              for ($i = 1; $i <= 3; $i++)
              {
              	  $whati=isset($_GET["what$i"])?$_GET["what$i"]:"";
              	  $wheri=isset($_GET["where$i"])?$_GET["where$i"]:"";
                  if (($whati != '') && ($wheri != ''))
                  {
                      $where .= " AND " . $wheri . " LIKE '%" . mysql_real_escape_string($whati) . "%'";
                  }
              }
              // ORDER BY
              $orderby = "";
              for ($i = 1; $i <= 3; $i++)
              {
              	  $sorti=isset($_GET["sortby$i"])?$_GET["sortby$i"]:"";
              	  $wheri=isset($_GET["where$i"])?$_GET["where$i"]:"";
                  if (($sorti == 'checked') && ($wheri != ''))
                  {
                      $orderby .= $wheri . ',';
                  }
              }

              // GROUP BY
              $groupby = "";
              for ($i = 1; $i <= 3; $i++)
              {
              	 $grupi=isset($_GET["groupby$i"])?$_GET["groupby$i"]:"";
              	 $wheri=isset($_GET["where$i"])?$_GET["where$i"]:"";
                  if (($grupi == 'checked') && ($wheri != ''))
                  {
                      $groupby .= $wheri . ',';
                  }
              }
              if ($groupby != '')
              {
                  $groupby = "GROUP BY " . rtrim($groupby, ',');
                  $fields .= ",count(*) as totale";
                  $oby =isset($_GET['oderbycount'])?$_GET['oderbycount']:"";
                  if ($oby == 'checked')
                  {
                      $orderby = "totale DESC," . $orderby;
                  }
              }

              if ($orderby != '')
              {
                  $orderby = "ORDER BY " . rtrim($orderby, ',');
              }


              $limit = "LIMIT " . $_GET['limitquery'];

			  $dst=isset($_GET['distinct'])?$_GET['distinct']:"";
              if ($dst == 'checked')
{
   $fields = " DISTINCT " . $fields;
}

              // Results
              print "<h2>" . __('Results', 'statcomm') . "</h2>";
              $sql = "SELECT $fields FROM $table_name $where $groupby $orderby $limit;";
              //  print "$sql<br>";
              print "<table class='widefat'><thead><tr>";
              for ($i = 1; $i <= 3; $i++)
              {
              	 $wheri=isset($_GET["where$i"])?$_GET["where$i"]:"";
                  if ($wheri != '')
                  {
                      print "<th scope='col'>" . ucfirst($wheri) . "</th>";
                  }
              }
              if ($groupby != '')
              {
                  print "<th scope='col'>" . __('Count', 'statcomm') . "</th>";
              }
              print "</tr></thead><tbody id='the-list'>";
              //TO IMPROVE: default type returned
              $qry = $wpdb->get_results($sql, ARRAY_N);
              foreach ($qry as $rk)
              {
                  print "<tr>";
                  for ($i = 1; $i <= 3; $i++)
                  {
                      print "<td>";
                      $wheri=isset($_GET["where$i"])?$_GET["where$i"]:"";
                      if ($wheri == 'urlrequested')
                      {
                          print utilities::outUrlDecode($rk[$i - 1]);
                      }
                      else
                      {
                      	$rki=isset($rk[$i - 1])?$rk[$i - 1]:"";
                        print $rki;
                      }
                      print "</td>";
                  }
                  print "</tr>";
              }
              print "</table>";
              print "<br /><br /><font size=1 color=gray>sql: $sql</font></div>";
          }
      }



      /**
       * Return table size and record number
       * 20120408 1.6.3:Improved
       * @return string
       */
      function iritablesize()
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

      function irirgbhex($red, $green, $blue)
      {
          $red = 0x10000 * max(0, min(255, $red + 0));
          $green = 0x100 * max(0, min(255, $green + 0));
          $blue = max(0, min(255, $blue + 0));
          // convert the combined value to hex and zero-fill to 6 digits
          return "#" . str_pad(strtoupper(dechex($red + $green + $blue)), 6, "0", STR_PAD_LEFT);
      }

      /**
       * Get info data from a query
       * Updated in 1.6.3, needs a serious improve.
       * @param $fld
       * @param $fldtitle
       * @param int $limit
       * @param string $param
       * @param string $queryfld
       * @param string $exclude
       */
      function iriValueTable($fld, $fldtitle, $limit = 0, $param = "", $queryfld = "", $exclude = "")
      {
          if ($queryfld == '')
          {
              $queryfld = $fld;
          }
          print "<div class='wrap'><h2>$fldtitle</h2><table style='width:100%;padding:0px;margin:0px;' cellpadding=0 cellspacing=0><thead><tr><th style='width:400px;background-color:white;'></th><th style='width:150px;background-color:white;'><u>" . __('Visits', 'statcomm') . "</u></th><th style='background-color:white;'></th></tr></thead>";
          print "<tbody id='the-list'>";

          $getCount="SELECT count($param $queryfld) as rks FROM #table# WHERE 1=1 $exclude";
          $rks = mySql::get_var($getCount);

          if ($rks > 0)
          {
              $sql = "SELECT count($param $queryfld) as pageview, $fld FROM #table# WHERE 1=1 $exclude  GROUP BY $fld ORDER BY pageview DESC";
              if ($limit > 0)
              {
                  $sql = $sql . " LIMIT $limit";
              }
              $qry = mySql::get_results($sql);
              $tdwidth = 450;
              $red = 131;
              $green = 180;
              $blue = 216;
              $deltacolor = round(250 / count($qry), 0);
              //      $chl="";
              //      $chd="t:";
              foreach ($qry as $rk)
              {
                  $pc = round(($rk->pageview * 100 / $rks), 1);
                  if ($fld == 'date')
                  {
                      $rk->$fld = utilities::conv2Date($rk->$fld);
                  }
                  if ($fld == 'urlrequested')
                  {
                      $rk->$fld = utilities::outUrlDecode($rk->$fld);
                  }

                  if ($fld == 'search')
                  {
                  	$rk->$fld = urldecode($rk->$fld);
                  }

                  //      $chl.=urlencode(mb_substr ($rk->$fld,0,50))."|";
                  //      $chd.=($tdwidth*$pc/100)."|";
                  print "<tr><td style='width:400px;overflow: hidden; white-space: nowrap; text-overflow: ellipsis;'>" . mb_substr ($rk->$fld, 0, 50);
                  if (strlen($rk->$fld) >= 50)
                  {
                      print "...";
                  }
                  // <td style='text-align:right'>$pc%</td>";
                  print "</td><td style='text-align:center;'>" . $rk->pageview . "</td>";
                  print "<td><div style='text-align:right;padding:2px;font-family:helvetica;font-size:7pt;font-weight:bold;height:16px;width:" . number_format(($tdwidth * $pc / 100), 1, '.', '') . "px;background:" . $this->irirgbhex($red, $green, $blue) . ";border-top:1px solid " . $this->irirgbhex($red + 20, $green + 20, $blue) . ";border-right:1px solid " . $this->irirgbhex($red + 30, $green + 30, $blue) . ";border-bottom:1px solid " . $this->irirgbhex($red - 20, $green - 20, $blue) . ";'>$pc%</div>";
                  print "</td></tr>\n";
                  $red = $red + $deltacolor;
                  $blue = $blue - ($deltacolor / 2);
              }
          }
          print "</table>\n";
          //  $chl=$this-> utf8_substr ($chl,0,strlen($chl)-1);
          //  $chd=mb_substr ($chd,0,strlen($chd)-1);
          //  print "<img src=http://chart.apis.google.com/chart?cht=p3&chd=".($chd)."&chs=400x200&chl=".($chl)."&chco=1B75DF,92BF23>\n";
          print "</div>\n";
      }

      /**
       * Analyze the url and return an array with pairs variable=value
       * Reference http://www.php.net/manual/es/function.parse-url.php
       * @param $url
       * @return array|null
       */
      function iriGetQueryPairs($url)
      {
          //$parsed_url = parse_url($url);
          $tab = parse_url($url);
          //$host = $tab['host'];
          if (key_exists("query", $tab))
          {
              $query = $tab["query"];
              $query = str_replace("&amp;", "&", $query);
              $query = urldecode($query);
              $query = str_replace("?", "&", $query);
              return explode("&", $query);
          }
          else
          {
              return null;
          }
      }


      /**
       * Analyze IP return true if banned
       * @param $arg
       * @return bool
       * 1.7.30: Potential limitation: if exist the custom file, the other file is plain ignored.
       * Is it treit doesn'twork i
       */
	  function iriCheckBanIP($arg)
      {
          global $wpdb;
          if (file_exists(ABSPATH . 'wp-content/plugins/' . dirname(plugin_basename(__FILE__)) . '-custom/banips.dat'))
              $lines = file(ABSPATH . 'wp-content/plugins/' . dirname(plugin_basename(__FILE__)) . '-custom/banips.dat');
          else
              $lines = file(ABSPATH . 'wp-content/plugins/' . dirname(plugin_basename(__FILE__)) . '/def/banips.dat');
        if ($lines !== false)
        {
            foreach ($lines as $banip)
              {
               if (@preg_match('/^' . rtrim($banip, "\r\n") . '$/', $arg)){
                   utilities::fl("matching between $banip and $arg in blod Id", $wpdb->blogid);
                   return true;
               }
              }
          }
          return false;
      }

  /**
   * Create table if doesn't exists
   * It uses dbDelta function to migrate table in case it needs.
   * Update 1.6.3: moved into mySql methods
   * v1.65.: added initialization options
   *         added current lapse, independent of cmb_uarefresh
   * v1.6.70: refactored . Option settings moved to settingsAPI, it has much more sense there.
   * v1.7.00: previous version assumed that activation hook fires on update. That was wrong (at least since WP 3.1(!)
   * See http://codex.wordpress.org/Creating_Tables_with_Plugins
   * v1.7.10: reset summaryTable on activation.
   * v1.7.20 multisite: we have to check if plugin is in multisite environment.
   * If it does, create a table on every blog we have.
   * Plugin should not rely on activation procedures as it could end up depending of other plugins or unexpected situations.
   * See: http://wpdevel.wordpress.com/2010/10/27/plugin-activation-hooks-no-longer-fire-for-updates/
   * and for multisite: https://core.trac.wordpress.org/ticket/14170
   * Multisite info reference to http://shibashake.com/wordpress-theme/write-a-plugin-for-wordpress-multi-site
   */
  function initTableAndOptions()
  {
    global $wpdb;
    If ( version_compare( get_bloginfo( 'version' ), '3.2.9', '<' ) ) {
        deactivate_plugins( basename( __FILE__ ) ); // Deactivate our plugin
        wp_die("<a href='http://www.wpgetready.com'>Statcomm</a> will work from Wordpress 3.3 and up<br/>.Please consider upgrade your Wordpress installation!<br/>");
    }

      //see restore_current_blog() if it works properly
      if (is_multisite()) { //no more function exists since wp 3.0
          utilities::fl("multisite mode active, proceeding to set data and tables..");

          $network=isset($_SERVER['SCRIPT_NAME'])?$_SERVER['SCRIPT_NAME']:"";
          $activate=isset($_GET['action'])?$_GET['action']:"";
          $isNetwork=($network=='/wp-admin/network/plugins.php')?true:false; //return true if we're trying network activation
          //I use deactivate because activate text doesn't come here (?), probably because the process doesn't depend on
          //activation deactivation
          $isActivation=($activate=='deactivate')?false:true;

          if ($isNetwork and $isActivation){
              utilities::fl("Activation in effect");
              $old_blog = $wpdb->blogid;
              // Get all blog ids
              $blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
              foreach ($blogids as $blog_id) {
                  switch_to_blog($blog_id);
                  $this->initSite();
              }
              switch_to_blog($old_blog);
              return; //end multisite activation
          }
          return; //don't do antything else
      }
      else
      {
          utilities::fl("NO network activation normal procedure in effect");
      }

      $this->initSite();
  }

    /**
     * Initialize entire settings and create table for current database
     * v1.7.20: the table creation runs FIRST, since if we have an error, the entire plugin has to abort
     * BEFORE saving any information.
     */
  function initSite()
  {
      utilities::fl("Initialization Table and values running...");
      mySql::checkTable( plugin_basename(__FILE__));
      settingsAPI::defaultValues(); //Initializate options to default values.
      mysql::resetSummaryTable();
  }

/**
 * Decides if it has to upgrade the plugin
 * This method is hook to plugins_loaded
 * Updated: independent statcomm_plugin_version
 */
      public function updateCheck() {
          $spv= get_option('statcomm_plugin_version');
          $needsUpgrade=isset($spv)?$spv:'';
        utilities::fl("option version:" ,$needsUpgrade);
         if ($needsUpgrade != utilities::PLUGIN_VERSION) {
              utilities::fl ("Detected new plugin version, proceeding to changes...");
              $this->initTableAndOptions(); //this method also updates plugin_version to the correct value.
          }
          else
          {
              utilities::fl("No update needed",utilities::PLUGIN_VERSION   );
          }
      }

/**
 * This function has to be improved A LOT since the new user agent detection is rendering
 * most of information obsolete.
 */
function statcommAgents()
      {
          $query = "SELECT date, MAX(time), ip, COUNT(*) as count, agent";
          $query .= " FROM #table#";
          $query .= " WHERE spider = '' AND browser = ''";
          $query .= " GROUP BY date, ip, agent";
          $query .= " ORDER BY date DESC";
          $result = mySql::get_results($query);

          print "<div class='wrap'><h2>" . __('Unknown User Agents', 'statcomm') . "</h2>";
          print "<table class='widefat'><thead><tr>";
          print "<th scope='col'>" . __('Date', 'statcomm') . "</th>";
          print "<th scope='col'>" . __('Last Time', 'statcomm') . "</th>";
          print "<th scope='col'>" . __('IP', 'statcomm') . "</th>";
          print "<th scope='col'>" . __('Count', 'statcomm') . "</th>";
          print "<th scope='col'>" . __('User Agent', 'statcomm') . "</th>";
          print "</tr></thead><tbody id='the-list'>";

          foreach ($result as $line)
          {
            $col = 0;
            print '<tr>';
            foreach ($line as $col_value)
{
    $col++;
    if ($col == 1)
        print '<td>' . utilities::conv2Date($col_value) . '</td>';
    else if ($col == 3)
        print "<td><a href='http://www.projecthoneypot.org/ip_" . $col_value . "' target='_blank'>" . $col_value . "</a></td>";
    else
        print '<td>' . $col_value . '</td>';
}
            print '</tr>';
          }
          print '</table></div>';
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

      /**
       * Statcomm capture data.
       * 1.6.3: Totally rewriten and highly improved.
       * Question: is there a way to include debug information without stressing the system? A:Yes
       * v1.6.70: declared some missing variables
       * v1.6.90: detection of condition errors (404). Note that detection is AFTER filtering, which means the filters have
       * relevance first. That will be discussed on the blog.
       * v1.6.90: action included.
       * v1.7.00: language support ,  improved saved process using prepare method, filter statcomm_preinsert
       * can be provided using geoLocation (Actually is not a problem but if we want performance, we have to look for alternatives)
       * v1.7.40: Improved error handling for geoLocation
       * @return string
       */
	  public function statcommAppend()
      {
        $t1=utilities::startTimer();
        $feed = '';
        global $userdata;
        get_currentuserinfo(); //get user information, update userdata variable

        //threat analysis not currently implemented in StatComm 1.6.3. In analysis
        $threat_score=0;
        $threat_type=0;
        //$os ='';
		//$browser='';
		//$isSpider= false;
        $searchengine="";
        $search_phrase="";

        //Get Time & IP
        $timestamp = current_time('timestamp');
        $vdate = gmdate("Ymd", $timestamp);
        $vtime = gmdate("H:i:s", $timestamp);
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        //if it is banned return
        if ($this->iriCheckBanIP($ipAddress) === true)
        {
            utilities::fl("rejected because ban IP");
        	return '';
        }
		//calculateThread currently in analysis
        $urlRequested = utilities::requestUrl();

		//Extract extension (if any)
		$extension = pathinfo( $urlRequested, PATHINFO_EXTENSION );
		//Filter by extension, discard common filetypes
		$commonFileTypes='/ico|css|js|jpe?g|png|gif/';
		if ( !empty( $urlRequested ) && preg_match( $commonFileTypes, $extension ))
		{
            utilities::fl("Rejected by extension:",$urlRequested);
			return'';
		}

		//Avoid collecting data from special folders (plugins, themes mu-,etc.)
		//1.6.4: modified specialfolder demiliter
		$specialFolders ='#(robots\.txt|wp-content/(mu-)?(plugins|themes)|wp-admin)#';
		//To improve: this filter will fail if we redefine WP_CONTENT_DIR & WP_CONTENT_URL
		// and/or also WP_PLUGIN_DIR, WP_PLUGIN_URL. Following versions will refine the concept
		if ( preg_match( $specialFolders,$urlRequested ) )
		{
            utilities::fl("Rejected by special:",$urlRequested);
			return '';
		}
        $redStatus = (isset($_SERVER['REDIRECT_STATUS']))?$_SERVER['REDIRECT_STATUS']:"";
        $statuscode = (!is_404())?$redStatus:404;
       //utilities::fl("Status code:",$statuscode);
        $referrer = (isset($_SERVER['HTTP_REFERER']) ? htmlentities($_SERVER['HTTP_REFERER']) : '');
        $userAgent = (isset($_SERVER['HTTP_USER_AGENT']) ? htmlentities($_SERVER['HTTP_USER_AGENT']) : '');
        //Analyze and extract relevant data.
		$parser= new statcommParser();
		$agent = $parser->Parse($userAgent);
        $spider ="";
		$options = settingsAPI::getOptions();
        $language=isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])?$_SERVER["HTTP_ACCEPT_LANGUAGE"]:'';
        $language= browserLanguage::getRelevantLanguage($language);
        if ($agent['typ'] == self::ROBOT)
        {
		  	$spider = $agent['ua_family'];
		  	$os='';
		  	$browser='';
        	//Check if we don't want to collect spiders
			if ($options["chk_no_spiders"]=='on')
			{
				return '';
			}
        }
        else //if not a Spider, is a feed?
        {
        	$prsurl = parse_url(get_bloginfo('url'));
			//return feedType if it is a feed, empty if not
		    $feed = utilities::feedType($prsurl['scheme'] . '://' . $prsurl['host'] . $_SERVER['REQUEST_URI']);
            // Get OS and browser
            $os = $agent['os_name'];
            $browser = $agent['ua_family']; //or it can be ua_version but it has far more details
            //get search_phrase using searchterms
            $pathDefinitionsFile=plugin_dir_path(__FILE__) . 'def';
           // utilities::fl("pathDefFile:", $pathDefinitionsFile);
            list($searchengine, $search_phrase) = utilities::searchTerm($referrer,$pathDefinitionsFile . '/searchterm.ini');
            //return empty string instead null
            $searchengine=empty($searchengine)?"":$searchengine;
            $search_phrase=empty($search_phrase)?"":$search_phrase;
        }

		  //if is a spider, we get feed='', search_phrase='',os='' and browser =''
		  // Auto-delete visits if settings are active. To improve: how about performance here?
		  //$wpdb->show_errors(); //Activate it for trace sql problems
      	if ($options["cmb_delete_spiders"] != 'Never delete!')
      	{
         	$t = gmdate("Ymd", strtotime('-' . $options["cmb_delete_spiders"]));
         	//Attention that this would need a new expansion in mysql class.
         	 mySql::query(mySql::QRY_AutoDelSpider, $t);
      	}
      	if ($options["cmb_delete_visitors"] != 'Never delete!')
      	{
        	$t = gmdate("Ymd", strtotime('-' . $options["cmb_delete_visitors"]));
        	mySql::query(mySql::QRY_AutoDel, $t);
      	}
        //v1.7.00: Introducing filter mode.
        //Notice that:
        //1-There are exceptions when the filter is not called at all.
        //Exceptions: Banned IP | File extension is filtered | Special folder url call | incoming visitor is a spider
        //and ignore spiders rule is activated | MaxMind option deactivated.
        $ud=isset($userdata->user_login)?$userdata->user_login:"";
          //$t2=utilities::startTimer(); //measure time
          $scu=new statcommCurrentUser();
          $scu->setDate($vdate);
          $scu->setTime($vtime);
          $scu->setIp($ipAddress);
          $scu->setUrlrequested(mysql_real_escape_string($urlRequested));
          $scu->setAgent(mysql_real_escape_string(strip_tags($userAgent)));
          $scu->setReferrer(mysql_real_escape_string($referrer) );
          $scu->setSearch(mysql_real_escape_string(strip_tags($search_phrase)));
          $scu->setNation(utilities::iriDomain($ipAddress)); //!!!!SLOW TODO: Solve this issue
          $scu->setOs(mysql_real_escape_string($os));
          $scu->setBrowser(mysql_real_escape_string($browser));
          $scu->setSearchengine($searchengine);
          $scu->setSpider($spider);
          $scu->setFeed($feed);
          $scu->setUser($ud);
          $scu->setThreatScore($threat_score);
          $scu->setThreatType($threat_type);
          $scu->setTimestamp($timestamp);
          $scu->setLanguage($language[0]);
          $scu->setStatuscode($statuscode);
          //add User Agent Information
          $scu->setUserAgent($agent);
          //add geoLocation
          $scu->setGeoLocation(null); //initialize geoLocation empty but if activated, put the proper value there.

          $geoLocation = utilities::geoLocationEnabled(); //null if there is a problem of file doesn't exist.
          if ($geoLocation !=utilities::ERROR_NONE)
          {
              $gi = utilities::geoLocationOpen(); //null if there is a problem of file doesn't exist.
              $record =GeoIpCity_Ctrl::GeoIP_info_by_addr($gi,$ipAddress); //$ipAddress
              utilities::geoLocationClose($gi);
              $scu->setGeoLocation($record);
          }

          //Test if this is possible.
          $scu= apply_filters('statcomm_preinsert',$scu);
          //A way to filter control is return a null value from the filter or return ''
          if(empty($scu)) return '';

      	if ((!is_user_logged_in()) or ($options["chk_logged"]=='on'))
      	{
              $insert = "Insert into #table#
                         (date,time,ip,urlrequested,agent,referrer,search,nation,os,browser,
                         searchengine,spider,feed,user,threat_score,threat_type,timestamp,language,statuscode)
                          values (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s,
                          %s, %s, %s, %s, %d, %d, %d, %s, %d) ";
              $data=array();
              $data[]=$scu->getDate();
              $data[]=$scu->getTime();
              $data[]=$scu->getIp();
              $data[]=$scu->getUrlrequested();
              $data[]=$scu->getAgent();
              $data[]=$scu->getReferrer();
              $data[]=$scu->getSearch();
              $data[]=$scu->getNation();
              $data[]=$scu->getOs();
              $data[]=$scu->getBrowser();
              $data[]=$scu->getSearchengine();
              $data[]=$scu->getSpider();
              $data[]=$scu->getFeed();
              $data[]=$scu->getUser();
              $data[]=$scu->getThreatScore();
              $data[]=$scu->getThreatType();
              $data[]=$scu->getTimestamp();
              $data[]=$scu->getLanguage();
              $data[]=$scu->getStatuscode();

              $query=mySql::prepare($insert,$data);
              $result=mySql::query($query);
              if (!empty($result))
              {
                  utilities::fl(("DB Error:" . $result));
              }

            //Check if class exists (since maxmind db could be deactivated)
            if (class_exists("statcommCurrentUser"))
            {
              do_action('statcomm_info',$scu);
            }
       	}
          utilities::fl ("Time completed t1:", utilities::stopTimer($t1));
          return '';
      }

		/**
		 * Provide localization for the plugin
		 * 1.6.4: update deprecated function.
		 */
		public function loadTextDomain() {
            utilities::fl("Text Domain",dirname(plugin_basename(__FILE__)) . '/locale/');
    		load_plugin_textdomain('statcomm',false,  dirname(plugin_basename(__FILE__)) . '/locale');
		}
}//1244,1147