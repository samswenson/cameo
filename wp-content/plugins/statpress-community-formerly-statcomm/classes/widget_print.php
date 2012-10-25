<?php
 /**
 * Adds StatComm_Print widget.
 * 20120401: First version
 */
class StatComm_Print extends WP_Widget {

        /**
         * Register widget with WordPress.
         */
        public function __construct() {
                parent::__construct(
                        'statcomm_print_widget', // Base ID
                        'StatComm_Print', // Name
                        array( 'description' => __( 'Allows to use dynamic variables in your HTML code', 'statcomm' ), ) // Args
                );
        }

        /**
         * Front-end display of widget.
         *
         * @see WP_Widget::widget()
         *
         * @param array $args     Widget arguments.
         * @param array $instance Saved values from database.
         */
        public function widget( $args, $instance ) {
                extract( $args );  //extract $before_widget,after_widgte

                //Add more flexibility to reprogram widget.
                $title = apply_filters('widget_title', $instance['title'] );
                $body  = apply_filters('widget_text' ,$instance['body']);
                echo $before_widget;
                if ( ! empty( $title ) )
                        echo $before_title . $title . $after_title;
                echo  $this->parseVariables($body);
                echo $after_widget;
        }

        /**
         * Sanitize widget form values as they are saved.
         *
         * @see WP_Widget::update()
         *
         * @param array $new_instance Values just sent to be saved.
         * @param array $old_instance Previously saved values from database.
         * @return array Updated safe values to be saved.
         */
        public function update( $new_instance, $old_instance ) {
                $instance = array();
                $instance['title'] = strip_tags( $new_instance['title'] );
                $instance['body'] =  $new_instance['body'] ;
                return $instance;
        }

        /**
         * Back-end widget form.
         * @see WP_Widget::form()
         * @param array $instance Previously saved values from database.
         */
        public function form( $instance ) {

        		$title = (isset( $instance[ 'title' ] ) ) ?$instance[ 'title' ]: __("Your title here","statcomm");
        		$body = (isset( $instance[ 'body' ] ) ) ?$instance[ 'body' ]:  __("Visits today: %visits%");


                ?>

                <h3><?php echo __("Available variables" , "statcomm"); ?></h3>
                <ul>
                <li><?php echo __("%browser% - Browser" , "statcomm"); ?></li>
                <li><?php echo __("%ip% - IP address" , "statcomm"); ?></li>
                <li><?php echo __("%latesthits% - 10 latest hits" , "statcomm"); ?></li>
                <li><?php echo __("%os% - Operative system" , "statcomm"); ?></li>
                <li><?php echo __("%pagestoday% - Pageviews today" , "statcomm"); ?></li>
                <li><?php echo __("%pagesyesterday% - Pageviews yesterday" , "statcomm"); ?></li>
                <li><?php echo __("%since% - Date of the first hit" , "statcomm"); ?></li>
                <li><?php echo __("%thistotalpages% - All pageviews so far" , "statcomm"); ?></li>
                <li><?php echo __("%thistotalvisits% - This page, total visits" , "statcomm"); ?></li>
                <li><?php echo __("%toppost% - The most viewed Post" , "statcomm"); ?></li>
                <li><?php echo __("%topbrowser% - The most used Browser" , "statcomm"); ?></li>
                <li><?php echo __("%topos% - The most used O.S." , "statcomm"); ?></li>
                <li><?php echo __("%totalvisits% - Total visits" , "statcomm"); ?></li>
                <li><?php echo __("%usersonline% - Logged online visitors" , "statcomm"); ?></li>
                <li><?php echo __("%visits% - Today visits" , "statcomm"); ?></li>
                <li><?php echo __("%visitorsonline% - All online visitors" , "statcomm"); ?></li>
                </ul>

                <p>
				<?php  //use class="widefat" to get a input all the widget wide  ?>
                <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label><br/>
                <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr($title); ?>" /><p/>
                <label for="<?php echo $this->get_field_id( 'body' ); ?>"><?php _e( 'Body text:' ); ?></label><p/>
                <textarea class="widefat" style="height:100px;" type="text"
                id="<?php  echo $this->get_field_id( 'body' ); ?>"
                name="<?php echo $this->get_field_name( 'body' ); ?>"><?php echo esc_attr( $body);?></textarea><p/>
                </p>
                <?php
        }

