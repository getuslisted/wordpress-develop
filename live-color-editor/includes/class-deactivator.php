<?php

/**
 * Fired during plugin deactivation.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/includes
 * @author     Jules <you@example.com>
 */
class LCE_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
        // Deactivation code will go here.
        // It's often good practice to not delete options on deactivation
        // in case the user wants to re-activate.
        // Deletion should happen on uninstall.
	}

}
