<?php
/**
Plugin Name: Adopt-a-Pet API Integration
Description: Pulls cat adoption data from Adopt-a-Pet
Version: 1.0
Author: Andrew Mowe, Team Eight
License: GPLv2 or later
Text Domain: adopt-a-pet
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once( ABSPATH . "wp-includes/pluggable.php" );

// Guzzle
require 'vendor/autoload.php';

use GuzzleHttp\Client;


// Register the menu
add_action( "admin_menu", "aap_plugin_menu_func" );
function aap_plugin_menu_func() {
   add_submenu_page( "options-general.php",  // Which menu parent
                  "Adopt-a-Pet",            // Page title
                  "Adopt-a-Pet",            // Menu title
                  "manage_options",       // Minimum capability (manage_options is an easy way to target administrators)
                  "adopt-a-pet",            // Menu slug
                  "aap_plugin_options"     // Callback that prints the markup
               );
}

// Print the markup for the page
function aap_plugin_options() {
   if ( !current_user_can( "manage_options" ) )  {
      wp_die( __( "You do not have sufficient permissions to access this page." ) );
    }

if ( isset($_GET['status']) && $_GET['status']=='success') {
?>
   <div id="message" class="updated notice is-dismissible">
      <p><?php _e("Cats updated!", "adopt-a-pet"); ?></p>
      <button type="button" class="notice-dismiss">
         <span class="screen-reader-text"><?php _e("Dismiss this notice.", "adopt-a-pet"); ?></span>
      </button>
   </div>

<?php } ?>

<form method="post" action="<?php echo admin_url( 'admin-post.php'); ?>">

   <input type="hidden" name="action" value="get_aap_cats" />

   <h3><?php _e("Refresh Adopt-a-Pet cats", "adopt-a-pet"); ?></h3>

   <input class="button button-primary" type="submit" value="<?php _e("Update", "adopt-a-pet"); ?>" />

</form>
   <?php
}

add_action( 'admin_post_get_aap_cats', 'get_aap_cats' );

if ( !function_exists('is_user_logged_in') ) :
/**
 * Checks if the current visitor is a logged in user.
 *
 * @since 2.0.0
 *
 * @return bool True if user is logged in, false if not logged in.
 */
function is_user_logged_in() {
	$user = wp_get_current_user();

	if ( empty( $user->ID ) )
		return false;

	return true;
}
endif;

function get_aap_cats() {

	$client = new Client([
		'base_uri' => 'http://api.adoptapet.com/search/'
	]);

	$response = $client->request('GET', 'pets_at_shelter?key=88cdacc2a0538c4708a7d2304ac328ab&shelter_id=72966&output=json');

	$code = $response->getStatusCode();
	$body = $response->getBody();

	$aap_cats = json_decode( $body );

	$aap_cats = $aap_cats->pets;

	$aap_cat_ids = array();

	foreach ( $aap_cats as $cat ) {

		$aap_cat_ids[] = $cat->pet_id;

		$name = $cat->pet_name;
		$order = $cat->order;
		$pet_id = $cat->pet_id;


		// echo '<h1>'.$pet_id.'</h1>';

		$response = $client->request('GET', 'pet_details?key=88cdacc2a0538c4708a7d2304ac328ab&output=json&pet_id=' . $pet_id );

		$code = $response->getStatusCode();
		$body = $response->getBody();

		$cat_info = json_decode( $body );
		$cat_info = $cat_info->pet;
		$color = $cat_info->color;
		$sex = $cat_info->sex;
		$hair = $cat_info->hair_length;
		$age = $cat_info->age;
		$desc = $cat_info->description;

		$existing_cat = get_posts( array(
				'post_type'		=> 'cats',
				'meta_query'	=> array(
					array(
						'key'			=> 'pet_id',
						'value'		=> $pet_id,
						'compare'	=> '='
					)
				)
			)
		);

		if ( $existing_cat ) :
			$cat_id = intval($existing_cat[0]->ID);
		else :
			$cat_id = 0;
		endif;

		// echo '<h2>'.$cat_id.'</h2>';

		$args = array(
				'ID'					=> $cat_id,
				'post_type'		=> 'cats',
				'post_title'	=> $name,
				'menu_order'	=> $order,
				'post_status'	=> 'publish',
				'meta_input'	=> array(
						'pet_id'				=> $pet_id,
						'pet_data'			=> $cat_info,
						'availability'	=> 'available',
						'color'					=> $color,
						'sex'						=> $sex,
						'age'						=> $age,
						'hair'					=> $hair,
						'description'		=> $desc
					)
			);

		wp_insert_post( $args );

		wp_set_object_terms( $cat_id, 'available', 'availability' );

	}

		$args = array(
			'posts_per_page' => -1,
			'post_type'	=> 'cats',
			'meta_query'	=> array(
				array(
					'key'	=> 'availability',
					'value'	=> 'available',
					'compare'	=> '='
				)
			)
		);

		$available_cats = get_posts( $args );

		foreach ( $available_cats as $available_cat ) {

			// get the pet id for currently available cats
			$pet_id = get_post_meta( $available_cat->ID, 'pet_id', true );

			if ( in_array( $pet_id, $aap_cat_ids ) ) {
				// still available
			} else {
				// not available anymore
				update_post_meta( $available_cat->ID, 'availability', 'unavailable' );
				wp_set_object_terms( $available_cat->ID, 'unavailable', 'availability' );
			}
		}

		// echo '<pre>';
		// print_r($aap_cat_ids);
		// echo '</pre>';

		// echo '<pre>';
		// print_r($available_cats);
		// echo '</pre>';

		// Redirect back to settings page
		$redirect_url = get_bloginfo("url") . "/wp-admin/options-general.php?page=adopt-a-pet&status=success";
		header("Location: ".$redirect_url);
		exit;

}

// get_aap_cats();
