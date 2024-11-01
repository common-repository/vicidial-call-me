<?php
/**
 * Plugin Name:       VICIdial Call Me
 * Description:       Displays a contact form which will post user submitted information into a VICIdial system utilizing the VICIdial non-agent API and call them.
 * Version:           1.0
 * Requires at least: 2.0.3
 * Author:            VICIdial
 * Author URI:        https://www.vicidial.com/
 * License:           AGPLv2
 * License URI:       http://www.affero.org/agpl2.html
 */


/////////////////////////////////////
// Housekeeping

# ACTIVATION
register_activation_hook( __FILE__, 'vdcm_activatePlugin' );
function vdcm_activatePlugin() {

	$options = get_option('vdcm_settings');
	if ( empty($options) ) {
		add_option('vdcm_settings', 'http://yourserver/vicidial/non_agent_api.php---APIUSER---APIPASS---1---998---add_to_hopper=Y&hopper_priority=99---NOTICE: By clicking \'CALL ME\' you hearby grant yourcompanyname permission to call you using an autodialer.---NONE---0');
	}

}

# DEACTIVATION
register_deactivation_hook( __FILE__, 'vdcm_deactivatePlugin' );
function vdcm_deactivatePlugin() {
	// Nothing to do.
}

# UNINSTALL
register_uninstall_hook(__FILE__, 'vdcm_uninstallPlugin');
function vdcm_uninstallPlugin() {
	
	delete_option('vdcm_settings');
	
}


/////////////////////////////////////
// CallMe Form

# Create the shortcode
add_action('init', 'vdcm_shortcodes_init');
function vdcm_shortcodes_init() {
	add_shortcode(
		'VICIdialCallMe',			// Shortcode tag
		'vdcm_display_shortcode'	// Function that handles the shortcode
	);
}
function vdcm_display_shortcode() {
	$action = esc_url( admin_url('admin-post.php') );

	$options = get_option('vdcm_settings');
	$options = explode("---", $options);
	$apiTcpa = esc_html( $options[6] );

	$output.= "
	<div id=\"content\">
	<form action=\"$action\" method=\"post\">
	<input type=hidden name=action value='vdcmCallMe_form'>
	First Name: <input maxlength = \"30\" size = \"20\" name=\"first_name\" type=\"text\" value=\"\" ><br>
	Last Name: <input maxlength = \"30\" size = \"20\" name=\"last_name\" type=\"text\" value=\"\" ><br>
	Phone: <input maxlength = \"16\" name=\"phone_number\" type=\"text\" value=\"\" ><br>
	Comments: <textarea name = \"comments\" rows = \"4\" cols = \"100\" ></textarea><br><br>
	<button type=\"submit\" value=\"submit\">CALL ME</button>
	</form><br>
	<i>$apiTcpa</i></div>";
	
	return $output;
}

