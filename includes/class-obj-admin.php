<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main admin class
 *
 * @author      Wes Cole
 * @category    Class
 * @package     ObjectivGoogleMaps/Classes
 * @since       1.0
 */
class Obj_Gmaps_Admin {

    public function __construct( $file ) {

		require_once 'class-obj-uibuilder.php';

        $this->file = $file;
		$this->dir = dirname( $file );
		$this->uibuilder = new Obj_Gmaps_UIBuilder( 'obj_location' );

        // Activation and Deactivation Hooks
        register_activation_hook( $file, array( $this, 'activate_plugin' ) );
		register_deactivation_hook( $file, array( $this, 'deactivate_plugin' ) );

		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_js' ) );
			add_action( 'admin_init', array( $this, 'metaboxes_setup' ) );
		}

    }

    /**
     * Activation callback
     */
    public function activate_plugin() {
        flush_rewrite_rules();
    }

    /**
     * Deactivation Callback
     */
    public function deactivate_plugin() {
        flush_rewrite_rules();
    }

	/**
     * Enqueue JS
     *
     * @since 1.0
     */
    public function enqueue_js( $hook ) {
		$screen = get_current_screen();
		$selected_post_type = get_option( 'obj_post_type' );

		$data_array = array(
			'api_key'	=> get_option( 'obj_api_key' )
		);

		if ( $hook == 'settings_page_obj_google_map_settings' || $screen->post_type == $selected_post_type ) {
			wp_enqueue_script( 'obj-google-maps-admin', plugins_url( '/assets/js/admin/build/main.js', $this->file ), array(), $this->version, true );
			wp_localize_script( 'obj-google-maps-admin', 'data', $data_array );
		}

    }

	/**
	 * Register address metabox
	 *
	 * @since 1.0
	 */
	public function metaboxes_setup () {
		$selected_post_type = get_option( 'obj_post_type' );
		if( !empty($selected_post_type) ) {
			add_action( 'add_meta_boxes_'.$selected_post_type, array( $this, 'create_metabox' ), 10, 1 );
			add_action( 'save_post_'.$selected_post_type, array( $this, 'save_post_validate' ), 9, 2 );
			add_action( 'save_post_'.$selected_post_type, array( $this, 'verify_wp_nonces' ), 10, 2 );
			add_action( 'save_post_'.$selected_post_type, array( $this, 'save_metabox' ), 11, 1 );
			add_action( 'save_post_'.$selected_post_type, array( $this, 'save_location_lat_long' ), 12, 1 );
		}
	}

	/**
	 * Create metabox
	 *
	 * @since 1.0
	 */
	public function create_metabox( $post ) {
		add_meta_box(
			'obj-google-address',
			__( 'Google Maps Address', 'obj-google-maps' ),
			array( $this, 'metabox_content' ),
			$post->post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Create metabox content
	 *
	 * @since 1.0
	 */
	public function metabox_content( $object ) {
		wp_nonce_field( 'obj_google_save', 'obj_google_save_nonce' );
		?>
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="autocomplete"><?php _e( "Address", 'obj-google-maps' ); ?></label>
					</th>
					<td>
						<input class="widefat" type="text" name="obj-google-address" id="autocomplete" value="<?php echo esc_attr( get_post_meta( $object->ID, 'obj_google_address', true ) ); ?>" size="30" />
					</td>
				</tr>
				<?php
				$custom_post_meta = array();
				$custom_post_meta = apply_filters( 'obj_location_post_meta', $custom_post_meta );
				// Supported field types: date, textbox, url, email, hidden, textarea
				// TODO: Add support for checkbox, number, and selectbox field types. UI functions exist but the value logic below will not work for them.
				foreach( $custom_post_meta as $meta_key => $field_array ) {
					if( empty($field_array['type']) || empty($field_array['label']) 
						|| !is_callable( array($this->uibuilder, $field_array['type']) ) )
						continue;

					$meta_value = get_post_meta( $object->ID, $this->uibuilder->get_name_id($meta_key), true );
					?>
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->uibuilder->get_name_id($meta_key); ?>"><?php _e( $field_array['label'], 'obj-google-maps' ); ?></label>
						</th>
						<td>
							<?php echo $this->uibuilder->{$field_array['type']}( $meta_key, $meta_value, 'widefat' ); ?>
						</td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php
	}

	public function save_post_validate( $post_id, $post ) {
		if( !$this->is_valid_post_save($post) ) {
			//Remove post saving actions
			$post_type = $post->post_type;
			remove_action( 'save_post_'.$post_type, array( $this, 'verify_wp_nonces' ), 10 );
			remove_action( 'save_post_'.$post_type, array( $this, 'save_metabox' ), 11 );
			remove_action( 'save_post_'.$post_type, array( $this, 'save_location_lat_long' ), 12 );
		}
	}

	private function is_valid_post_save($post) {
		//Check for auto saves, creating a new post, no post array, and unhandled post types
		if( is_array($post) )
			$post = (object) $post;
		if( empty($_POST) 
			|| 'auto-draft' == $post->post_status
			|| 'trash' == $post->post_status
			|| (defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE) )
			return false;
		return true;
	}

	public function verify_wp_nonces( $post_id, $post ) {
		if( !$this->is_valid_post_save($post) )
			return; //Do nothing
		
		if( !isset( $_POST['obj_google_save_nonce'] ) )
			$this->display_error('verify_nonce', 'Unable to verify security nonce.');
		
		check_admin_referer( 'obj_google_save', 'obj_google_save_nonce' );
	}

	public function save_location_lat_long( $post_id ) {
	    // - Update the post's metadata.
	    if ( isset( $_POST['obj-google-address'] ) ) {
	        $address = $_POST['obj-google-address'];
	        $string = str_replace (" ", "+", urlencode( $address ) );
	        $url = "http://maps.googleapis.com/maps/api/geocode/json?address=".$string."&sensor=false";

	        $response = wp_remote_get( $url );
	        $data = wp_remote_retrieve_body( $response );
	        $output = json_decode( $data );
	        if (!empty($output) && $output->status == 'OK') {
				$address_components = $output->results[0]->address_components;
	            $geometry = $output->results[0]->geometry;
	            $longitude = $geometry->location->lng;
	            $latitude = $geometry->location->lat;
				update_post_meta( $post_id, 'obj_location_address_components', $address_components );
	            update_post_meta( $post_id, 'obj_location_lat', $latitude );
	            update_post_meta( $post_id, 'obj_location_lng', $longitude );
	        }
	    }
	}

	/**
	 * Save Metaboxes
	 *
	 * @since 1.0
	 */
	public function save_metabox( $post_id ) {
		//Save location address
		$obj_google_address = '';
		if( isset( $_POST['obj-google-address'] ) )
			$obj_google_address = sanitize_text_field( $_POST['obj-google-address'] );
		update_post_meta( $post_id, 'obj_google_address', $obj_google_address );

		//Save location post meta
		$custom_post_meta = array();
		$custom_post_meta = apply_filters( 'obj_location_post_meta', $custom_post_meta );
		// Supported field types: date, textbox, url, email, hidden, textarea
		// TODO: Add support for checkbox, number, and selectbox field types. UI functions exist but the saving logic below will not work for them.
		foreach( $custom_post_meta as $meta_key => $field_array ) {
			$meta_value = '';
			if( isset( $_POST[$this->uibuilder->get_name_id($meta_key)] ) )
				$meta_value = sanitize_text_field( $_POST[$this->uibuilder->get_name_id($meta_key)] );
			update_post_meta( $post_id, $this->uibuilder->get_name_id($meta_key), $meta_value );
		}
	}

	private function display_error($code, $message) {
		wp_die( new WP_Error('obj_google_'.$code, 'Objectiv Google Maps: '.$message) );
	}
}
