<?php

// block direct access to this plugin
defined("ABSPATH") or die("");

function admiraladblock_register_settings()
{
    register_setting("admiral_property_settings", ADMIRAL_ADBLOCK_ADMIN_PROPERTY_ID_OPTION_KEY);
    register_setting("admiral_property_settings", ADMIRAL_ADBLOCK_ADMIN_APPEND_PHP_OPTION_KEY);
}

add_action("admin_init", "admiraladblock_register_settings");

function admiraladblock_options()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    $currentPropertyID = AdmiralAdBlockAnalytics::getPropertyID();
    $currentAppendPHP = AdmiralAdBlockAnalytics::$alwaysAppendPHP;
    echo '<div class="wrap" style="margin-top: 30px;">
        <div class="logo-container">
            <img src="//cdn.getadmiral.com/logo-horz.svg" height="40" />
        </div>
        <p>The Admiral Wordpress Plugin allows you to easily measure how much revenue you are losing to adblock. You must have an account at <a href="https://www.getadmiral.com" target="_blank">getadmiral.com</a> to use this plugin.</p>
        <form method="post" action="options.php">';
    settings_fields('admiral_property_settings');
    do_settings_sections('admiral_property_settings');
    echo '<table class="form-table"><tbody><tr>';
    echo '<th>Property ID</th>';
    echo '<td>';
    echo '<input type="text" name="' . ADMIRAL_ADBLOCK_ADMIN_PROPERTY_ID_OPTION_KEY . '" value="' . addcslashes($currentPropertyID, '"') . '" />';
    echo '<p class="description">The property ID is a unique identifier that identifies this site.</p>';
    echo '<p class="description">Find your property ID on it\'s property page at <a href="https://www.getadmiral.com" target="_blank">getadmiral.com</a>.</p>';
    echo '</td></tr><tr>';
    echo '<th>Treat all requests as php</th>';
    echo '<td>';
    echo '<input type="checkbox" name="' . ADMIRAL_ADBLOCK_ADMIN_APPEND_PHP_OPTION_KEY . '" value="1" ' . (!empty($currentAppendPHP) ? 'checked' : '') . '/>';
    echo '<p class="description">Check this if only php extensions get served by Wordpress.</p>';
    echo '<p class="description">Try checking this box if a script is 404 erroring on your site.</p>';
    echo '</td></tr></tbody></table>';
    submit_button();
    echo '</form>
    </div>';
}

function admiraladblock_plugin_menu()
{
    add_options_page("Admiral Options", "Admiral", "manage_options", "admiral-adblock-analytics", "admiraladblock_options");
}

add_action("admin_menu", "admiraladblock_plugin_menu");

function admiraladblock_auto_update($update, $item) {
    if (!empty($item) && !empty($item->slug) && $item->slug === 'admiral-adblock-suite') {
        return true;
    }
    // fallback to whatever it was going to do instead
    return $update;
}
add_filter("auto_update_plugin", "admiraladblock_auto_update", 10, 2);

/* EOF */
