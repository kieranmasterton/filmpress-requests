<?php
/*
Plugin Name: FilmPress Requests
Plugin URI: http://filmpress.org
Description: FilmPress plugin that allows you to collect requests for screenings of you film.
Version: 0.1
Author: Kieran Masterton
Author URI: http://kieranmasterton.com
License: GPL2+
*/

class FilmPress_Requests {
    
    // Run.
    function init() {
        add_action( 'admin_enqueue_scripts', array('FilmPress_Requests', 'admin_styles') );
        add_action( 'admin_menu', array('FilmPress_Requests', 'register_submenu_pages') );
        
        
        FilmPress_Requests::register_requests_post_type();
        // Add filter to rewrite post titles
        add_filter( 'wp_insert_post_data' , array('FilmPress_Requests', 'rewrite_request_title') , '100', 2 );
        // Add filter to hijack map page template
        add_filter( 'the_content', array('FilmPress_Requests','map_page_content') );
        
        // Add action to set lat/long
        add_action('save_post', array('FilmPress_Requests','geocode'));  
        
    }
    
    // Runs upon activation.
    static function plugin_activation(){
        FilmPress_Requests::create_map_page();
    }
    
    // Runs upon deactivation.
    function plugin_deactivation(){
        FilmPress_Requests::delete_map_page();
    }
    
    function register_submenu_pages(){
        add_submenu_page( 'edit.php?post_type=filmpress-request', 'Requests Map', 'Requests Map', 'manage_options', 'requests-map', array('FilmPress_Requests', 'admin_submenu_map_page_callback') );
    }
    
    // Request map admin page
    function admin_submenu_map_page_callback(){
        echo '<h3>Requests Map</h3>';
        return FilmPress_Requests::display_map();
    }
    