# Handle the form
add_action( 'admin_post_nopriv_vdcmCallMe_form', 'vdcm_send_post_to_vicidial' );
add_action( 'admin_post_vdcmCallMe_form', 'vdcm_send_post_to_vicidial' );
function vdcm_send_post_to_vicidial() {
	if (isset($_POST["first_name"]))	{$vdcm_first_name=preg_replace("/[^a-zA-Z]/","",$_POST["first_name"]);}
	if (isset($_POST["last_name"]))		{$vdcm_last_name=preg_replace("/[^a-zA-Z]/","",$_POST["last_name"]);}
	if (isset($_POST["phone_number"]))	{$vdcm_phone_number=preg_replace("/[^0-9]/","",$_POST["phone_number"]);}
	if (isset($_POST["comments"]))		{$vdcm_comments=sanitize_textarea_field($_POST["comments"]);}
	
	if ( empty($vdcm_phone_number) ) {
		echo "You have not provided a phone number.";
		exit;
	}

	$vdcm_options = get_option('vdcm_settings');
	$vdcm_options = explode("---", $vdcm_options);
		$vdcm_domain = $vdcm_options[0];
		$vdcm_user = $vdcm_options[1];
		$vdcm_pass = $vdcm_options[2];
		$vdcm_phone_code = $vdcm_options[3];
		$vdcm_listId = $vdcm_options[4];
		$vdcm_apiQueryStr = $vdcm_options[5];
		$vdcm_RedirectUrl = $vdcm_options[7];
		$vdcm_db = $vdcm_options[8];
	$vdcm_body = array(
		'user' => $vdcm_user,
		'pass' => $vdcm_pass,
		'function' => 'add_lead',
		'source' => 'wp_vdcm',
		'list_id' => $vdcm_listId,
		'first_name' => $vdcm_first_name,
		'last_name' => $vdcm_last_name,
		'phone_code' => $vdcm_phone_code,
		'phone_number' => $vdcm_phone_number,
		'comments' => $vdcm_comments
	);
	if ( !($vdcm_apiQueryStr == "NONE" || $vdcm_apiQueryStr == "") ) {
		$vdcm_apiQueryStr = explode("&", $vdcm_apiQueryStr);
		foreach ($vdcm_apiQueryStr as $vdcm_apiFieldValuePair) {
			$vdcm_apiFieldValuePair = explode("=", $vdcm_apiFieldValuePair);
			$vdcm_body[$vdcm_apiFieldValuePair[0]] = $vdcm_apiFieldValuePair[1];
		}
	}

	$vdcm_postArgs = array(
		'method' => 'POST',
		'body' => $vdcm_body
	);
	$vdcm_apiResponse = wp_remote_post( $vdcm_domain, $vdcm_postArgs );

	if ($vdcm_db) {
		var_dump($vdcm_postArgs);
		echo "<br><br>$vdcm_apiResponse[body]";
		exit;
	}
	if ( $vdcm_RedirectUrl == "NONE" || $vdcm_RedirectUrl == "" ) {
		echo "Thank you. You will be called shortly.";
	}
	else {
		wp_redirect( $vdcm_RedirectUrl );
		exit;
	}
}


/////////////////////////////////////
// ADMIN SETTINGS

