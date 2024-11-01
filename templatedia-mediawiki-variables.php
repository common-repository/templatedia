<?php /*

**************************************************************************

Plugin Name:  Templatedia MediaWiki Variables
Plugin URI:   http://www.viper007bond.com/wordpress-plugins/templatedia/
Version:      1.0.0
Description:  Adds the <a href="http://www.mediawiki.org/wiki/Help:Variables">MediaWiki variables</a> to <a href="http://www.viper007bond.com/wordpress-plugins/templatedia/">Templatedia</a> for use in templates. <strong>This plugin is an optional install.</strong>
Author:       Viper007Bond
Author URI:   http://www.viper007bond.com/

**************************************************************************

Besides the obvious purpose of this plugin, it is also designed to be an
example of how to add new variables to Templatedia.

**************************************************************************/

// Tell WordPress we want to modify the "templatedia_variables" filter with our "templatedia_mediawikivars" function
add_filter( 'templatedia_variables', 'templatedia_mediawikivars' );


// This function accepts an array (the current variables), adds new variables to it, and then returns the modified array
function templatedia_mediawikivars( $variables ) {
	global $Templatedia;

	// Load up the localization file if we're using WordPress in a different language
	// Place it in the "localization" folder and name it "templatedia-[value in wp-config].mo"
	load_plugin_textdomain('templatedia', $Templatedia->folder . '/localization');

	$utctime = current_time('timestamp', TRUE);
	$localtime = current_time('timestamp');

	// Create a new array with our new variables
	$mediawikivars = array(
		// GMT based
		'CURRENTYEAR'        => array( 'output' => date( 'Y', $utctime ), 'description' => __('The current year, UTC timezone', 'templatedia') ),
		'CURRENTMONTH'       => array( 'output' => date( 'm', $utctime ), 'description' => __('The current month (zero-padded), UTC timezone', 'templatedia') ),
		'CURRENTMONTHNAME'   => array( 'output' => date( 'F', $utctime ), 'description' => __('The current month name, UTC timezone', 'templatedia') ),
		'CURRENTMONTHABBREV' => array( 'output' => date( 'M', $utctime ), 'description' => __('The current month (abbreviation), UTC timezone', 'templatedia') ),
		'CURRENTDAY'         => array( 'output' => date( 'j', $utctime ), 'description' => __('The current day of the month (unpadded), UTC timezone', 'templatedia') ),
		'CURRENTDAY2'        => array( 'output' => date( 'd', $utctime ), 'description' => __('The current day of the month (zero-padded), UTC timezone', 'templatedia') ),
		'CURRENTDOW'         => array( 'output' => date( 'w', $utctime ), 'description' => __('The current day of the week (unpadded), UTC timezone', 'templatedia') ),
		'CURRENTDAYNAME'     => array( 'output' => date( 'l', $utctime ), 'description' => __('The current name of the day of the week, UTC timezone', 'templatedia') ),
		'CURRENTTIME'        => array( 'output' => date( 'H:i', $utctime ), 'description' => __('The current time (24-hour HH:mm format), UTC timezone', 'templatedia') ),
		'CURRENTHOUR'        => array( 'output' => date( 'H', $utctime ), 'description' => __('The current hour (24-hour zero-padded number), UTC timezone', 'templatedia') ),
		'CURRENTWEEK'        => array( 'output' => date( 'W', $utctime ), 'description' => __('The current week of the year, UTC timezone', 'templatedia') ),
		'CURRENTTIMESTAMP'   => array( 'output' => date( 'YmdHis', $utctime ), 'description' => __('The current ISO 8601 timestamp, UTC timezone', 'templatedia') ),

		// Blog timezone based
		'LOCALYEAR'        => array( 'output' => date( 'Y', $localtime ), 'description' => __('The current year, blog timezone', 'templatedia') ),
		'LOCALMONTH'       => array( 'output' => date( 'm', $localtime ), 'description' => __('The current month (zero-padded), blog timezone', 'templatedia') ),
		'LOCALMONTHNAME'   => array( 'output' => date( 'F', $localtime ), 'description' => __('The current month name, blog timezone', 'templatedia') ),
		'LOCALMONTHABBREV' => array( 'output' => date( 'M', $localtime ), 'description' => __('The current month (abbreviation), blog timezone', 'templatedia') ),
		'LOCALDAY'         => array( 'output' => date( 'j', $localtime ), 'description' => __('The current day of the month (unpadded), blog timezone', 'templatedia') ),
		'LOCALDAY2'        => array( 'output' => date( 'd', $localtime ), 'description' => __('The current day of the month (zero-padded), blog timezone', 'templatedia') ),
		'LOCALDOW'         => array( 'output' => date( 'w', $localtime ), 'description' => __('The current day of the week (unpadded), blog timezone', 'templatedia') ),
		'LOCALDAYNAME'     => array( 'output' => date( 'l', $localtime ), 'description' => __('The current name of the day of the week, blog timezone', 'templatedia') ),
		'LOCALTIME'        => array( 'output' => date( 'H:i', $localtime ), 'description' => __('The current time (24-hour HH:mm format), blog timezone', 'templatedia') ),
		'LOCALHOUR'        => array( 'output' => date( 'H', $localtime ), 'description' => __('The current hour (24-hour zero-padded number), blog timezone', 'templatedia') ),
		'LOCALWEEK'        => array( 'output' => date( 'W', $localtime ), 'description' => __('The current week of the year, blog timezone', 'templatedia') ),
		'LOCALTIMESTAMP'   => array( 'output' => date( 'YmdHis', $localtime ), 'description' => __('The current ISO 8601 timestamp, blog timezone', 'templatedia') ),
	);

	// Merge the two arrays
	$variables = $variables + $mediawikivars;

	// Return the new variables array
	return $variables;
}

?>