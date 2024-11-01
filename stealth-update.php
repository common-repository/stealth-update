<?php
/**
 * Plugin Name: Stealth Update
 * Version:     2.5
 * Plugin URI:  http://coffee2code.com/wp-plugins/stealth-update/
 * Author:      Scott Reilly
 * Author URI:  http://coffee2code.com
 * Text Domain: stealth-update
 * License:     GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Description: Adds the ability to update a post without having WordPress automatically update the post's post_modified timestamp.
 *
 * Compatible with WordPress 3.6+ through 4.5+.
 *
 * =>> Read the accompanying readme.txt file for instructions and documentation.
 * =>> Also, visit the plugin's homepage for additional information and updates.
 * =>> Or visit: https://wordpress.org/plugins/stealth-update/
 *
 * @package Stealth_Update
 * @author  Scott Reilly
 * @version 2.5
 */

/*
 * TODO:
 * - Make it work for direct, non-UI calls to wp_update_post()
 * - Add class function get_meta_key() as getter for meta_key and
 *   filter on request rather than init to allow late filtering
 * - Add support for bulk edit box.
 * - Needs more unit tests.
 */

/*
	Copyright (c) 2009-2016 by Scott Reilly (aka coffee2code)

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'c2c_StealthUpdate' ) ) :

class c2c_StealthUpdate {

	/**
	 * The name of the associated form field.
	 *
	 * @access private
	 * @var string
	 */
	private static $field      = 'stealth_update';

	/**
	 * The name of the post meta key.
	 *
	 * Note: Filterable via 'c2c_stealth_publish_meta_key' filter.
	 *
	 * @access private
	 * @var string
	 */
	private static $meta_key   = '_stealth-update'; // Filterable via 'stealth_update_meta_key' filter

	/**
	 * Returns version of the plugin.
	 *
	 * @since 2.2.1
	 */
	public static function version() {
		return '2.5';
	}

	/**
	 * Initializer.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * The stealth update capability is only exposed for non-draft posts/pages.
	 *
	 * @since 2.0
	 * @uses apply_filters() Calls 'c2c_stealth_update_meta_key' with default meta key name
	 */
	public static function do_init() {
		global $pagenow, $post;

		// Load textdomain
		load_plugin_textdomain( 'stealth-update' );

		// Deprecated as of 2.3.
		$meta_key = apply_filters( 'stealth_update_meta_key', self::$meta_key );

		// Apply custom filter to obtain meta key name.
		$meta_key = apply_filters( 'c2c_stealth_update_meta_key', $meta_key );

		// Only override the meta key name if one was specified. Otherwise the
		// default remains (since a meta key is necessary)
		if ( $meta_key ) {
			self::$meta_key = $meta_key;
		}

		// Register hooks
//		if ( is_admin() && ( 'post.php' == $pagenow ) && !empty( $post->ID ) && ( 'draft' != $post->post_status ) )
		if ( is_admin() && ( 'post.php' == $pagenow ) && empty( $post ) ) {
			add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'add_to_submitbox' ) );
		}
		add_action( 'quick_edit_custom_box', array( __CLASS__, 'add_to_quick_edit' ), 10, 2 );
		add_filter( 'wp_insert_post_data',   array( __CLASS__, 'wp_insert_post_data' ), 2, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_filter( 'post_date_column_time', array( __CLASS__, 'add_icon_to_post_date_column' ), 10, 4 );
	}

	/**
	 * Outputs a dashicon lock if the post is configured to be stealth updated.
	 *
	 * @since 2.5
	 *
	 * @param string  $t_time      The published time.
	 * @param WP_Post $post        Post object.
	 * @param string  $column_name The column name.
	 * @param string  $mode        The list display mode ('excerpt' or 'list').
	 */
	public static function add_icon_to_post_date_column( $h_time, $post, $column_name, $mode ) {
		echo $h_time;

		if ( get_post_meta( $post->ID, self::$meta_key, true ) ) {
			echo ' <span class="' . esc_attr( self::$field ) . ' dashicons dashicons-lock" title="' . esc_attr__( 'Post has stealth updates enabled.', 'stealth-update' ) . '"></span>';
		}
	}

	/**
	 * Enqueues the admin JS.
	 *
	 * @since 2.5
	 *
	 * @param string $hook_name The hook (aka page) name.
	 */
	public static function admin_enqueue_scripts( $hook_name ) {
		if ( 'edit.php' !== $hook_name ) {
			return;
		}

		wp_enqueue_script( self::$field, plugins_url( 'assets/admin.js', __FILE__ ), array( 'jquery' ), self::version(), true );
	}

	/**
	 * Draws the UI to prompt user if stealth update should be present for the post.
	 *
	 * @since 2.0
	 * @uses apply_filters() Calls 'c2c_stealth_update_default' with stealth publish state default (false)
	 *
	 * @param bool $checked Should the checkbox be checked?
	 */
	public static function add_ui( $checked = null ) {
		if ( null === $checked ) {
			$checked = ( (bool) apply_filters( 'c2c_stealth_update_default', false ) ) ? '1' : '0';
		}
		$checked = checked( (bool) $checked, true, false );

		echo "<div class='misc-pub-section'><label class='selectit c2c-stealth-update' for='" . esc_attr( self::$field ) . "' title='";
		esc_attr_e( 'If checked, the post\'s modification date won\'t be updated to reflect the update when the post is saved.', 'stealth-update' );
		echo "'>\n";
		echo "<input id='" . esc_attr( self::$field ) . "' type='checkbox' $checked value='1' name='" . esc_attr( self::$field ) . "' />\n";
		_e( 'Stealth update?', 'stealth-update' );
		echo '</label></div>' . "\n";
	}

	/**
	 * Adds the checkbox to the edit form.
	 *
	 * @since 2.5
	 */
	public static function add_to_submitbox() {
		global $post;

		if ( apply_filters( 'c2c_stealth_update_default', false, $post ) ) {
			$value = '1';
		} else {
			$value = get_post_meta( $post->ID, self::$meta_key, true );
		}

		self::add_ui( (bool) $value );
	}

	/**
	 * Adds the checkbox to the quick edit panel.
	 *
	 * @since 2.5
	 *
	 * @param string $column_name Name of the column being output to quick edit.
	 * @param string $post_type   The post type of the post.
	 */
	public static function add_to_quick_edit( $column_name, $post_type ) {
		if ( did_action( 'quick_edit_custom_box' ) > 1 ) {
			return;
		}

		self::add_ui();
	}

	/**
	 * On post insert, save the value of stealth update custom field and possibly
	 * revert post_modified date.
	 *
	 * @since 2.0
	 *
	 * @param  array $data    An array of slashed post data.
	 * @param  array $postarr An array of sanitized, but otherwise unmodified post data.
	 * @return array The potentially modified $data
	 */
	public static function wp_insert_post_data( $data, $postarr ) {
		// Only operate on non-revision posts being updated.
		if ( isset( $postarr['post_type'] ) && ( 'revision' != $postarr['post_type'] ) && $postarr['ID'] ) {
			// Update the value of the stealth update custom field
			if ( isset( $postarr[ self::$field ] ) && $postarr[ self::$field ] ) {
				update_post_meta( $postarr['ID'], self::$meta_key, '1' );
			} else {
				delete_post_meta( $postarr['ID'], self::$meta_key );
			}

			// Possibly revert the post_modified date to the original post_modified date
			if ( isset( $postarr[ self::$field ] ) && $postarr[ self::$field ] ) {
				$data['post_modified']     = $postarr['post_modified'];
				$data['post_modified_gmt'] = get_gmt_from_date( $data['post_modified'] );
			}
		}

		return $data;
	}

} // end c2c_StealthUpdate

c2c_StealthUpdate::init();

endif; // end if !class_exists()
