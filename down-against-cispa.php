<?php
/*
Plugin Name: Down Against CISPA
Plugin URI: http://downagainstsopa.com
Description: Down Against Cispa displays a splash page on your WordPress site April 22nd in protest of the Stop Online Piracy Act. Several configuration options are available.
Version: 1.0.6
Author: Ten-321 Enterprises
Author URI: http://ten-321.com
License: GPL3
*/
/*  Copyright 2012  Ten-321 Enterprises and Chris Tidd  (email : contact@ctidd.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/    
?>
<?php
/**
 * Determine if the CISPA message should be shown and, if so, do it.
 */
function cispa_redirect() {
	/* Don't redirect if this is the admin area - somewhat redundant, but helpful nonetheless */
	if ( is_admin() )
		return;
	
	$cispa_opts = get_cispa_options();
	$blackout_dates = array_map( 'trim', explode( ',', $cispa_opts['blackout_dates'] ) );
	
	if ( ! empty( $cispa_opts['custom_page'] ) && is_page( $cispa_opts['custom_page'] ) )
		return;
	
	$cookiename = 'seen_cispa_blackout';
	if ( array_key_exists( 'cookie_hash', $cispa_opts ) )
		$cookiename .= '_' . $cispa_opts['cookie_hash'];
	
	/* Don't redirect if they've already seen the blackout page this session */
	if ( isset( $_COOKIE ) && array_key_exists( $cookiename, $_COOKIE ) && empty( $cispa_opts['no_cookie'] ) ) {
		/*wp_die( 'The cookie is already set' );*/
		return;
	}
	/* Don't redirect if this isn't the home page or front page */
	if ( ! is_front_page() && ! is_home() && empty( $cispa_opts['all_pages'] ) ) {
		if ( ! empty( $cispa_opts['page_id'] ) && ! is_page( $cispa_opts['page_id'] ) ) {
			/*wp_die( 'This is not the home/front page' );*/
			return;
		}
	}
	
	// On January 23, 2012 redirect traffic to the protest page.
	if ( is_cispa_message_displayed() ) {
		$qs = ! empty( $cispa_opts['continue_to_dest'] ) ? '?redirect_to=' . urlencode( $_SERVER['REQUEST_URI'] ) : '';
		$cookiename = 'seen_cispa_blackout';
		if ( array_key_exists( 'cookie_hash', $cispa_opts ) )
			$cookiename .= '_' . $cispa_opts['cookie_hash'];
		// Meta refresh is the only redirect technique I found consistent enough. It has drawbacks, but it's reliable and simple.
		/*wp_safe_redirect( plugins_url( 'stop-cispa.php', __FILE__ ) );*/
		if ( empty( $cispa_opts['custom_page'] ) && ( empty( $cispa_opts['page_id'] ) || ! is_numeric( $cispa_opts['page_id'] ) ) ) {
			if ( empty( $cispa_opts['no_cookie'] ) )
				setcookie( $cookiename, 1, 0, '/' );
			wp_safe_redirect( plugins_url( 'stop-cispa.php', __FILE__ ) . $qs, 307 );
		} else if ( ! empty( $cispa_opts['custom_page'] ) ) {
			if ( empty( $cispa_opts['no_cookie'] ) )
				setcookie( $cookiename, 1, 0, '/' );
			wp_safe_redirect( get_permalink( $cispa_opts['custom_page'] ) . $qs, 307 );
		} else if ( is_page( $cispa_opts['page_id'] ) ) {
			if ( empty( $cispa_opts['no_cookie'] ) )
				setcookie( $cookiename, 1, 0, '/' );
			include_once( plugin_dir_path( __FILE__ ) . 'stop-cispa.php' );
		} else {
			wp_safe_redirect( get_permalink( $cispa_opts['page_id'] ) . $qs, 307 );
		}
		die();
	}
}
add_action( 'template_redirect', 'cispa_redirect', 99 );

/**
 * Retrieve the options
 */
function get_cispa_options() {
	$cispa_opts = get_option( 'cispa_blackout_dates', '2013-04-22' );
	if ( ! is_array( $cispa_opts ) )
		$cispa_opts = array( 'blackout_dates' => $cispa_opts );
	
	$cispa_opts = array_merge( array(
		'blackout_dates' => '2013-04-22',
		'backlinks'      => 0,
		'all_pages'      => 0,
		'no_cookie'      => 0,
		'page_id'        => null,
		'site_link'      => null,
		'nag'            => 1,
		'continue_to_dest' => 0,
		'custom_page'    => 0,
	), $cispa_opts );
	
	return $cispa_opts;
}

