<?php
/*
Plugin Name: Admiral Ad Block Analytics
Plugin URI: http://getadmiral.com/wordpress
Description: Admiral is an advanced adblock analytics and revenue recovery platform.
Author: Admiral <support@getadmiral.com>
Version: 1.6.1
Author URI: http://getadmiral.com/
*/

// block direct access to this plugin
defined("ABSPATH") or die("");

define("ADMIRAL_ADBLOCK_ADMIN_PROPERTY_ID_OPTION_KEY", "admiral_property_id");
define("ADMIRAL_ADBLOCK_ADMIN_APPEND_PHP_OPTION_KEY", "admiral_append_php");

require("AdmiralAdBlockAnalytics.php");

function admiraladblock_load_settings()
{
    try {
        $propertyID = get_option(ADMIRAL_ADBLOCK_ADMIN_PROPERTY_ID_OPTION_KEY, "");
        if (!empty($propertyID)) {
            AdmiralAdBlockAnalytics::setPropertyID($propertyID);
        }
        $appendPHP = get_option(ADMIRAL_ADBLOCK_ADMIN_APPEND_PHP_OPTION_KEY, "");
        if (!empty($appendPHP)) {
            AdmiralAdBlockAnalytics::$alwaysAppendPHP = true;
        }
    } catch (Exception $e) {
        error_log("Error loading settings: " . $e->getMessage());
    }
}

admiraladblock_load_settings();

if (AdmiralAdBlockAnalytics::enabled()) {
    require('scriptHooks.php');
}

// always include the admin section
require('adminHooks.php');

/* EOF */