    /**
     * Parses variables for dynamic view
     * Invoked mainly from the widget
     * This should need a big revision...for next version
        Converted... now I have to review it and tested!!!
     * @param $body
     * @return mixed|string
     */
	      public function parseVariables($body)
      {
		  $parser = new statcommParser();
		  $userAgent = (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
		  $ua=$parser->Parse($userAgent);

          if (strpos(strtolower($body), "%visits%") !== false)
          {
			$qry = mySql::get_results(mySql::QRY_VisitorsDay,array(gmdate("Ymd", current_time('timestamp'))));
			$body = str_replace("%visits%", $qry[0]->visitors, $body);
          }

          if (strpos(strtolower($body), "%totalvisits%") !== false)
          {
              $qry = mySql::get_results(mySql::QRY_Visitors);
              $body = str_replace("%totalvisits%", $qry[0]->visitors, $body);
          }

          if (strpos(strtolower($body), "%thistotalvisits%") !== false)
          {
		      $qry = mySql::get_results(mySql::QRY_parseThisTotalVisits,mysql_real_escape_string(utilities::requestUrl()));
              $body = str_replace("%thistotalvisits%", $qry[0]->pageview, $body);
          }
          if (strpos(strtolower($body), "%since%") !== false)
          {
			  $qry = mySql::get_results(mySql::QRY_parseSince);
              $body = str_replace("%since%", utilities::conv2Date($qry[0]->date), $body);
          }
          if (strpos(strtolower($body), "%os%") !== false)
          {
              $os = $ua['os_name'];
              $body = str_replace("%os%", $os, $body);
          }
          if (strpos(strtolower($body), "%browser%") !== false)
          {
              $browser = $ua['ua_family'];
              $body = str_replace("%browser%", $browser, $body);
          }
          if (strpos(strtolower($body), "%ip%") !== false)
          {
              $ipAddress = $_SERVER['REMOTE_ADDR'];
              $body = str_replace("%ip%", $ipAddress, $body);
          }
          if (strpos(strtolower($body), "%visitorsonline%") !== false)
          {
              $to_time = current_time('timestamp');
              $from_time = strtotime('-4 minutes', $to_time);
			  $qry = mySql::get_results(mySql::QRY_parseVisitOnline,array($from_time,$to_time));
              $body = str_replace("%visitorsonline%", $qry[0]->visitors, $body);
          }
          if (strpos(strtolower($body), "%usersonline%") !== false)
          {
              $to_time = current_time('timestamp');
              $from_time = strtotime('-4 minutes', $to_time);
			  $qry = mySql::get_results(mySql::QRY_parseUsersOnline,array($from_time,$to_time));
              $body = str_replace("%usersonline%", $qry[0]->users, $body);
          }
          if (strpos(strtolower($body), "%toppost%") !== false)
          {
			  $qry = mySql::get_results(mySql::QRY_parseTopPosts);
              $body = str_replace("%toppost%", utilities::outUrlDecode($qry[0]->urlrequested), $body);
          }
          if (strpos(strtolower($body), "%topbrowser%") !== false)
          {
			  $qry = mySql::get_results(mySql::QRY_parseTopBrowser);
              $body = str_replace("%topbrowser%", utilities::outUrlDecode($qry[0]->browser), $body);
          }
          if (strpos(strtolower($body), "%topos%") !== false)
          {
			  $qry = mySql::get_results(mySql::QRY_parseTopOs);
              $body = str_replace("%topos%", utilities::outUrlDecode($qry[0]->os), $body);
          }
          if(strpos(strtolower($body),"%pagestoday%") !== false)
          {

			$qry = mySql::get_results(mySql::QRY_parsePagesToday,array(gmdate("Ymd",current_time('timestamp'))));
			$body = str_replace("%pagestoday%", $qry[0]->pageview, $body);
		  }

   		if(strpos(strtolower($body),"%thistotalpages%") !== FALSE)
   			{
					$qry = mySql::get_results(mySql::QRY_parseThisTotalPages);
      				$body = str_replace("%thistotalpages%", $qry[0]->pageview, $body);
      		}

      		if (strpos(strtolower($body), "%latesthits%") !== false)
			{
				$qry = mySql::get_results(mySql::QRY_parseLatestHits);
				$body = str_replace("%latesthits%", urldecode($qry[0]->search), $body);
				for ($counter = 0; $counter < 10; $counter += 1)
				{
					$body .= "<br>". urldecode($qry[$counter]->search);
				}
			}

			if (strpos(strtolower($body), "%pagesyesterday%") !== false)
			{
				//$yesterday = gmdate('Ymd', current_time('timestamp') - 86400);
				$qry = mySql::get_row(mySql::QRY_parsePagesYesterday);
				$body = str_replace("%pagesyesterday%", (is_array($qry) ? $qry[0]->visitsyesterday : 0), $body);
			}
          return $body;
      }

} // class StatComm_Print

//registration
add_action('widgets_init', create_function('','register_widget("StatComm_Print");'));
?>