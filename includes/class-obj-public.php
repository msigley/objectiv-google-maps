<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public Views class
 *
 * @author      Wes Cole
 * @category    Class
 * @package     ObjectivGoogleMaps/Classes
 * @since       1.0
 */
class Obj_Gmaps_Public {

    public function __construct( $file, $version ) {

        $this->file = $file;
        $this->version = $version;

        add_action( 'init', array( $this, 'add_map_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_js' ) );

    }

	/**
     * Enqueue JS
     *
     * @since 1.0
     */
    public function enqueue_js() {

        if ( obj_has_shortcode( 'objectiv_google_maps' ) ) {
            wp_enqueue_script( 'obj-google-maps', plugins_url( '/assets/js/build/main.js', $this->file ), array(), $this->version, true );
			wp_enqueue_style( 'obj-google-maps-style', plugins_url( '/assets/css/public/public.css', $this->file ), array(), $this->version );
        }

    }

    /**
     * Add map shortcode
     *
     * @since 1.0
     */
    public function add_map_shortcode() {

        add_shortcode( 'objectiv_google_maps', array( $this, 'map_shortcode_markup' ) );

    }

    /**
     * Create Map Shortcode Markup
     *
     * @since 1.0
     */
    public function map_shortcode_markup() {
		$selected_post_type = get_option( 'obj_post_type' );
		$height = get_option( 'obj_map_height' );

		$posts_arg = array(
			'post_type'	=> $selected_post_type,
			'posts_per_page'	=> -1
		);

		$post_type_object = get_post_type_object( $selected_post_type );
		$post_type_object_labels = $post_type_object->labels;

		$posts = get_posts( $posts_arg );

		foreach( $posts as $key => $post ) {
			$lat = get_post_meta( $post->ID, 'obj_location_lat', true );
			$lng = get_post_meta( $post->ID, 'obj_location_lng', true );

			if ( $lat && $lng ) {
				$posts[$key]->lat = $lat;
				$posts[$key]->lng = $lng;
				$posts[$key]->permalink = get_the_permalink( $post->ID );
				$posts[$key]->post_type_label = $post_type_object_labels->singular_name;
				$posts[$key]->address = get_post_meta( $post->ID, 'obj_google_address', true );
			}
		}

		$data_array = array(
			'apiKey'	=> get_option( 'obj_api_key' ),
			'mapType'	=> get_option( 'obj_map_type' ),
			'mapCenter'	=> get_option( 'obj_map_center' ),
			'mapZoom'	=> get_option( 'obj_map_zoom' ),
			'locations'	=> $posts
		);

		if ( obj_has_shortcode( 'objectiv_google_maps' ) ) {
			wp_localize_script( 'obj-google-maps', 'data', $data_array );
		}

        ob_start();

		echo '<div id="obj-google-map-wrap">';
		echo '<input id="obj-search-input" class="controls" type="text" placeholder="Search by city...">';
        echo '<div id="obj-google-maps" style="height:' . $height . ';"></div>';
		echo '</div>';

        return ob_get_clean();

    }

}