# Initialize the settings fields
add_action('admin_init', 'vdcm_settings_init');
function vdcm_settings_init() {
	
	register_setting(
		'vdcm_settings',	// Option group
		'vdcm_settings', 	// Option name
		[					// Arguments
		'type' => 'string',
		'description' => 'A triple-dash delimited string of the VICIdial Call Me plug-in settings.',
		'sanitize_callback' => 'vdcm_settings_sanitize'
		]
	);
	function vdcm_settings_sanitize( $args ) {
	    
		$args[user] = preg_replace('/[^-_0-9a-zA-Z]/','',$args[user]);
		$args[pass] = preg_replace('/[^-_0-9a-zA-Z]/','',$args[pass]);
		$args[listId] = preg_replace('/[^0-9]/','',$args[listId]);
		$args[phnCode] = preg_replace('/[^0-9]/','',$args[phnCode]);
		$args[qStr] = preg_replace('/[^&=:+-_0-9a-zA-Z]/','',$args[qStr]);
		$args[tcpa] = sanitize_textarea_field( $args[tcpa] );
		$args[domain] = esc_url( $args[domain] );
		if ( $args[rdrUrl] != "NONE" ) {
			$args[rdrUrl] = esc_url( $args[rdrUrl] );
			}
		else {
			$args[pass] = preg_replace('/[^a-zA-Z]/','',$args[pass]);
			}
		
		$validated = $args[domain]."---".$args[user]."---".$args[pass]."---".$args[phnCode]."---".$args[listId]."---".$args[qStr]."---".$args[tcpa]."---".$args[rdrUrl]."---".$args[db];
	    return $validated;
	}
	
	# Admin settings section
	add_settings_section( 
		'vdcm_settings_section', 
		'VICIdial Call Me Settings', 
		'vdcm_settings_section_cb', 
		'vdcm_settings_page'
	);
	function vdcm_settings_section_cb() {
	    echo "These settings must be filled out for the Call Me button to function.";
	}
	
	# Get our data to populate defaults
	$options = get_option('vdcm_settings');
	$options = explode("---", $options);
		$apiDomain = $options[0];
		$apiUser = $options[1];
		$apiPass = $options[2];
		$apiPhnCode = $options[3];
		$apiListId = $options[4];
		$apiQueryStr = $options[5];
		$apiTcpa = $options[6];
		$apiRdrUrl = $options[7];
		$db = $options[8];

	# Webserver field
	add_settings_field(
		'vdcm_api_domain',
		'API URL',
		'vdcm_api_domain_cb',
		'vdcm_settings_page',
		'vdcm_settings_section',
		 [
		 'label_for' => 'vdcm_settings[domain]',
		 'class' => '',
		 'data' => $apiDomain
		 ]
	);	
	function vdcm_api_domain_cb( $args ) {
	    echo "<input size = \"50\" id = \"" . $args[label_for] . "\" name = \"" . $args[label_for] . "\" value = \"" . $args[data] . "\")></input><br>
			  <i>This should be the full URL to the VICIdial API script.<br>
			  i.e. \"http://www.yourserver.com/vicidial/non_agent_api.php\".</i>";
	}

	# User field
	add_settings_field(
		'vdcm_api_user',			// Slug name
		'API User',					// Field label
		'vdcm_api_user_cb',			// Function to display setting
		'vdcm_settings_page',		// Slug name of settings page
		'vdcm_settings_section',	// Slug name of settings section within settings page
		 [							// Extra args
		 'label_for' => 'vdcm_settings[user]',
		 'class' => '',
		 'data' => $apiUser
		 ]
	);
	function vdcm_api_user_cb( $args ) {
	    echo "<input id = \"" . $args[label_for] . "\" name = \"" . $args[label_for] . "\" value = \"" . $args[data] . "\")></input>";
	}
	
	# Pass field
	add_settings_field(
		'vdcm_api_pass',
		'API Pass',
		'vdcm_api_pass_cb',
		'vdcm_settings_page',
		'vdcm_settings_section',
		 [
		 'label_for' => 'vdcm_settings[pass]',
		 'class' => '',
		 'data' => $apiPass
		 ]
	);
	function vdcm_api_pass_cb( $args ) {
	    echo "<input type=\"password\" id = \"" . $args[label_for] . "\" name = \"" . $args[label_for] . "\" value = \"" . $args[data] . "\")></input>";
	}

	# Phone code
	add_settings_field(
	    'vdcm_api_phone_code',
	    'Phone Code',
	    'vdcm_api_phone_code_cb',
	    'vdcm_settings_page',
	    'vdcm_settings_section',
	    [
	        'label_for' => 'vdcm_settings[phnCode]',
	        'class' => '',
	        'data' => $apiPhnCode
	    ]
	    );
	function vdcm_api_phone_code_cb( $args ) {
	    echo "<input maxlength = \"3\" size = \"3\" id = \"" . $args[label_for] . "\" name = \"" . $args[label_for] . "\" value = \"" . $args[data] . "\")></input><br>
			  <i>The country code for the phone number. Default is '1' for US and Canada.</i>";
	}
	
	# List ID
	add_settings_field(
		'vdcm_api_list_id',
		'List ID',
		'vdcm_api_list_id_cb',
		'vdcm_settings_page',
		'vdcm_settings_section',
		 [
		 'label_for' => 'vdcm_settings[listId]',
		 'class' => '',
		 'data' => $apiListId
		 ]
	);
	function vdcm_api_list_id_cb( $args ) {
	    echo "<input maxlength = \"14\" size = \"14\" id = \"" . $args[label_for] . "\" name = \"" . $args[label_for] . "\" value = \"" . $args[data] . "\")></input><br>
			  <i>ID of the list inside VICIdial the lead will be inserted into.</i>";
	}
	
	# Query params
	add_settings_field(
		'vdcm_api_queryStr',
		'Query String Options',
		'vdcm_api_queryStr_cb',
		'vdcm_settings_page',
		'vdcm_settings_section',
		 [
		 'label_for' => 'vdcm_settings[qStr]',
		 'class' => '',
		 'data' => $apiQueryStr
		 ]
	);
	function vdcm_api_queryStr_cb( $args ) {
	    echo "<input size = \"50\" id = \"" . $args[label_for] . "\" name = \"" . $args[label_for] . "\" value = \"" . $args[data] . "\")></input><br>
			  <i>You can include additional query string parameters for the ADD_LEAD API function here.<br>
			  Separate each parameter=value pair with an ampersand (&).</i>";
	}
	
	# TCPA blurb
	add_settings_field(
		'vdcm_api_tcpa',
		'TCPA Permission Statement',
		'vdcm_api_tcpa_cb',
		'vdcm_settings_page',
		'vdcm_settings_section',
		 [
		 'label_for' => 'vdcm_settings[tcpa]',
		 'class' => '',
		 'data' => $apiTcpa
		 ]
	);
	function vdcm_api_tcpa_cb( $args ) {
	    echo "<textarea id = \"" . $args[label_for] . "\" name = \"" . $args[label_for] . "\" rows = \"4\" cols = \"100\" >" . $args[data] . "</textarea><br>
			  <i>We HIGHLY recommend that you include a statment indicating the user grants your company permission to call them with an autodialer for TCPA compliance within the US.</i>";
	}
	
	# Redirect URL
	add_settings_field(
	    'vdcm_api_redirect_url',
	    'Redirect URL',
	    'vdcm_api_redirect_url_cb',
	    'vdcm_settings_page',
	    'vdcm_settings_section',
	    [
	        'label_for' => 'vdcm_settings[rdrUrl]',
	        'class' => '',
	        'data' => $apiRdrUrl
	    ]
	    );
	function vdcm_api_redirect_url_cb( $args ) {
	    echo "<input size = \"50\" id = \"" . $args[label_for] . "\" name = \"" . $args[label_for] . "\" value = \"" . $args[data] . "\")></input><br>
			  <i>The URL to redirect your user to after submitting their info to be called. Default is 'NONE' and will display a short thank you.</i>";
	}
	
	# Debug Setting
	add_settings_field(
		'vdcm_api_debug',
		'Debug Mode',
		'vdcm_api_debug_cb',
		'vdcm_settings_page',
		'vdcm_settings_section',
		[
			'label_for' => 'vdcm_settings[db]',
			'class' => '',
			'data' => $db
		]
	);
	function vdcm_api_debug_cb( $args ) {
		if ( $args[data] ) {
			$on = "checked=\"checked\"";
		}
		else {
			$off = "checked=\"checked\"";
		}
		echo "<input type=\"radio\" id = \"" . $args[label_for] . "\" name = \"" . $args[label_for] . "\" value=\"0\" $off>OFF<br>
			  <input type=\"radio\" id = \"" . $args[label_for] . "\" name = \"" . $args[label_for] . "\" value=\"1\" $on>ON<br>
			  <i>Will display the POST fields sent and API response returned by VICIdial after form submission.<br>
			  THIS WILL REVEAL YOUR API CREDENTIALS - USE WITH CAUTION.</i>";
	}
}

# Display settings page
add_action('admin_menu', 'vdcm_settings_page');
function vdcm_settings_page() {
	add_options_page(
		'VICIdial Call Me',				// Page title
		'VICIdial Call Me',				// Menu title
		'manage_options',				// Capability of user required to view
		'vdcm_settings_page',			// Slug name for the menu
		'vdcm_display_settings_page'	// Function to handle displaying settings page
	);
}
function vdcm_display_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		echo "You do not have permission to be here.";
		return;
	}
	settings_errors( 'vdcm_settings' );

	echo "<div class=\"wrap\">";
	echo "<form action=\"options.php\" method=\"post\">";
	settings_fields( 'vdcm_settings' );
	do_settings_sections('vdcm_settings_page');
	submit_button( 'Save Settings' );
	echo "</form></div>";
}
?>