/**
 * Determine whether the blackout dates indicate the CISPA message should be displayed
 * @return bool whether or not the current date is in the list of blackout dates
 */
function is_cispa_message_displayed() {
	$cispa_opts = get_cispa_options();
	$blackout_dates = array_map( 'trim', explode( ',', $cispa_opts['blackout_dates'] ) );
	
	$time = @date( "Y-m-d", current_time( 'timestamp' ) );
	return in_array( $time, $blackout_dates );
}

/**
 * Add the CISPA options page to the administration menu
 */
function add_cispa_options_page() {
	add_submenu_page( 'options-general.php', __( 'CISPA Blackout Options' , 'cispa-blackout-plugin'), __( 'CISPA Options' , 'cispa-blackout-plugin'), 'manage_options', 'cispa_options_page', 'cispa_options_page_callback' );
	add_action( 'admin_init', 'register_cispa_options' );
}
add_action( 'admin_menu', 'add_cispa_options_page' );

/**
 * Whitelist the CISPA options and set up the options page
 */
function register_cispa_options() {
	register_setting( 'cispa_options_page', 'cispa_blackout_dates', 'sanitize_cispa_opts' );
	add_settings_section( 'cispa_options_section', __( 'CISPA Blackout Options' , 'cispa-blackout-plugin'), 'cispa_options_section_callback', 'cispa_options_page' );
	
	add_settings_field( 'cispa_blackout_dates', __( 'Blackout dates:' , 'cispa-blackout-plugin'), 'cispa_options_field_callback', 'cispa_options_page', 'cispa_options_section', array( 'label_for' => 'cispa_blackout_dates', 'field_name' => 'blackout_dates' ) );
	add_settings_field( 'cispa_hide_backlinks', __( 'Remove backlinks to plugin sponsors?' , 'cispa-blackout-plugin'), 'cispa_options_field_callback', 'cispa_options_page', 'cispa_options_section', array( 'label_for' => 'cispa_hide_backlinks', 'field_name' => 'backlinks' ) );
	add_settings_field( 'cispa_all_pages', __( 'Show the CISPA message to visitors the first time they visit your site, no matter which page they land on?' , 'cispa-blackout-plugin'), 'cispa_options_field_callback', 'cispa_options_page', 'cispa_options_section', array( 'label_for' => 'cispa_all_pages', 'field_name' => 'all_pages' ) );
	add_settings_field( 'cispa_no_cookie', __( 'Don\'t allow visitors to view the regular site when the CISPA message is active:' , 'cispa-blackout-plugin'), 'cispa_options_field_callback', 'cispa_options_page', 'cispa_options_section', array( 'label_for' => 'cispa_no_cookie', 'field_name' => 'no_cookie' ) );
	add_settings_field( 'cispa_site_link', __( 'Link to the following page with the "Continue to site" link' , 'cispa-blackout-plugin'), 'cispa_options_field_callback', 'cispa_options_page', 'cispa_options_section', array( 'label_for' => 'cispa_blackout_dates[site_link]', 'field_name' => 'site_link' ) );
	add_settings_field( 'cispa_continue_to_dest', __( 'Make the "Continue to site" link lead to the visitor\'s original destination, instead of the page indicated above?' , 'cispa-blackout-plugin' ), 'cispa_options_field_callback', 'cispa_options_page', 'cispa_options_section', array( 'label_for' => 'cispa_continue_to_dest', 'field_name' => 'continue_to_dest' ) );
	add_settings_field( 'cispa_page_id', __( 'Use the following page for the CISPA message:' , 'cispa-blackout-plugin'), 'cispa_options_field_callback', 'cispa_options_page', 'cispa_options_section', array( 'label_for' => 'cispa_blackout_dates[page_id]', 'field_name' => 'page_id' ) );
	add_settings_field( 'cispa_custom_page', __( 'Use the following page as a custom CISPA message instead of the one included in this plugin?', 'cispa-blackout-plugin' ), 'cispa_options_field_callback', 'cispa_options_page', 'cispa_options_section', array( 'label_for' => 'cispa_blackout_dates[custom_page]', 'field_name' => 'custom_page' ) );
	add_settings_field( 'cispa_nag', __( 'Display an admin notice about this plugin?' , 'cispa-blackout-plugin' ), 'cispa_options_field_callback', 'cispa_options_page', 'cispa_options_section', array( 'label_for' => 'cispa_nag', 'field_name' => 'nag' ) );
}

