<?php
	/*
	Plugin Name: Spillt Recipe App
	Plugin URI: https://spillt.co/wordpress-integration/
	Description: This plugin provides the ability to sync reviews from Spillt App with your blog. It also automatically syncs your recipes to the app, so that followers can be notified as you publish new work.
	Version: 1.0.32
	Author: Betterfeed, Inc
	License: GPLv2 or later
	Text Domain: spillt

	GitHub Plugin URI: https://github.com/SpiltApp/spilltwpplugin
	*/

	namespace Spillt;


	// If this file is called directly, abort.
	if ( ! defined( 'WPINC' ) ) {
		die;
	}



	define(__NAMESPACE__ . '\PREFIX', 'spillt');

	define(__NAMESPACE__ . '\PLUGIN_URL', untrailingslashit(plugin_dir_url(__FILE__)));

	define(__NAMESPACE__ . '\PLUGIN_DIR', untrailingslashit(plugin_dir_path(__FILE__)));

	define(__NAMESPACE__ . '\PLUGIN_FOLDER', plugin_basename(PLUGIN_DIR));

	define(__NAMESPACE__ . '\PLUGIN_INSTANCE', sanitize_title(crypt($_SERVER['SERVER_NAME'], $salt = PLUGIN_FOLDER)));

	define(__NAMESPACE__ . '\PLUGIN_SETTINGS_URL', admin_url('admin.php?page='.PREFIX));

	define(__NAMESPACE__ . '\CHANGELOG_COVER', PLUGIN_URL . '/assets/images/plugin-cover.jpg');

	define(__NAMESPACE__ . '\ERROR_PATH', plugin_dir_path(__FILE__) . 'error.log');

	define(__NAMESPACE__ . '\TEXT_DOMAIN', 'spillt-app');

// Load action scheduler.
	global $action_scheduler;
	$action_scheduler = require_once plugin_dir_path( __FILE__ ) . 'vendor/woocommerce/action-scheduler/action-scheduler.php';


//init
	if(!class_exists( __NAMESPACE__ . '\Core')){
		include_once PLUGIN_DIR . '/includes/class-core.php';
	}

	register_activation_hook( __FILE__, __NAMESPACE__ . '\Core::on_activation');
	register_deactivation_hook( __FILE__, __NAMESPACE__ . '\Core::on_deactivation');
	register_uninstall_hook( __FILE__, __NAMESPACE__ . '\Core::on_uninstall');

//load translation, make sure this hook runs before all, so we set priority to 1
	add_action('init', function(){
		load_plugin_textdomain( __NAMESPACE__ . '\TEXT_DOMAIN', false, dirname(plugin_basename( __FILE__ )) . '/languages/' );
	}, 1);
