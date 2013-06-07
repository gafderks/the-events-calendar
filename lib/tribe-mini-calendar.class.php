<?php
if ( class_exists( 'TribeEventsMiniCalendar' ) )
	return;

class TribeEventsMiniCalendar {

	private $args;
	private $show_list = true;

	function __construct() {
		add_action( 'wp_ajax_tribe-mini-cal', array( $this, 'ajax_change_month' ) );
		add_action( 'wp_ajax_nopriv_tribe-mini-cal', array( $this, 'ajax_change_month' ) );

		add_action( 'wp_ajax_tribe-mini-cal-day', array( $this, 'ajax_select_day' ) );
		add_action( 'wp_ajax_nopriv_tribe-mini-cal-day', array( $this, 'ajax_select_day' ) );

	}

	/**
	 * Return the month to show in the widget
	 *
	 * @return string
	 * @since 3.0
	 * @author Jessica Yazbek
	 **/
	public function get_month()	{
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return isset( $_POST["eventDate"] ) ? $_POST["eventDate"] : date_i18n( TribeDateUtils::DBDATEFORMAT );
		}
		return date_i18n( TribeDateUtils::DBDATEFORMAT );
	}

	/**
	 * Get the args passed to the mini calendar
	 *
	 * @return array
	 * @since 3.0
	 * @author Jessica Yazbek
	 **/
	public function get_args() {
		return $this->args;
	}

	public function  ajax_select_day() {
		$response = array( 'success' => false, 'html' => '', 'view' => 'mini-day' );

		if ( isset( $_POST["nonce"] ) && isset( $_POST["eventDate"] ) && isset( $_POST["count"] ) ) {

			if ( ! wp_verify_nonce( $_POST["nonce"], 'calendar-ajax' ) )
				die();

			$response['success'] = true;

			add_action( 'pre_get_posts', array( $this, 'ajax_select_day_set_date' ), -10 );

			$tax_query = isset( $_POST['tax_query'] ) ? $_POST['tax_query'] : null;

			$this->args = array( 	'eventDate'   => $_POST["eventDate"],
									'count'        => $_POST["count"],
			               			'tax_query'    => $tax_query,
			               			'eventDisplay' => 'day' );

			ob_start();

			$this->setup_list();
			tribe_get_template_part('widgets/mini-calendar/list');

			remove_action( 'pre_get_posts', array( $this, 'ajax_select_day_set_date' ) );


			$response['html'] = ob_get_clean();

			if ( !empty( $_POST['return_objects'] ) && $_POST['return_objects'] === '1' ) {
				$response['objects'] = $events;
			}

		}
		apply_filters( 'tribe_events_ajax_response', $response );

		header( 'Content-type: application/json' );
		echo json_encode( $response );
		die();
	}

	public function ajax_change_month() {

		$response = array( 'success' => false, 'html' => '', 'view' => 'mini-month' );

		if ( isset( $_POST["eventDate"] ) && isset( $_POST["count"] ) ) {

			$tax_query = isset( $_POST['tax_query'] ) ? $_POST['tax_query'] : null;

			$args = array( 'eventDate'		=> $_POST["eventDate"],
			               'count'			=> $_POST["count"],
			               'tax_query'		=> $tax_query,
			               'filter_date'	=> true 
			               );

			ob_start();

			self::do_calendar( $args );

			$response['html']    = ob_get_clean();
			$response['success'] = true;

		}
		apply_filters( 'tribe_events_ajax_response', $response );

		// echo $response['html'];

		header( 'Content-type: application/json' );
		echo json_encode( $response );
		die();

	}

	/**
	 *
	 * returns the full markup for the AJAX Calendar
	 *
	 * @static
	 *
	 * @param array $args
	 *              -----> layout:      string  'wide' or 'tall'
	 *                                          month:       date    What month-year to print
	 *                                          count:       int     # of events in the list (doesn't affect the calendar).
	 *                                          tax_query:   array   For the events list (doesn't affect the calendar).
	 *                                          Same format as WP_Query tax_queries. See sample below.
	 *
	 *
	 * tax_query sample:
	 *
	 *        array( 'relation' => 'AND',
	 *               array( 'taxonomy' => 'tribe_events_cat',
	 *                      'field'    => 'slug',
	 *                      'terms'    => array( 'featured' ),
	 *              array( 'taxonomy' => 'post_tag',
	 *                     'field'    => 'id',
	 *                     'terms'    => array( 103, 115, 206 ),
	 *                     'operator' => 'NOT IN' ) ) );
	 *
	 *
	 */

	public function do_calendar( $args = array() ) {

		$this->args = $args;

		if ( ! isset( $this->args['eventDate'] ) ) {
			$this->args['eventDate'] = $this->get_month();
		}

		// don't show the list if they set it the widget option to show 0 events in the list
		if ( $this->args['count'] == 0 )	{ 
			$this->show_list = false;
		}

		// enqueue the widget js
		self::styles_and_scripts();

		// widget setting for count is not 0
		if ( $this->show_list ) {
			// if we should show the list view, set up the query
			add_action( 'tribe_pre_get_template_part_widgets/mini-calendar/list', array( $this, 'setup_list') );
	
			// enqueue the cleanup
			add_action( 'tribe_post_get_template_part_widgets/mini-calendar/list', array( $this, 'shutdown_list') );
		} else {
			add_filter( 'tribe_get_template_part_path', array( $this, 'block_list_template_path' ), 10, 2 );
		}

		tribe_show_month( array(
				'tax_query' => $this->args['tax_query'],
				'eventDate' => $this->args['eventDate'],
			), 'widgets/mini-calendar/calendar' );

	}

	/**
	 * Filter the list tepmlate paths
	 *
	 * @return string|bool
	 * @param $file the full file path
	 * @param $template the template requested
	 * @since 3.0
	 **/
	public function block_list_template_path( $file, $template ){
		if ($template == 'widgets/mini-calendar/list.php') {
			return false;
		}
		return $file;
	}

	private function styles_and_scripts() {
		wp_enqueue_script( 'tribe-mini-calendar', TribeEventsPro::instance()->pluginUrl . 'resources/widget-calendar.js', array( 'jquery' ) );

		// Tribe Events CSS filename
		$event_file = 'widget-calendar.css';
		$stylesheet_option = tribe_get_option( 'stylesheetOption', 'tribe' );

		// What Option was selected
		switch( $stylesheet_option ) {
			case 'skeleton':
				$event_file_option = 'widget-calendar-'. $stylesheet_option .'.css';
				break;
			case 'full':
				$event_file_option = 'widget-calendar-'. $stylesheet_option .'.css';
				break;
			default:
				$event_file_option = 'widget-calendar-theme.css';
				break;
		}

		$styleUrl = TribeEventsPro::instance()->pluginUrl . 'resources/' . $event_file_option;
		$styleUrl = TribeEventsTemplates::locate_stylesheet( 'tribe-events/pro/'. $event_file, $styleUrl );
		$styleUrl = apply_filters( 'tribe_events_pro_widget_calendar_stylesheet_url', $styleUrl );

		// Load up stylesheet from theme or plugin
		if( $styleUrl && $stylesheet_option == 'tribe' ) {
			wp_enqueue_style( 'widget-calendar-pro-style', TribeEventsPro::instance()->pluginUrl . 'resources/widget-calendar-full.css' );						
			wp_enqueue_style( TribeEvents::POSTTYPE . '-widget-calendar-pro-style', $styleUrl );
		} else {
			wp_enqueue_style( TribeEvents::POSTTYPE . '-widget-calendar-pro-style', $styleUrl );
		}		

		$widget_data = array( "ajaxurl" => admin_url( 'admin-ajax.php', ( is_ssl() ? 'https' : 'http' ) ) );
		wp_localize_script( 'tribe-mini-calendar', 'TribeMiniCalendar', $widget_data );
	}

	public function setup_list() {

		// make sure the widget taxonomy filter setting is respected
		add_action( 'pre_get_posts', array( $this, 'set_count' ), 1000 );

		global $wp_query;

		// hijack the main query to load the events via provided $args
		if ( !is_null( $this->args ) ) {
			$this->reset_q = $wp_query;
			$query_args = array( 
							 'posts_per_page'               => $this->args['count'],
		                     'tax_query'                    => $this->args['tax_query'],
		                     'eventDisplay'                 => 'custom',
		                     'start_date'					=> $this->get_month(),
		                     'post_status'                  => array( 'publish' ),
		                     'is_tribe_mini_calendar'       => true );


			// set end date if initial load, or ajax month switch
			if ( ! defined( 'DOING_AJAX' ) || ( defined( 'DOING_AJAX' ) && $_POST['action'] == 'tribe-mini-cal' ) ) {
				$query_args['end_date']	= substr_replace($this->get_month(), TribeDateUtils::getLastDayOfMonth( strtotime( $this->get_month() ) ), -2);
			}

			$wp_query = TribeEventsQuery::getEvents( $query_args, true );
		}
	}

	public function shutdown_list($template) {

		// reset the global $wp_query
		if ( !empty( $this->reset_q ) ) {
			global $wp_query;
			$wp_query = $this->reset_q;
		}

		// stop paying attention to the widget count setting, we're done with it
		remove_action( 'pre_get_posts', array( $this, 'set_count' ), 1000 );

	}

	public function get_tax_query_from_widget_options( $filters, $operand ) {

		if ( empty( $filters ) )
			return null;

		$tax_query = array();

		foreach ( $filters as $tax => $terms ) {
			if ( empty( $terms ) )
				continue;

			$tax_operand = 'AND';
			if ( $operand == 'OR' )
				$tax_operand = 'IN';

			$arr         = array( 'taxonomy' => $tax, 'field' => 'id', 'operator' => $tax_operand, 'terms' => $terms );
			$tax_query[] = $arr;
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = $operand;
		}

		return $tax_query;
	}

	/* Query Filters */

	public function set_count( $query ) {

		$count = !empty( $this->args['count'] ) ? $this->args['count'] : 5;
		$query->set( 'posts_per_page', $count );

		return $query;
	}

	public function set_taxonomies( $query ) {

		if ( !empty( $this->args['tax_query'] ) ) {
			$query->set( 'tax_query', $this->args['tax_query'] );
		}

		return $query;
	}

	public function ajax_change_month_set_date( $query ) {

		if ( isset( $_POST["eventDate"] ) && $_POST["eventDate"] ) {
			// $query->set( 'eventDate', $_POST["eventDate"] . '-01' );
			// $query->set( 'start_date', $_POST["eventDate"] . '-01' );
			$query->set( 'end_date', date( 'Y-m-d', strtotime( TribeEvents::instance()->nextMonth( $_POST["eventDate"] . '-01' ) ) - ( 24 * 3600 ) ) );
			$query->set( 'eventDisplay', 'month' );
		}
		return $query;
	}

	public function ajax_select_day_set_date( $query ) {

		if ( isset( $_POST["eventDate"] ) && $_POST["eventDate"] ) {
			$query->set( 'eventDate', $_POST["eventDate"] );
			$query->set( 'eventDisplay', 'day' );
			$query->set( 'start_date', tribe_event_beginning_of_day( $_POST["eventDate"] ) );
			$query->set( 'end_date', tribe_event_end_of_day( $_POST["eventDate"] ) );
			$query->set( 'hide_upcoming', false );
		}
		return $query;
	}


	public static $instance;

	/**
	 * Get (and instantiate, if necessary) the instance of the class
	 *
	 * @static
	 * @return TribeEventsMiniCalendar
	 */
	public static function instance() {
		if ( !is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

}
