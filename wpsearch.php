<?php

/**
 * Plugin Name: Search by WP Search
 * Plugin URI: http://wordpress.org/plugins/search-by-wp-search/
 * Description: The WP Search plugin replaces WordPress search with a better search that is fully customizable via the dashboard, API, filters and action hooks.
 * Version: 1.0
 * Author: WP Search
 * Author URI: http://wpsear.ch/
 * Tags: search, wordpress search, wp search, faceted search, search widget, wpsearch, custom search, better search,post serach, custom post search, taxonomy search
 */
define('WPSEARCH_VERSION', '1.0');
add_action('init', 'wpsearch_load_plugin_textdomain');

// Load i18n Language Translation
function wpsearch_load_plugin_textdomain() {
    load_plugin_textdomain('wpsearch', FALSE, dirname(plugin_basename(__FILE__)) . '/languages/');
}

require_once 'WPSearchPlugin.php';
require_once 'WPSearchApiClient.php';
require_once 'WPSearchIndexer.php';
require_once 'WPSearchSearch.php';
require_once 'WPSearchUtils.php';
require_once 'WPSearchOptions.php';
require_once 'WPSearchWidget.php';
require_once 'WPSearchFunctions.php';

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if (!is_plugin_active('ReduxFramework/redux-framework.php') && !is_plugin_active('redux-framework/redux-framework.php') && !is_plugin_active('ReduxFramework-master/redux-framework.php')) {
    if ( !class_exists('Redux_Framework') && file_exists(__DIR__ . '/ReduxFramework/ReduxCore/framework.php')) {
        require_once( __DIR__ . '/ReduxFramework/ReduxCore/framework.php' );
    }
}
if (file_exists(__DIR__ . '/redux-config.php')) {
    require_once(__DIR__ . '/redux-config.php' );
}


$wpsearch = new WPSearchPlugin();
$wpsearch->run();
?>