    function display_map(){
        echo "<script src=\"http://maps.google.com/maps/api/js?sensor=false\"></script>
        <script type=\"text/javascript\">
        
        var data = {\"requests\":[";
            
        $requests_query = new WP_Query( array( 'post_type' => 'filmpress-request') );
            
        while ( $requests_query->have_posts() ) : $requests_query->the_post();
            echo "{\"longitude\":" . get_post_meta(get_the_ID(), "filmpress-request-longitude", TRUE) . ", \"latitude\":"  . get_post_meta(get_the_ID(), "filmpress-request-latitude", TRUE) . "},";
        endwhile;
        
    	echo "]}
    	
    	var script = '<script type=\"text/javascript\" src=\"/wp-content/plugins/filmpress-requests/js/markerclusterer';
          if (document.location.search.indexOf('compiled') !== -1) {
            script += '_compiled';
          }
          script += '.js\"><' + '/script>';
          document.write(script);

          function initialize() {
            var center = new google.maps.LatLng(0, 0);

            var map = new google.maps.Map(document.getElementById('map'), {
              zoom: 2,
              center: center,
              mapTypeId: google.maps.MapTypeId.ROADMAP
            });

            var markers = [];
    		for (var i in data['requests']) {
    		  if (data['requests'].hasOwnProperty(i)) {

              var request = data['requests'][i];
              var latLng = new google.maps.LatLng(request.latitude,
                  request.longitude);
              var marker = new google.maps.Marker({
                position: latLng
              });
              markers.push(marker);
            }
    	}
            var markerCluster = new MarkerClusterer(map, markers);
          }
          google.maps.event.addDomListener(window, 'load', initialize);
        </script>
        <div id=\"map-container\"><div id=\"map\" style=\"width: 600px;height: 400px;\"></div></div>

";
    }
    
    function geocode($post_id){
        if($_POST['filmpress-request-postal-code']){
           $string = str_replace (" ", "+", urlencode($_POST['filmpress-request-postal-code'] . ', GB'));
           $details_url = "http://maps.googleapis.com/maps/api/geocode/json?address=".$string."&sensor=false";

           $ch = curl_init();
           curl_setopt($ch, CURLOPT_URL, $details_url);
           curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
           $response = json_decode(curl_exec($ch), true);

           // If Status Code is ZERO_RESULTS, OVER_QUERY_LIMIT, REQUEST_DENIED or INVALID_REQUEST
           if ($response['status'] != 'OK') {
            return $data;
           }

           $geometry = $response['results'][0]['geometry'];    
           
           $lat = (string)$geometry['location']['lat'];
           $lng = (string)$geometry['location']['lng'];
           
           update_post_meta($post_id, 'filmpress-request-latitude', $lat);
           update_post_meta($post_id, 'filmpress-request-longitude', $lng);

        }
        
        return $data;       
    }
    
    function admin_styles() {
        wp_register_style('filmpress_admin_styles', plugins_url() . '/filmpress-requests/css/admin.css');
        wp_enqueue_style('filmpress_admin_styles');
    }
    
    // Register requests post type.
    function register_requests_post_type() {
        
        // Register the custom requests type.
        $labels = array(
            'name' => _x('Requests', 'post type general name'),
            'singular_name' => _x('request', 'post type singular name'),
            'add_new' => _x('Add New', 'request'),
            'add_new_item' => __('Add New request'),
            'edit_item' => __('Edit Request'),
            'new_item' => __('New Request'),
            'all_items' => __('All Request'),
            'view_item' => __('View Request'),
            'search_items' => __('Search Requests'),
            'not_found' =>  __('No requests found'),
            'not_found_in_trash' => __('No requests found in Trash'), 
            'parent_item_colon' => '',
            'menu_name' => 'Film Requests'
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true, 
            'show_in_menu' => true, 
            'query_var' => true,
            'rewrite' => array('slug' => 'request', 
        	                    'with_front' => true),
            'capability_type' => 'post',
            'has_archive' => true, 
            'hierarchical' => false,
            'menu_position' => 5,
            'supports' => array('name')
        );

        register_post_type('filmpress-request', $args);
        
        // Hook it up
        FilmPress_Requests::add_request_meta_box_hooks();
        
        
    }
    
    function add_request_meta_box_hooks(){
        // Add meta boxes
        add_action('admin_menu', array('FilmPress_Requests','add_requests_meta_boxes'));
        add_action('save_post', array('FilmPress_Requests','save_request_postal_code_meta_box'));
        add_action('save_post', array('FilmPress_Requests','save_request_email_meta_box')); 
        add_action('save_post', array('FilmPress_Requests','save_request_longitude_meta_box')); 
        add_action('save_post', array('FilmPress_Requests','save_request_latitude_meta_box'));   
    }
    
    // Add requests meta boxes.
    function add_requests_meta_boxes() {
        
            // Email address meta box
        	add_meta_box(
        		'filmpress_request_email', // div id
        		'Email Address', // title
        		array('FilmPress_Requests','populate_request_email_meta_box'), // callback function
        		'filmpress-request', // post type
        		'advanced'
        	);
        	
        	// Postal code meta box
        	add_meta_box(
        		'filmpress_request_postal_code', // div id
        		'Postal / Zip Code ', // title
        		array('FilmPress_Requests','populate_request_postal_code_meta_box'), // callback function
        		'filmpress-request', // post type
        		'advanced'
        	);
        	
        	// Longitude meta box
        	add_meta_box(
        		'filmpress_request_longitude', // div id
        		'Longitude', // title
        		array('FilmPress_Requests','populate_request_longitude_meta_box'), // callback function
        		'filmpress-request', // post type
        		'advanced'
        	);
        	
        	// Latitude meta box
        	add_meta_box(
        		'filmpress_request_latitude', // div id
        		'Latitude', // title
        		array('FilmPress_Requests','populate_request_latitude_meta_box'), // callback function
        		'filmpress-request', // post type
        		'advanced'
        	);
        
    }
    
    // Callback to populate postal code meta box.
    function populate_request_postal_code_meta_box()
    {
        // Fetch the post data.
        global $post;

        $base = null;

        // Use nonce for verification
        echo '<input type="hidden" 
                        name="filmpress-request-postal-code-section-id" 
                        id="filmpress-request-postal-code-section-id" 
                        value="' . wp_create_nonce(plugin_basename(__FILE__)) . '" />';

        // The actual fields for data entry.
        $value = get_post_meta($post->ID, 'filmpress-request-postal-code', true);
        $base .= '<label class="screen-reader-text" 
                            for="filmpress-request-postal-code">Name</label>
                    <input type="text"
                            value="' . $value . '"
                            name="filmpress-request-postal-code" 
                            tabindex="6" 
                            id="filmpress-request-postal-code"
                            style="width:80%;">';

        echo $base;
    }
    
    // Callback to populate email meta box.
    function populate_request_email_meta_box()
    {
        // Fetch the post data.
        global $post;

        $base = null;

        // Use nonce for verification
        echo '<input type="hidden" 
                        name="filmpress-request-email-section-id" 
                        id="filmpress-request-email-section-id" 
                        value="' . wp_create_nonce(plugin_basename(__FILE__)) . '" />';

        // The actual fields for data entry.
        $value = get_post_meta($post->ID, 'filmpress-request-email', true);
        $base .= '<label class="screen-reader-text" 
                            for="filmpress-request-email">Name</label>
                    <input type="text"
                            value="' . $value . '"
                            name="filmpress-request-email" 
                            tabindex="6" 
                            id="filmpress-request-email"
                            style="width:80%;">';

        echo $base;
    }
    
    
    // Callback to populate longitude meta box.
    function populate_request_longitude_meta_box()
    {
        // Fetch the post data.
        global $post;

        $base = null;

        // Use nonce for verification
        echo '<input type="hidden" 
                        name="filmpress-request-longitude-section-id" 
                        id="filmpress-request-longitude-section-id" 
                        value="' . wp_create_nonce(plugin_basename(__FILE__)) . '" />';

        // The actual fields for data entry.
        $value = get_post_meta($post->ID, 'filmpress-request-longitude', true);
        $base .= '<label class="screen-reader-text" 
                            for="filmpress-request-longitude">Name</label>
                    <input type="text"
                            value="' . $value . '"
                            name="filmpress-request-longitude" 
                            tabindex="6" 
                            id="filmpress-request-longitude"
                            style="width:80%;" disabled="disabled">';

        echo $base;
    }
    
    // Callback to populate latitude meta box.
    function populate_request_latitude_meta_box()
    {
        // Fetch the post data.
        global $post;

        $base = null;

        // Use nonce for verification
        echo '<input type="hidden" 
                        name="filmpress-request-latitude-section-id" 
                        id="filmpress-request-latitude-section-id" 
                        value="' . wp_create_nonce(plugin_basename(__FILE__)) . '" />';

        // The actual fields for data entry.
        $value = get_post_meta($post->ID, 'filmpress-request-latitude', true);
        $base .= '<label class="screen-reader-text" 
                            for="filmpress-request-latitude">Name</label>
                    <input type="text"
                            value="' . $value . '"
                            name="filmpress-request-latitude" 
                            tabindex="6" 
                            id="filmpress-request-latitude"
                            style="width:80%;"  disabled="disabled">';

        echo $base;
    }
    
        // Callback to save requests meta boxes.
     function save_request_postal_code_meta_box($post_id){

           if (!wp_verify_nonce( $_POST['filmpress-request-postal-code-section-id'], plugin_basename(__FILE__))) {
               return $post_id;
           }

           if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ){
               return $post_id;
           }

           if ('post' == $_POST['post_type']) {
               if (!current_user_can('edit_post', $post_id)){
                   return $post_id;
               }
           }

           $new_data = strip_tags($_POST['filmpress-request-postal-code']);

           update_post_meta($post_id, 'filmpress-request-postal-code', $new_data);
       }
       
