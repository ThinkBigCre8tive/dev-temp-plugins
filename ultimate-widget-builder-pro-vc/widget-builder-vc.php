<?php
/*
 * Plugin Name: Ultimate Widget Builder Pro with Visual Composer
 * Plugin URI: http://ninjateam.org
 * Description: Feel free to insert EVERYTHING to the widget, eg: image, video, post slider...
 * Version: 1.4
 * Author: NinjaTeam
 * Author URI: http://ninjateam.org
 */

define('NJT_WG_B_FILE', __FILE__);

define('NJT_WG_B_DIR', realpath(plugin_dir_path(NJT_WG_B_FILE)));
define('NJT_WG_B_URL', plugins_url('', NJT_WG_B_FILE));
define('NJT_WG_B_I18N', 'njt_widget_builder');

require_once NJT_WG_B_DIR . '/widget.php';
require_once NJT_WG_B_DIR . '/init.php';
NjtWGBuilder::instance();
