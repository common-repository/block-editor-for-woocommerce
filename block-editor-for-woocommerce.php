<?php
/**
 * Use Block Editor (Gutenberg) for WooCommerce Product Editing
 *
 * @author Kudratullah <mhamudul.hk@gmail.com>
 * @package PxH_WC_Block_Editor
 * @version 1.2.0
 * @copyright 2019 Kudratullah
 * @license GPL-v3 or later
 *
 * @wordpress
 * Plugin Name: Block Editor For WooCommerce
 * Description: Enable Block Editor (Gutenberg) for WooCommerce
 * Plugin URI: https://github.com/Kudratullah/block-editor-for-woocommerce
 * Author: Kudratullah
 * Author URI: https://github.com/Kudratullah
 * Version: 1.2.0
 * License: GPL v3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: pxh-wc-block-editor
 * Domain Path: /languages/
 * Requires at least: 5.0
 * Tested up to: 6.3.2
 * WC tested up to: 8.2.1
 */

/**
 * Copyright (c) 2019 Kudratullah (email: mhamudul.hk@gmail.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * **********************************************************************
 */

if ( ! defined( 'ABSPATH' ) ) {
	// !silence
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die();
}

if ( ! defined( 'PxH_WC_BE_VERSION' ) ) {
	/**
	 * Plugin Version
	 * @since 1.0.0
	 */
	define( 'PxH_WC_BE_VERSION', '1.2.0' );
}
if ( ! defined( 'PxH_WC_BE_FILE' ) ) {
	/**
	 * Absolute path to this plugin main file
	 * @since 1.0.0
	 */
	define( 'PxH_WC_BE_FILE', __FILE__ );
}
if ( ! defined( 'PxH_WC_BE_PATH' ) ) {
	/**
	 * Absolute path to this plugin directory without trailing slash
	 * @since 1.0.0
	 */
	define( 'PxH_WC_BE_PATH', dirname( PxH_WC_BE_FILE ) );
}
if ( ! defined( 'PxH_WC_BE_URL' ) ) {
	/**
	 * URL Pointing to this plugin directory with trailing slash
	 * @since 1.0.0
	 */
	define( 'PxH_WC_BE_URL', plugins_url( '/', PxH_WC_BE_FILE ) );
}

// Initialize after WC Loaded
add_action( 'woocommerce_loaded', 'PxH_WC_Enable_Block_Editor', 9999 );

/**
 * Initialize Plugin after WooCommerce.
 *
 * Remove filters applied by WooCommerce for disabling block editor on Product Edit Page
 *
 * @return void
 * @since 1.0.0
 *
 */
function PxH_WC_Enable_Block_Editor() {
	add_action( 'before_woocommerce_init', 'PxH_Declare_WC_HPOS_Compatibility' );
	add_filter( 'woocommerce_register_post_type_product', 'PxH_WC_BE_Fix_Product_Template', 100 );
	remove_filter( 'gutenberg_can_edit_post_type', 'WC_Post_Types::gutenberg_can_edit_post_type', 10 );
	remove_filter( 'use_block_editor_for_post_type', 'WC_Post_Types::gutenberg_can_edit_post_type', 10 );
	add_action( 'admin_enqueue_scripts', 'PxH_WC_Block_Editor_Scripts' );

	// set show_in_rest = true for product_cat & product_tag for showing in block editor taxonomy selector
	add_filter( 'woocommerce_taxonomy_args_product_cat', 'PxH_WC_BE_Product_Taxonomy_Show_In_Rest' );
	add_filter( 'woocommerce_taxonomy_args_product_tag', 'PxH_WC_BE_Product_Taxonomy_Show_In_Rest' );

	add_action( 'pre_post_update', 'PxH_WC_BE_Stop_Resetting_Missing_Catalog_Options', -1 );
	add_action( 'admin_footer', 'PxH_WC_BE_Review_Reply_Form' );
}

/**
 * Hooked callback for admin script enqueueing
 *
 * @return void
 * @since 1.0.0
 *
 */
function PxH_WC_Block_Editor_Scripts() {
	$screen    = get_current_screen();
	$screen_id = $screen ? $screen->id : '';
	$suffix    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	if ( in_array( $screen_id, array( 'product', 'edit-product' ) ) ) {
		wp_enqueue_script( 'wp-ajax-response' );
		wp_enqueue_script(
			'pxh_wc_enable_block_editor',
			PxH_WC_BE_URL . 'assets/js/admin' . $suffix . '.js',
			[
				'jquery',
				'postbox',
				'wc-admin-product-meta-boxes'
			], // make sure it loaded after wp postbox and wc product meta box scripts
			defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? filemtime( PxH_WC_BE_PATH . '/assets/js/admin.js' ) : PxH_WC_BE_VERSION,
			true
		);

		if ( isset( $screen->post_type ) && post_type_supports( $screen->post_type, 'comments' ) ) {
			wp_enqueue_script( 'admin-comments' );
			enqueue_comment_hotkeys_js();
		}
	}
}

/**
 * Allow WooCommerce Product Category And Product Tags in rest.
 * Allowing in rest will allow these taxonomy to be shown in block editor.
 *
 * @param array $args
 *
 * @return  array
 * @since 1.1.0
 *
 */
function PxH_WC_BE_Product_Taxonomy_Show_In_Rest( $args ) {
	$args['show_in_rest'] = true;

	return $args;
}


/**
 * Disable new WooCommerce product template (Since WC 7.7.0).
 *
 * @param array $post_type_args
 *
 * @return array
 * @since 1.1.0
 *
 */
function PxH_WC_BE_Fix_Product_Template( $post_type_args ) {
	if ( array_key_exists( 'template', $post_type_args ) ) {
		unset( $post_type_args['template'] );
		unset( $post_type_args['template_lock'] );
	}

	return $post_type_args;
}

/**
 * Currently, in block editor we don't have product visibility options and featured checkbox.
 * So everytime user saves product these values get reset.
 * Set catalog visibility and feature checkbox value to $_POST var from product object to prevent this.
 *
 * @param string|int $post_id
 *
 * @since 1.1.0
 *
 */
function PxH_WC_BE_Stop_Resetting_Missing_Catalog_Options( $post_id ) {
	if ( ! is_admin() ) {
		return;
	}

	$currentScreen = get_current_screen();
	if ( $currentScreen->id !== 'product' || $currentScreen->post_type !== 'product' ) {
		return;
	}

	try {
		$product = WC()->product_factory->get_product( $post_id );
	} catch ( \Exception $exception ) {
		return;
	}

	if ( ! ( $product instanceof \WC_Product ) ) {
		return;
	}

	if ( $product->is_featured() ) {
		$_POST['_featured'] = 'on';
	}

	$_POST['_visibility'] = $product->get_catalog_visibility();
}


/**
 * Add review (comment) reply form for product.
 *
 * @return void
 * @since 1.1.0
 *
 */
function PxH_WC_BE_Review_Reply_Form() {
	$screen    = get_current_screen();
	$screen_id = $screen ? $screen->id : '';
	if ( in_array( $screen_id, array( 'product', 'edit-product' ) ) ) {
		if ( isset( $screen->post_type ) && post_type_supports( $screen->post_type, 'comments' ) ) {
			//require ABSPATH . 'wp-admin/edit-form-advanced.php';
			echo '<div id="pxh-comment-form">';
			wp_comment_reply();
			echo '</div>';
		}
	}
}

/**
 * Declare HPOS (COT) compatibility for WooCommerce.
 *
 * @return void
 * @since 1.2.0
 */
function PxH_Declare_WC_HPOS_Compatibility() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}

// End of file wc-block-editor.php
