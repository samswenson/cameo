<?php
/**
 * Created by WpGetReady @2012
 * Author: Fernando Zorrilla de San Martin
 * Date: 4/26/12
 * Time: 7:04 AM
 * Class to update GeoliteCity.dat
 * 20120426 13:50: first very rough version to test and improve. Due potential problems, it would be better to move the .gz file
 * to my account and try to download from there. After everything works alright, make a real test with the real destination.
 * 20120427: is not enough to set timeout in wp_remote_request
 * 20120428: I got an error directly from maxmind and the plugin didn't get it. That is because we assumed that
 * file will be there always.
 * 20120722: The class should work considering the current file is broken. Check if the file is broken and allow download.
 */

class geoliteUpdater
{
    // Maxmind url to download from. Avoid hitting this url too often since you can be banned for short periods
    //For testing, use alternative urls
    const GEOLITE_URL= 'http://geolite.maxmind.com/download/geoip/database/GeoLiteCity.dat.gz';
    const TIMEOUT = 120;
    private $GeoLiteFilePath='';
    private $GeoLiteGzipPath='';

    private $CheckPeriod= 0;

    function __construct()
    {
        $this->GeoLiteFilePath = plugin_dir_path(dirname(__FILE__)) . 'def/GeoLiteCity.dat';
        $this->GeoLiteGzipPath = plugin_dir_path(dirname(__FILE__)) . 'def/GeoLiteCity.gz.dat';
        $this->CheckPeriod =30*24*60*60; //check the database around every six months.
    }