       // Callback to save requests meta boxes.
      function save_request_email_meta_box($post_id){

          if (!wp_verify_nonce( $_POST['filmpress-request-email-section-id'], plugin_basename(__FILE__))) {
              return $post_id;
          }

          if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ){
              return $post_id;
          }

          if ('post' == $_POST['post_type']) {
              if (!current_user_can('edit_post', $post_id)){
                  return $post_id;
              }
          }

          $new_data = strip_tags($_POST['filmpress-request-email']);

          update_post_meta($post_id, 'filmpress-request-email', $new_data);
      }
      
      // Callback to save requests meta boxes.
        function save_request_longitude_meta_box($post_id){

            if (!wp_verify_nonce( $_POST['filmpress-request-longitude-section-id'], plugin_basename(__FILE__))) {
                return $post_id;
            }

            if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ){
                return $post_id;
            }

            if ('post' == $_POST['post_type']) {
                if (!current_user_can('edit_post', $post_id)){
                    return $post_id;
                }
            }

            $new_data = strip_tags($_POST['filmpress-request-longitude']);

            update_post_meta($post_id, 'filmpress-request-longitude', $new_data);
        }
        
        // Callback to save requests meta boxes.
            function save_request_latitude_meta_box($post_id){

                if (!wp_verify_nonce( $_POST['filmpress-request-latitude-section-id'], plugin_basename(__FILE__))) {
                    return $post_id;
                }

                if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ){
                    return $post_id;
                }

                if ('post' == $_POST['post_type']) {
                    if (!current_user_can('edit_post', $post_id)){
                        return $post_id;
                    }
                }

                $new_data = strip_tags($_POST['filmpress-request-latitude']);

                update_post_meta($post_id, 'filmpress-request-latitude', $new_data);
            }
       
       
       // Rewrite the post title
       function rewrite_request_title($data, $postarr) {
           $data['post_title'] = $postarr['filmpress-request-email'];

           return $data;
        }
        
        // Creates maps page.
        function create_map_page(){
            $args = array(
              'name' => 'film-request-map',
              'post_type' => 'page',
              'post_status' => 'publish',
              'showposts' => 1,
              'caller_get_posts'=> 1
            );
            $post = get_posts($args);
            
            if( $post ) {
                wp_delete_post($post[0]->ID, TRUE);
            }

            // Create post object
            $map_page['post_title'] = 'Film Request Map';
            $map_page['post_content'] = "Don't edit or delete this page. It is used by FilmPress Requests to display your film requests map.";
            $map_page['post_status'] = 'publish';
            $map_page['post_type'] = 'page';
            $map_page['comment_status'] = 'closed';
            $map_page['ping_status'] = 'closed';
            $map_page['post_name'] = 'film-request-map';
            $map_page['post_category'] = array(1); // the default 'Uncatrgorised'

            // Insert the post into the database
            $map_page_id = wp_insert_post( $map_page );

        }
        
        // Delete maps page.
        function delete_map_page(){
            $args = array(
              'name' => 'film-request-map',
              'post_type' => 'page',
              'post_status' => 'publish',
              'showposts' => 1,
              'caller_get_posts'=> 1
            );
            $post = get_posts($args);
     
            if( $post ) {
                wp_delete_post($post[0]->ID, TRUE);
            }
        }
        
        // Function to hijack map page template.
        function map_page_content( $the_content )
        {
 
            if ( is_page( 'film-request-map' ) ) {
                $the_content = FilmPress_Requests::display_map();
            }
            return $the_content;
        }
    
}

// Register activation / deactivation hooks.

register_activation_hook( __FILE__, array('FilmPress_Requests', 'plugin_activation'));
register_deactivation_hook( __FILE__, array( 'FilmPress_Requests', 'plugin_deactivation' ) );

// Run.
add_action( 'init', array( 'FilmPress_Requests', 'init' ) );