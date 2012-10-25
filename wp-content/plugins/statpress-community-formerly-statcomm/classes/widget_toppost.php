<?php
 /**
 * Adds StatComm_TopPosts widget.
 * 20120401: First version.
 * There are a few things to improve:
 * -Documentation
 * -Code simplification: one class to solve the solution
 * -Pass string to multilanguage
 */
class StatComm_TopPosts extends WP_Widget {

        /**
         * Register widget with WordPress.
         */
        public function __construct() {
                parent::__construct(
                        'statcomm_toppost_widget', // Base ID
                        'StatComm_TopPosts', // Name
                        array( 'description' => __( 'Show Top Posts from your blog', 'statcomm' ), ) // Args
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
                //allow filter to change the title in any way
                $title = apply_filters( 'widget_title', $instance['title'] );
                echo $before_widget;
                if ( ! empty( $title ) )
                        echo $before_title . $title . $after_title;
                echo $this->topPosts($instance['limit_results'],$instance['visits']);
                echo $after_widget;
        }

        /**
         * Sanitize widget form values as they are saved.
         *
         * @see WP_Widget::update()
         *
         * @param array $new_instance Values just sent to be saved.
         * @param array $old_instance Previously saved values from database.
         *
         * @return array Updated safe values to be saved.
         */
        public function update( $new_instance, $old_instance ) {
                $instance = array();
                $instance['title'] = strip_tags( $new_instance['title'] );
                $instance['limit_results'] = strip_tags( $new_instance['limit_results'] );
                $instance['visits'] = strip_tags( $new_instance['visits'] );
                return $instance;
        }

        /**
         * Back-end widget form.
         *
         * @see WP_Widget::form()
         *
         * @param array $instance Previously saved values from database.
         */
        public function form( $instance ) {

        		$title = (isset( $instance[ 'title' ] ) ) ?$instance[ 'title' ]:__( 'Your title here...', 'statcomm' );
				$limit_results = (isset( $instance[ 'limit_results' ] )) ?$instance[ 'limit_results' ]:5;
				$visits =(isset( $instance[ 'visits' ] ))?$instance[ 'visits' ]:"1";

                //use class="widefat" to get a input all the widget wide
                ?>
                <p>

                <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
                <input  id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /><p/>
                <label for="<?php echo $this->get_field_id( 'limit_results' ); ?>"><?php _e( 'Limit results to:' ); ?></label>
                <input  id="<?php echo $this->get_field_id( 'limit_results' ); ?>" name="<?php echo $this->get_field_name( 'limit_results' ); ?>" type="text" value="<?php echo esc_attr( $limit_results ); ?>" /><p/>
                <label for="<?php echo $this->get_field_id( 'visits' ); ?>"><?php _e( 'Show Visitor Numbers:' ); ?></label>
                <input id="<?php echo $this->get_field_id( 'visits' ); ?>" type="checkbox" name="<?php echo $this->get_field_name( 'visits' ); ?>"  value="1" <?php checked('1', $visits ); ?> />
                </p>
                <?php
        }


		/**
		 * Get the top 5 posts.
		 */
	  function topPosts($limit = 5, $showcounts)
      {
          $res = "\n<ul>\n";
		  $qry = mySql::get_results(mySql::QRY_TopPosts,$limit);
		  if(count($qry)!=0)
		  {
	          foreach ($qry as $rk)
	          {
	            $res .= "<li><a href='" . utilities::irigetblogurl() .
	            ((strpos($rk->urlrequested, 'index.php') === FALSE) ? $rk->urlrequested : '') . "'>" .
	            utilities::outUrlDecode($rk->urlrequested) . "</a>";

	            if (strtolower($showcounts) == '1')
	            {
	            	$res .= " (" . $rk->totale . ")";
	            }
	            $res .="</li>";
	          }
		  }
		  else
		  {
		  	echo __( 'No results.</br>', 'statcomm' );
		  }
          return "$res</ul>\n";
      }


} // class StatComm_TopPosts

//registration
add_action('widgets_init', create_function('','register_widget("StatComm_TopPosts");'));
?>