    /**
     * Download the geolite datafile
     */
    function update()
    {

     $update=false;
     echo "<div class='wrap'>";
     echo "<div id='icon-statcomm' class='icon32'><br/></div>"; //custom statcomm icon
     utilities::msg(__("<h2>Gelocation Database Download</h2>","statcomm"),false);
     if (file_exists($this->GeoLiteFilePath))
     {
         $update=true;
         utilities::msg( '<b>'. __('Updating current local Maxmind GeoLiteCity database', 'statcomm').'</b>');
         //Additional checking: if the file is detected as corrupted, allow download right away.
         utilities::msg(__('Checking if Maxmind is in good conditions...','statcomm'));
         $geoLocation= utilities::geoLocationEnabled();
         if ($geoLocation == utilities::ERROR_NONE)
         {
             utilities::msg(__('Database is OK, trying to update...','statcomm'));
         }
         else
         {
             $update=false;
             utilities::msg(__('Database detected possibly corrupted, trying to replace...','statcomm'));
         }
     }
      else
      {
         utilities::fl("GeoLiteCity file not found ", $this->GeoLiteFilePath);
         utilities::msg( '<b>'. __('Preparing a first time install of the Maxmind GeoLiteCity database', 'statcomm').'</b>');
      }
      //If we are low in memory, increase it
      //TODO:(is it necessary?)
      $this->set_memory_increase();

      //We would need to retrieve ONLY the headers from a file, NOT the entire file. Is that possible using wp_remote_**?
      //wp_remote_retrieve_headers is NOT doing what we need. It just analyzes the result array only.
      //For the moment we're using a custom curl function to get the date if we need it
      //TODO:make sure that curl is available, if not , find another alternative.
      //TODO:if I have file and size, I can show to the user the real size. Correct it.
      if ($update)
      {
          $local_file_time = filemtime($this->GeoLiteFilePath); //Get local file date
          $header=$this->get_remote_date(self::GEOLITE_URL);    //and also remote file date
          $remote_file_time=$header['time']; //TODO:CHECK FORMAT and assure compatible format
          utilities::fl("time returned: local file: $local_file_time, remote file: $remote_file_time");
          //if returned false, we cannot use curl for this server
          if ($remote_file_time==false)
          {
              utilities::fl("Problems getting file date: file not found or function failed, curl deactivated");
              utilities::msg( '<br /><br /><b>'.__('Client error: unable to retrieve file date from server (curl no supported) ', 'statcomm').'</b><br />');
              utilities::msg( '<br /><br /><b>'.__('Your server does not have curl activated', 'statcomm').'</b><br />');
              return;
          }
          else
          {
              utilities::fl("difference between now and local file date (days)",date("d",$local_file_time - time()));
              utilities::fl("Period in days:", date("d",$this->CheckPeriod));

              if (time() - $local_file_time   >  $this->CheckPeriod ) { // if remote date is less than period, skip download
                  //Time is less than period, is the server newer than local?
                  utilities::fl("Check if remote is newer than local:");
                  utilities::fl("positive result means download:", $remote_file_time-$local_file_time);
                  if ($remote_file_time>$local_file_time)
                  {
                      $this->downloadFile();
                  }
                  else
                  {
                    utilities::fl("Is not time to download yet");
                    $this->upToDate($remote_file_time,$local_file_time);
                  }
              }
              else
              {
                  //. date("d",$this->CheckPeriod) .
                  utilities::msg( '<b>'.__('Skipped checking (only once every ', 'statcomm') . ($this->CheckPeriod/86400) .
                    __(' days)', 'statcomm'). '</b>');

                  $this->upToDate($remote_file_time,$local_file_time);
              }
          }
      }
      else
      {
            //download the file directly without the hassle.
            $this->downloadFile();
      }
     if (is_multisite() and current_user_can("manage_network"))
     {
         //Return to network admin only for network admins
         echo '<br/> <a href="' . wp_nonce_url(admin_url( 'network/admin.php?page=Statcomm' ),'statcomm-geolite'). '" >'. __('Return to Network Options menu', 'statcomm'). '</a>';
     }
        else
     {
            echo '<br/> <a href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm/options' ),'statcomm-geolite'). '" >'. __('Return to Options menu', 'statcomm'). '</a>';
     }
      echo "</div>";
    }

    /**
     * Provides a download procedure checking various potential limitations.
     * @return mixed
     */
    private function downloadFile(){
        utilities::msg( '<b>'.__('GeoliteCity size is about 10 MB.', 'statcomm').'</b>');
        utilities::msg( __('The download time depends on your server download speed.', 'statcomm'));
        utilities::msg( __('A timeout can happen if the download takes more than 2 minutes.', 'statcomm'));
        utilities::msg( __('Getting file from server...', 'statcomm'));

        //Proceed to download using HTTP API
        utilities::fl("Getting file from server...");
        @set_time_limit(self::TIMEOUT);
        $geoliteGz= wp_remote_request(self::GEOLITE_URL,array('timeout'=>self::TIMEOUT));
        //If we get an error, display it and abort
        if ($this->versionError($geoliteGz))  return;
        utilities::fl("gzip file downloaded to memory, saving to disk...");
        utilities::msg("Done. Saving gzip file to disk...");
        //File downloaded. Save to disk the compressed file, but don't get me warnings nor errors, we'll deal later
        //Put the file where current .dat file resides
        $realpath=str_replace("\\","/",$this->GeoLiteGzipPath);  //watchout:there is no need to put filename.
        @file_put_contents($realpath, wp_remote_retrieve_body($geoliteGz));
        utilities::fl("Saving file to",$realpath);
        //Check if was saved alright.
        //TODO:check if writable before begin
        if (!file_exists($this->GeoLiteGzipPath ))
        {
            utilities::fl("gzip file not found");
            utilities::msg( __('Could not save gzip file to disk or file not found. Permissions problems?', 'statcomm'));
            return;
        }
        utilities::fl("File gzip in correct place, unzipping:",$this->GeoLiteGzipPath );
        utilities::msg("Done. Decompressing file...");
        //un-gzip file, if error, abort
        if (!utilities::ungzipFile($this->GeoLiteGzipPath,$this->GeoLiteFilePath)) return;
        utilities::msg("Done.");
        //Check if file is in place
        if (!file_exists($this->GeoLiteFilePath))
        {
            utilities::fl("file misplaced or not found");
            utilities::msg( __('Could not save GeoLiteCity.dat to disk or file not found. Permissions problems?', 'statcomm'));
            return;
        }
        utilities::fl("file successfully save.");
        utilities::msg( __('GeoLiteCity.dat successfully saved and ready to use', 'statcomm'));
    }


    /**
     * Show appropriate message when error
     * v1.7.20: improved error if we don't get a proper message
     * @param $fileGz
     * @return bool
     */
    private function versionError($fileGz)  {
        $code   = wp_remote_retrieve_response_code($fileGz);
        $errMsg = wp_remote_retrieve_response_message($fileGz);
        if(is_wp_error($fileGz) or ($code>399))
        {
            //Analyze error type

            utilities::fl("error code:" , $code);
            utilities::fl("error detail:",$fileGz);
            if ($code <500) //Request failed of a client error
            {
                utilities::fl("Client error $code while getting version: " , $errMsg);
                utilities::msg( '<b>'. sprintf( __('Client error when retrieving file:(%d) - %s'  , 'statcomm'),$code,$errMsg).'</b>');
                if (empty($errMsg))
                {
                    utilities::msg( '<b>'.__('Error details:'  , 'statcomm').'</b>');
                    utilities::msg( var_dump($fileGz));
                }
                return true;
            }
            else //Request failed because a server error
            {
                utilities::fl("Server error $code: while getting version: ", $errMsg);
                utilities::msg( '<b>'.sprintf (__('Server error when retrieving file:(%d) - %s'  , 'statcomm'),$code,$errMsg).'</b>');
                if (empty($errMsg))
                {
                    utilities::msg( '<b>'.__('Error details:'  , 'statcomm').'</b>');
                    utilities::msg( var_dump($fileGz));
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Message of already updated
     */
    private function upToDate($remote_date,$local_file_time)    {
        utilities::msg( '<b>'.__('You have the latest available Maxmind GeoLiteCity database', 'statcomm').'</b>');
        // how many calendar days ago?
        $maxmind_days_ago = floor((strtotime(date('Y-m-d'). ' 00:00:00') - strtotime(date('Y-m-d', $remote_date ). ' 00:00:00')) / (60*60*24));
        $yours_days_ago = floor((strtotime(date('Y-m-d'). ' 00:00:00') - strtotime(date('Y-m-d', $local_file_time). ' 00:00:00')) / (60*60*24));

        utilities::msg( sprintf( __('Maxmind database last update was about %d days ago (checked against file date on the server)' ,'statcomm'), $maxmind_days_ago).
            '</b>');//.sprintf( __('<br/>You can download it directly from <a href="%s" target="_new">newest file available</a>', 'statcomm'),'http://www.maxmind.com/app/geolitecity').'</b>.<br />');

        utilities::msg( sprintf( __('You updated to the current GeoLiteCity database on %s (%d days ago)','statcomm'), date("Y-m-d",$local_file_time),$yours_days_ago).
             '</b><br /><br />'.__('No new updates are available today.', 'statcomm').'</b>');
    }

    /**
     * Increase memory to 64M if needed
     */
    private function set_memory_increase(){
    $vm_mem_limit = 'unknown';
    $vm_mem_increase = '64M';
    $vm_mem_limit = @ini_get('memory_limit');
    if ($vm_mem_limit == 'unknown') {
        utilities::msg( __('PHP Memory Limit is unknown, increase is not available.','statcomm'));
        return;
    }
    if ( function_exists('memory_get_usage')  && ( (int) @ini_get('memory_limit') < abs(intval($vm_mem_increase)) ) ) {
        utilities::msg( sprintf( __('PHP Memory Limit is %1$s, temporarily increasing to %2$s ','statcomm'),$vm_mem_limit,$vm_mem_increase));
        @ini_set('memory_limit', $vm_mem_increase);
        $vm_mem_limit = @ini_get('memory_limit');
        if($vm_mem_limit == $vm_mem_increase) {
            utilities::msg( __('completed.','statcomm'));
        } else {
            utilities::msg( __('failed.','statcomm'));
        }
    }
    else
    {
        utilities::msg( sprintf( __('PHP Memory Limit is %s, increasing is not needed.','statcomm'),$vm_mem_limit));
    }
    }

    /**
     * Get remote datetime of a file
     * Limitation: server need to support curl
     * Reference: http://sandalian.com/php/check-filesize-of-remote-file-using-php.html
     * @param $url
     * @return array|bool
     */
    function get_remote_date($url){
        if (!function_exists('curl_init'))
            return false;

        $uh = curl_init();
        curl_setopt($uh, CURLOPT_URL, $url);

        // set NO-BODY to not receive body part
        curl_setopt($uh, CURLOPT_NOBODY, 1);

        // set HEADER to be false, we don't need header
        curl_setopt($uh, CURLOPT_HEADER, 0);

        // retrieve last modification time
        curl_setopt($uh, CURLOPT_FILETIME, 1);
        curl_exec($uh);

        // assign filesize into $filesize variable
        $filesize = curl_getinfo($uh,CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        // assign file modification time into $filetime variable
        $filetime = curl_getinfo($uh,CURLINFO_FILETIME);
        curl_close($uh);

        // push out
        return array("size"=>$filesize,"time"=>$filetime);
    }


}