/**
 * Sanitize the updated CISPA options
 * @param array $input the value of the options
 * @return array the sanitized values
 */
function sanitize_cispa_opts( $input ) {
	$input['blackout_dates'] = esc_attr( stripslashes( $input['blackout_dates'] ) );
	$input['backlinks'] = array_key_exists( 'backlinks', $input ) && ( '1' === $input['backlinks'] || 1 === $input['backlinks'] ) ? 1 : 0;
	$input['all_pages'] = array_key_exists( 'all_pages', $input ) && ( '1' === $input['all_pages'] || 1 === $input['all_pages'] ) ? 1 : 0;
	$input['no_cookie'] = array_key_exists( 'no_cookie', $input ) && ( '1' === $input['no_cookie'] || 1 === $input['no_cookie'] ) ? 1 : 0;
	$input['site_link'] = (int)$input['site_link'];
	$input['continue_to_dest'] = array_key_exists( 'continue_to_dest', $input ) && ( '1' === $input['continue_to_dest'] || 1 === $input['continue_to_dest'] ) ? 1 : 0;
	if ( empty( $input['page_id'] ) )
		$input['page_id'] = cispa_create_blank_page();
	if ( empty( $input['custom_page'] ) )
		$input['custom_page'] = 0;
	if ( array_key_exists( 'nag', $input ) ) {
		switch ( $input['nag'] ) {
			case 0:
			case '0':
			case '':
				$input['nag'] = 0;
				break;
			case 2:
			case '2':
				$input['nag'] = 2;
				break;
			default:
				$input['nag'] = 1;
		}
	} else {
		$input['nag'] = 1;
	}
	
	$input['cookie_hash'] = md5( time() );
	
	return $input;
}

/**
 * Create a new blank page to use as the placeholder
 */
function cispa_create_blank_page() {
	return wp_insert_post( array( 
		'comment_status' => 'closed',
		'ping_status'    => 'closed',
		'post_title'     => __( 'CISPA Blackout ' , 'cispa-blackout-plugin'),
		'post_content'   => __( 'This is a placeholder page for this website\'s CISPA Blackout message.' , 'cispa-blackout-plugin'),
		'post_type'      => 'page',
		'post_status'    => 'publish',
	) );
}

/**
 * Output the options page HTML
 */
function cispa_options_page_callback() {
	if ( ! current_user_can( 'manage_options' ) )
		wp_die( 'You do not have sufficient permissions to view this page.' );
?>
<div class="wrap">
	<h2><?php _e( 'CISPA Blackout Options' , 'cispa-blackout-plugin') ?></h2>
    <form method="post" action="options.php">
    <?php settings_fields( 'cispa_options_page' ) ?>
    <?php do_settings_sections( 'cispa_options_page' ) ?>
    <p><input type="submit" class="button-primary" value="<?php _e( 'Save Changes' , 'cispa-blackout-plugin') ?>"/></p>
    </form>
</div>
<?php
}

/**
 * Output the message to be displayed at the top of the options section
 */
function cispa_options_section_callback() {
	_e( '<p>Please choose the date(s) on which you would like the CISPA Blackout redirect to occur.</p>' , 'cispa-blackout-plugin');
	_e( '<p><em>Saving these options will reset all of the CISPA cookies, so visitors will see the CISPA message again even if they have already seen it.</em></p>' , 'cispa-blackout-plugin');
}

/**
 * Output the HTML for the options form elements
 */
