<?php
/**
 * Created by WpGetReady n @2012
 * Author: Fernando Zorrilla de San Martin
 * Date: 09/05/12
 * Time: 04:28 PM
 * class to update uasparser info.
 * We decided change to manual mode, since in that way the user has complete plugin control.
 * 201200509: Improved: date handle as object. Problem DateTime object is for PHP 5.3 or up :(
 */

class uaparserUpdater
{

    private $uaParserFilePath='';
    private $uaParserZipPath='';
    private $uaParserCache='';

    function __construct()
    {
        $this->uaParserFilePath = plugin_dir_path(dirname(__FILE__)) . 'def/uasdata.ini';
        $this->uaParserZipPath  = plugin_dir_path(dirname(__FILE__)) . 'def/uasdata.zip';
        $this->uaParserCache    = plugin_dir_path(dirname(__FILE__)) . 'def/cache.ini';
    }

    /**
     * Main function to update
     * Dumps info to the screen while is processing
     * @return mixed
     */
    function update()
    {
        echo "<div class='wrap'>";
        echo "<div id='icon-statcomm' class='icon32'><br/></div>"; //custom statcomm icon
        utilities::msg(__("<h2>User Agent String Database Download</h2>"),false);
        utilities::fl("checking uaparser existance...","");
        //Does the uaparser exists?
        if (file_exists($this->uaParserFilePath)) {
            utilities::fl("file {$this->uaParserFilePath} ok,checking cache","");
            //And also, does the cache exists?
            if (file_exists($this->uaParserCache)) {
                utilities::fl("file {$this->uaParserCache} ok, checking if we need to update","");
                //Do we need to update? compare current version with server version.
                //If it is different, update. The server will always try to be up-to-date.
                $cacheIni = parse_ini_file($this->uaParserCache);
                $localversion = isset($cacheIni['localversion'])?$cacheIni['localversion']:0;
                $msgVersion= utilities::remoteRequest(utilities::UAPARSER_VERSION,utilities::TIMEOUT);
                $isError=utilities::isError($msgVersion,
                                        __("Client error when getting version(%d) - %s","statcomm"),
                                        __("Server error when getting version(%d) - %s","statcomm")
                                        );
                //isError already handle error situation so we don't need to do anything else.
                if (!$isError)
                {
                    //Evaluate the current version against the last version
                    $version=wp_remote_retrieve_body($msgVersion);
                    if ($version != $localversion) //Version differs, download it.
                    {
                        utilities::msg(__("New version found in server, starting download...","statcomm"));
                        $this->downloadFile($version);
                    }
                    {
                        utilities::msg(__("You already have the last version. No need to update.","statcomm"));
                    }
                }
            }
            else //cache file does not exist, download it.
            {
                utilities::msg(__("UAS Cache file not present, starting download...","statcomm"));
                $this->downloadFile();
            }
        }
        else //parser file does not exist, download it
        {
            utilities::msg(__("UAS Parser database not present, starting download...","statcomm"));
            $this->downloadFile();
        }
        if (is_multisite() and current_user_can("manage_network"))
        {
            echo '<br/> <a href="' . wp_nonce_url(admin_url( 'network/admin.php?page=Statcomm' ),'statcomm-uaparserupdate'). '" >'. __('Return to Network Options menu', 'statcomm'). '</a>';
        }
        else
        {
            echo '<br/> <a href="' . wp_nonce_url(admin_url( 'admin.php?page=statComm/options' ),'statcomm-uaparserupdate'). '" >'. __('Return to Options menu', 'statcomm'). '</a>';
        }

        echo "</div>";
    }


