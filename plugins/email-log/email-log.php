<?php
/**
 * Plugin Name: Email Log
 * Plugin URI: https://wpemaillog.com/
 * Description: Logs every email sent through WordPress
 * Author: WebFactory Ltd
 * Version: 2.63
 * Author URI: https://www.webfactoryltd.com/
 * Text Domain: email-log
 * License: GPLv2 or later
 * Requires at least: 4.0
 * Tested up to: 7.0
 * Requires PHP: 5.6
 */

/**
 * Copyright 2026 WebFactory Ltd
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA.
 */

defined('ABSPATH') || exit; // Exit if accessed directly.

define('EMAIL_LOG_FILE', __FILE__);
define('EMAIL_LOG_URL', trailingslashit(plugins_url('', __FILE__)));
define('EMAIL_LOG_PATH', trailingslashit(plugin_dir_path(__FILE__)));
define('EMAIL_LOG_URI', trailingslashit(plugin_dir_url(__FILE__)));

require_once dirname(__FILE__) . '/wf-flyout/wf-flyout.php';

function load_email_log($plugin_file)
{
    global $email_log;

    $plugin_dir = plugin_dir_path($plugin_file);

    if (is_admin()) {
      new wf_flyout(__FILE__);
    }

    // setup autoloader.
    require_once 'include/EmailLogAutoloader.php';

    $loader = new \EmailLog\EmailLogAutoloader();
    $loader->add_namespace('EmailLog', $plugin_dir . 'include');
    $loader->add_namespace('Sudar\\WPSystemInfo', $plugin_dir . 'vendor/sudar/wp-system-info/src/');

    if (file_exists($plugin_dir . 'tests/')) {
        // if tests are present, then add them.
        $loader->add_namespace('EmailLog', $plugin_dir . 'tests/wp-tests');
    }

    $loader->add_file($plugin_dir . 'include/Util/helper.php');
    $loader->add_file($plugin_dir . 'include/Addon/addon-helper.php');

    $loader->register();

    $email_log = new \EmailLog\Core\EmailLog($plugin_file, $loader, new \EmailLog\Core\DB\TableManager());

    $email_log->add_loadie(new \EmailLog\Core\EmailLogger());

    $email_log->add_loadie(new \EmailLog\Core\UI\UILoader(), true);

    $email_log->add_loadie(new \EmailLog\Core\Request\NonceChecker());
    $email_log->add_loadie(new \EmailLog\Core\Request\LogListAction());

    $capability_giver = new \EmailLog\Core\AdminCapabilityGiver();
    $email_log->add_loadie($capability_giver);

    // `register_activation_hook` can't be called from inside any hook.
    register_activation_hook($plugin_file, array($email_log->table_manager, 'on_activate'));
    register_activation_hook($plugin_file, array($capability_giver, 'add_cap_to_admin'));

    // Ideally the plugin should be loaded in a later event like `init` or `wp_loaded`.
    // But some plugins like EDD are sending emails in `init` event itself,
    // which won't be logged if the plugin is loaded in `wp_loaded` or `init`.
    add_action('plugins_loaded', array($email_log, 'load'), 101);
}

function email_log_plugin_version()
{
    $plugin_data = get_file_data(__FILE__, array('version' => 'Version'), 'plugin');

    return $plugin_data['version'];
} // get_plugin_version

/**
 * Return the global instance of Email Log plugin.
 * Eventually the EmailLog class might become singleton.
 *
 * @since 2.0
 *
 * @global \EmailLog\Core\EmailLog $email_log
 *
 * @return \EmailLog\Core\EmailLog
 */
function email_log()
{
    global $email_log;
    return $email_log;
}

load_email_log(__FILE__);