function cispa_options_field_callback( $args ) {
	$cispa_opts = get_cispa_options();
	$blackout_dates = array_map( 'trim', explode( ',', $cispa_opts['blackout_dates'] ) );
	$blackout_dates = implode( ', ', $blackout_dates );
	
	switch ( $args['field_name'] ) {
		case 'blackout_dates':
?>
	<input class="widefat" type="text" value="<?php echo $blackout_dates ?>" name="cispa_blackout_dates[blackout_dates]" id="cispa_blackout_dates"/><br />
<em><?php _e( 'Please enter the dates in YYYY-MM-DD format. Separate multiple dates with commas.' , 'cispa-blackout-plugin') ?></em>
<?php
		break;
		case 'backlinks':
?>
	<input type="checkbox" name="cispa_blackout_dates[backlinks]" id="cispa_hide_backlinks" value="1"<?php checked( 1, $cispa_opts['backlinks'] ) ?>/>
<?php
		break;
		case 'all_pages':
?>
	<input type="checkbox" name="cispa_blackout_dates[all_pages]" id="cispa_all_pages" value="1"<?php checked( 1, $cispa_opts['all_pages'] ) ?>/><br />
<em><?php _e( 'By default, only the front page and posts "home" page show the CISPA message. If a visitor lands on an internal page, they won\'t see the CISPA message until they visit the home or front page. Check the box above to replace all pages on your site with the message.' , 'cispa-blackout-plugin') ?></em><p><em><?php _e( 'If you have the option above checked, but do not check the option below, visitors will only see the message once. Once they click through to visit your site, they will no longer see the CISPA message.' , 'cispa-blackout-plugin') ?></em></p>
<?php
		break;
		case 'no_cookie':
?>
	<input type="checkbox" name="cispa_blackout_dates[no_cookie]" id="cispa_no_cookie" value="1"<?php checked( 1, $cispa_opts['no_cookie'] ) ?>/><br />
<em><?php _e( 'By default, after a visitor has seen the CISPA message, all other visits to your site (including clicking the "Continue to site" link) will show the regular content. If you check the box above, they will see the CISPA message every time they visit your site (as long as it\'s active).' , 'cispa-blackout-plugin') ?></em>
<?php
		break;
		case 'site_link':
?>
<?php
			wp_dropdown_pages( array(
				'name'             => 'cispa_blackout_dates[site_link]',
				'echo'             => 1,
				'show_option_none' => 'Link to the site home page',
				'selected'         => $cispa_opts['site_link'],
			) );
?>
<?php
		break;
		case 'continue_to_dest':
?>
	<input type="checkbox" name="cispa_blackout_dates[continue_to_dest]" id="cispa_continue_to_dest" value="1"<?php checked( $cispa_opts['continue_to_dest'], 1 ) ?>/><br/>
    <em><?php _e( 'By default, the plugin will point visitors to your home page with the "Continue to site" link. Checking this box will allow the visitor to proceed to the page they initially requested, instead of being directed to the home page, or the alternative page you might have selected.' , 'cispa-blackout-plugin' ) ?></em>
<?php
		break;
		case 'page_id':
?>
<?php
			$pages = wp_dropdown_pages( array( 
				'name'             => 'cispa_blackout_dates[page_id]',
				'echo'             => 0,
				'show_option_none' => 'Create a new page (recommended)',
				'selected'         => $cispa_opts['page_id'],
			) );
			$pages = str_replace( '</select>', '<option value="redirect"' . selected( $cispa_opts['page_id'], 'redirect', false ) . '>Redirect to the PHP file (not recommended)</option></select>', $pages );
			echo $pages;
?>
    </select><br />
<em><?php _e( 'This page will be used as a placeholder for the CISPA message. If anyone tries to visit a page that is supposed to redirect to the CISPA message, they will be redirected to the address of the page selected above, and the CISPA Blackout message will be displayed there.</em></p><p><em>If you choose "Create a new page", a new blank page will automatically be created with a title of "CISPA Blackout". That page will be excluded automatically from any calls to wp_list_pages() and will be automatically removed when the plugin is deactivated.' , 'cispa-blackout-plugin') ?></em></p>
<?php
		break;
		case 'nag':
?>
	<select name="cispa_blackout_dates[nag]" id="cispa_nag" class="widefat">
    	<option value="0"<?php selected( $cispa_opts['nag'], 0 ) ?>><?php _e( 'Never display an admin notice' , 'cispa-blackout-plugin' ) ?></option>
        <option value="1"<?php selected( $cispa_opts['nag'], 1 ) ?>><?php _e( 'Display a notice only when the CISPA message is being displayed' , 'cispa-blackout-plugin' ) ?></option>
        <option value="2"<?php selected( $cispa_opts['nag'], 2 ) ?>><?php _e( 'Display a notice the whole time this plugin is activated' , 'cispa-blackout-plugin' ) ?></option>
    </select><br/>
    <em><?php _e( 'The admin notice will include links to more information about CISPA to help you keep up with news about the bill. When the CISPA message is being displayed, the admin notice will indicate that, and will include information about when the CISPA message is displayed to visitors.' , 'cispa-blackout-plugin' ) ?></em>
<?php
		break;
		case 'custom_page':
?>
<?php
			wp_dropdown_pages( array(
				'name'             => 'cispa_blackout_dates[custom_page]',
				'echo'             => 1,
				'show_option_none' => 'Use the included CISPA message',
				'selected'         => $cispa_opts['custom_page'],
			) );
		break;
	}
}