    /**
     * 20120510: Added optional parameter version to avoid calling version twice in case new version exists on server
     * @param string $version: server Version (only case when we check and find that server version is newer
     * @return mixed
     */
    private function downloadFile($version=""){

        //Before doing anything, we check if the Zip utility is available.
        if (!class_exists('ZipArchive'))
        {
            utilities::fl("ZipArchive not supported/installed for this host. The plugin cannot unzip the file. Process aborted","");
            return;
        }

        utilities::msg( '<b>'.__('UA Parser size is about 100 Kb.', 'statcomm').'</b>');
        utilities::msg( __('This process should be very fast...', 'statcomm'));
        utilities::msg( __('Getting file from server...', 'statcomm'));

        //Proceed to download using HTTP API
        utilities::fl("Getting file from server...");
        $uaparserZip= utilities::remoteRequest(utilities::UAPARSER_URL,utilities::TIMEOUT);
        //If we get an error, display it and abort

        $isError= utilities::isError($uaparserZip,
                                __('Client error when retrieving zip file:(%d) - %s'  , 'statcomm'),
                                __('Server error when retrieving file:(%d) - %s'  , 'statcomm')
        );

        if($isError) return;

        utilities::fl("Zip file downloaded to memory, saving to disk...");
        utilities::msg("Done. Saving Zip file to disk...");
        //File downloaded. Save to disk the compressed file, but don't get me warnings nor errors, we'll deal later
        //Put the file where current .dat file resides
        $realpath=str_replace("\\","/",$this->uaParserZipPath);  //watchout:there is no need to put filename.
        @file_put_contents($realpath, wp_remote_retrieve_body($uaparserZip));
        utilities::fl("Saving file to",$realpath);
        //Check if was saved alright.
        if (!file_exists($this->uaParserZipPath ))
        {
            utilities::fl("Zip file not found");
            utilities::msg( __('Could not save Zip file or file not found. Permissions problems?', 'statcomm'));
            return;
        }
        utilities::fl("File Zip in correct place, unzipping:",$this->uaParserZipPath );
        utilities::msg("Done. Decompressing file...");
        //Checking MD5 first
        $md5response=utilities::remoteRequest (utilities::UAPARSER_MD5,utilities::TIMEOUT);
        //check if error
        $isError=utilities::isError($md5response ,
                            __('Client error when retrieving file:(%d) - %s'  , 'statcomm') ,
                            __('Server error when retrieving file:(%d) - %s'  , 'statcomm')
                            );

        if($isError) return;

        utilities::fl("response md5response:",$md5response);
        $md5_from_server= wp_remote_retrieve_body($md5response);
        $md5_file=md5_file($this->uaParserZipPath);
        if ($md5_from_server!=$md5_file)
        {
            utilities::fl("Check file mismatch: $md5_from_server != $md5_file ");
            utilities::msg(__("Error: File md5 mismatch when downloading. Abort process"));
            return;
        }
        utilities::fl("File checked ok, proceed to unzip","");
        //Proceed to unzip the file
        $zip = new ZipArchive() ;
        //open archive
        //TODO: check if this is really a zip file
        if ($zip->open($this->uaParserZipPath) !== TRUE)
        {
            utilities::fl('Could not open zip file. Aborting...');
            utilities::msg(_("Error:Could not open zip file. Aborted.","statcomm"));
            return;
        }
        utilities::fl("File zip downloaded ok, proceed to unzip");
        //Extract contents to destination directory
        //Bug in windows: files using \ confuse the class, forcing to make a folder
        $realpath=str_replace("\\","/",dirname($this->uaParserFilePath));  //watchout:there is no need to put filename.
        //TODO:the destination filename has to be exactly the name we are expecting. Check or see a way to put exactly the name we're expecting.
        $zip->extractTo($realpath);
        $zip->close();
        //see also http://php.net/manual/es/ref.zip.php
        //and also php zip stream http://php.net/manual/en/wrappers.compression.php
        //Checking if the file is extracted right.
        if (!file_exists($this->uaParserFilePath))
        {
            utilities::fl("uasdata creation failed: permission problems?");
            utilities::msg(__("Error: failed to create uasdata.ini file. Permision problems?","statcomm"));
            return;
        }
        utilities::fl("file unzipped and in correct directory" , $this->uaParserFilePath);
        //TODO: until now we don't know yet if the file is valid or was tampered (since I only checked the ZIP file not the inside.)
        @unlink($this->uaParserZipPath);
        if (file_exists($this->uaParserZipPath))
        {
            utilities::fl("File {$this->uaParserZipPath} not deleted: permission problem?","");
            utilities::msg(__("Warning:Impossible to delete Zip file: permision problems?","statcomm"));
            //return; not critical error at this stage
        }
        else
        {
            utilities::fl("File {$this->uaParserZipPath} deleted ok");
        }

        if(empty($version)) //Never set $version before (only when a new version is in the server)
                            //The idea is to avoid to call version twice in that case.
        {
            //Get version from server
            $verServer= utilities::remoteRequest(utilities::UAPARSER_VERSION,utilities::TIMEOUT);
            //check if error
            $isError= utilities::isError($verServer ,
                          __('Client error when retrieving version:(%d) - %s'  , 'statcomm'),
                          __('Server error when retrieving version:(%d) - %s'  , 'statcomm')
                              );
            if ($isError) return; //potential problem: if system is unable to save cache, it will keep always trying to get the file.
            $version=wp_remote_retrieve_body($verServer);
        }

        //Make cache file with the new version
        $this->buildCacheFile($this->uaParserCache,$version); //true if success, false if not.
        return;
    }


    /**
     * Build Cache file, return false if fails, true if succeed
     * @param $PARSER_CACHE: Location of where the cache will be stored
     * @param $ver: File version
     * @return void
     */
    private function buildCacheFile($PARSER_CACHE,$ver)
    {
        // Get date from WP
        $timestamp = current_time('timestamp');
        $vdate = gmdate("Ymd", $timestamp);
        $vtime = gmdate("H:i:s", $timestamp);

        // Build a new cache file and store it
        $cacheIni = "; Cache info for class UASparser. Built time: $vdate $vtime";
        $cacheIni .= "[main]\n";
        $cacheIni .= "localversion = \"$ver\"\n";
        $cacheIni .= 'lastupdate = "'.time()."\"\n";
        @file_put_contents($PARSER_CACHE, $cacheIni);

        //Check if cache was built correctly
        if (!file_exists($PARSER_CACHE))
        {
            utilities::fl("Cache file creation failed or not found: permission problems?");
            return; //not necessary but if we expand the method it would has sense.
        }
        utilities::fl("file $PARSER_CACHE saved","");
        return;
    }


}