/**
 * Attempt to keep the blank CISPA placeholder page from showing up in 
 * 		auto-generated menus
 */
function exclude_cispa_page( $excludes ) {
	$cispa_opts = get_cispa_options();
	
	if ( ! empty( $cispa_opts['page_id'] ) && __( 'CISPA Blackout ' , 'cispa-blackout-plugin') == get_the_title( $cispa_opts['page_id'] ) )
		$excludes[] = $cispa_opts['page_id'];
	
	return $excludes;
}
add_filter( 'wp_list_pages_excludes', 'exclude_cispa_page', 99 );

/**
 * Perform deactivation actions
 * Remove the placeholder page if the user created a new page for this plugin
 * Delete the options from the database
 */
function remove_cispa_placeholder() {
	$cispa_opts = get_cispa_options();
	
	if ( ! empty( $cispa_opts['page_id'] ) && __( 'CISPA Blackout' , 'cispa-blackout-plugin') == get_the_title( $cispa_opts['page_id'] ) )
		wp_delete_post( $cispa_opts['page_id'], true );
	
	delete_option( 'cispa_blackout_dates' );
}
register_deactivation_hook( __FILE__, 'remove_cispa_placeholder' );

function load_down_cispa_textdomain() {
	load_plugin_textdomain( 'cispa-blackout-plugin', false, 'cispa-blackout-plugin/languages' );
}
add_action( 'init', 'load_down_cispa_textdomain' );

/**
 * Display the admin notice if the options indicate to do so
 */
function cispa_admin_nag() {
	$cispa_opts = get_cispa_options();
	
	if ( empty( $cispa_opts['nag'] ) )
		return;
	
	$pages = empty( $cispa_opts['all_pages'] ) ? 'your home page' : 'all pages on your site';
	$visits = empty( $cispa_opts['no_cookie'] ) ? 'their first visit' : 'all visits';
	
	if ( 1 == $cispa_opts['nag'] ) {
		if ( ! is_cispa_message_displayed() )
			return;
		
		$msg = sprintf( __( 'The CISPA message is currently being displayed to visitors of %s on %s to your site.' , 'cispa-blackout-plugin' ), $pages, $visits );
	} else {
		$blackout_dates = array_map( 'trim', explode( ',', $cispa_opts['blackout_dates'] ) );
		sort( $blackout_dates );
		$dates = array();
		$form = get_option( 'date_format' );
		foreach ( $blackout_dates as $d ) {
			if ( ! strtotime( $d ) )
				continue;
			$dates[] = date( $form, strtotime( $d ) );
		}
		switch ( count( $dates ) ) {
			case 0:
				$blackout_dates = '[no dates specified]';
				break;
			case 1:
				$blackout_dates = implode( ', ', $dates );
				break;
			case 2:
				$blackout_dates = implode( ' and ', $dates );
				break;
			default:
				$last_date = array_pop( $dates );
				$blackout_dates = implode( ', ', $dates ) . ' and ' . $last_date;
		}
		$msg = sprintf( __( 'The CISPA Blackout Plugin (Down Against CISPA) is activated on your site. It is currently set up to show the CISPA message to visitors of %s on %s to your site during the following dates: %s.' , 'cispa-blackout-plugin' ), $pages, $visits, $blackout_dates );
	}
	
	printf( __( '<div class="updated fade"><p>%s</p><p>For more current CISPA information, please feel free to visit <a href="%s">the Down Against CISPA</a> website.</p></div>', 'cispa-blackout-plugin' ), $msg, 'http://www.cispawpblackout.com/' );
}
add_action( 'admin_notices', 'cispa_admin_nag' );
?>
