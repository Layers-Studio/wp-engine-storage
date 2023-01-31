<?php

/**
 * Plugin Name: WP Engine Site Storage
 * Plugin URI: https://layers.studio
 * Author: Layers Studio
 * Author URI: https://layers.studio
 * Version: 0.1.0
 * 
 * Description: A plugin that checks the storage usage of a WP Engine website and restricts file uploads if the usage is above a threshold
 * Text Domain: wpe_storage
 *
 */


// Retrieve the sites in the WP Engine account accessed via the provided credentials
function wp_engine_api_connection() {

    // Get the API user and pass from the plugins settings
    $plugin_options = get_option( 'wpe_storage_options_group' );

    $api_user = $plugin_options['wpe_storage_api_user'];
    $api_pass = $plugin_options['wpe_storage_api_pass'];

    // If there is no api_user or api_pass throw an error
    if(!$api_user || !$api_pass){
        throw new Exception( 'WP Engine API details have not been set in the plugins settings.' );
    }

    // Initiate a curl request 
    $ch = curl_init();

    // Set the URL endpoint forus to CURL
    curl_setopt( $ch, CURLOPT_URL, 'https://api.wpengineapi.com/v1/installs?limit=100&offset=0' );

    // Set the status for return/transferring
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

    // Send a GET request to the URL
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'GET' );

    // Create an empty headers array ready to populate on our request
    $headers = array();

    // Create our credentials string ready for encoding
    $cred_string = $api_user . ":" . $api_pass;

    // Encode and add our credentials to our headers
    $headers[] = "Authorization: Basic " . base64_encode($cred_string);

    // Set the headers of the CURL Request
    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

    // Execute the curl request and get the response
    $response = curl_exec( $ch );

    // If there are errors then throw an error
    if ( curl_errno( $ch ) ) {
        throw new Exception( curl_error( $ch ));
    }

    // Close out our curl request
    curl_close( $ch );

    // Return the response ready for checking
    return $response;
}

// The function to check the storage usage of the WP Engine website
function check_wp_engine_storage_usage() {

    // Get the response of our API call to return all sites in the WP Engine account 
    $wpengine_response = wp_engine_api_connection();
    $sites = json_decode($wpengine_response, TRUE);

    // Get the domain name of the current site
    $domain_name = $_SERVER['HTTP_HOST'];

    //echo '<pre>' . print_r($sites['results'], true) . '</pre>';

    // Search for a site with the matching domain name
    foreach ($sites['results'] as $site) {
        if ($site['primary_domain'] == $domain_name) {
            $site_id = $site['site']['id'];
            break;
        }
    }
    
    // If we didn't find a matching site, throw an error
    if (!isset($site_id)) {
        throw new Exception('Error: Site with domain name ' . $domain_name . ' not found in WP Engine account');
    }

    return $site_id;

    // WP ENGINE CURRENTLY DOESN'T SUPPORT THE CHECKING OF SITE STORAGE VIA THE API
    // WHEN IT DOES WE WILL NEED TO FINISH THE REST OF THIS FUNCTION SO IT RETURNS
    // THE STORAGE USAGE OF THE SITE INSTEAD OF IT'S ID
    
    // Return the amount of storage occupied by the site
    // return $storage_usage;
}

/* WP ENGINE CURRENTLY DOESN'T SUPPORT THE CHECKING OF SITE STORAGE VIA THE API
// WHEN IT DOES WE WILL NEED TO WRITE THE REST OF THIS FUNCTION 

// The function to check the storage threshold before uploading a file
function check_storage_threshold_before_upload($file) {

    //Get the selected threshold from the settings page
    $storage_threshold = get_option('wpe_storage_hosting_package');

    //Loop over the values setting the threshold in GB
    if($storage_threshold === 'bronze'){
        $storage_threshold = 2;
    } elseif($storage_threshold === 'silver'){
        $storage_threshold = 5;
    } elseif($storage_threshold === 'gold'){
        $storage_threshold = 10;
    }

    //Convert GB to Bytes for comparison of storage_usage
    $true_threshold = $storage_threshold * 1024 * 1024 * 1024;

    //Get the amount of storage the site takes up
    $storage_usage = check_wp_engine_storage_usage();

    // Get the file size
    $file_size = $file['size'];

    // If the storage usage is greater than the threshold throw and error.
    // If uploading the file will take them over the threshold
    if ($storage_usage > $true_threshold || ($storage_usage + $file_size) > $true_threshold) {
        $file['error'] = 'File uploads are currently disabled due to storage usage limits.';
    } 
    
    return $file;
}

// Filter to check the storage threshold before uploading a file
add_filter('wp_handle_upload_prefilter', 'check_storage_threshold_before_upload');*/

// The function to add the plugin settings page
function wpe_storage_add_settings_page() {
    add_options_page(
        'WP Engine Site Storage Settings',
        'WP Engine Site Storage',
        'manage_options',
        'wpe-storage',
        'wpe_storage_settings_page'
    );
}

// Action to add the plugin settings page
add_action('admin_menu', 'wpe_storage_add_settings_page');

// The function to register the plugin settings
function wpe_storage_register_settings() {
    register_setting( 
        'wpe_storage_options_group', //A settings group name. Should correspond to an allowed option key name.
        'wpe_storage_options_group' //The name of an option to sanitize and save.
    );

    add_settings_section(
        'wpe_storage_settings_section', //$id 
        __( 'Settings', 'wpe_storage' ), //$title
        'wpe_storage_settings_section_callback', //Function that echos out any content at the top of the section (between heading and fields).
        'wpe-storage' //The slug-name of the settings page on which to show the section
    );

    add_settings_field(
        'wpe_storage_api_user', //$id 
        __( 'API User', 'wpe_storage' ), //$title
        'wpe_storage_api_user', //Function that echos out any content at the top of the section (between heading and fields).
        'wpe-storage', //The slug-name of the settings page on which to show the section
        'wpe_storage_settings_section' //The slug-name of the section of the settings page in which to show the box.
    );

    add_settings_field(
        'wpe_storage_api_pass', //$id 
        __( 'API Password', 'wpe_storage' ), //$title
        'wpe_storage_api_pass', //Function that echos out any content at the top of the section (between heading and fields).
        'wpe-storage', //The slug-name of the settings page on which to show the section
        'wpe_storage_settings_section' //The slug-name of the section of the settings page in which to show the box.
    );

    add_settings_field(
        'wpe_storage_section_hosting_package', //$id 
        __( 'Hosting Package', 'wpe_storage' ), //$title
        'wpe_storage_section_hosting_package', //Function that echos out any content at the top of the section (between heading and fields).
        'wpe-storage', //The slug-name of the settings page on which to show the section
        'wpe_storage_settings_section' //The slug-name of the section of the settings page in which to show the box.
    );    
}

function wpe_storage_settings_section_callback() { 
    echo __( 'This below options must be configured to ensure the website can conntect to WP Engine and work as expected.', 'wpe_storage' );
}

function wpe_storage_api_user() { 
    $options = get_option( 'wpe_storage_options_group' );

    isset( $options['wpe_storage_api_user'] ) && !empty($options['wpe_storage_api_user']) ? $value = $options['wpe_storage_api_user'] :  $value = "";

    echo '<input type="password" name="wpe_storage_options_group[wpe_storage_api_user]" value="' . $value . '" size="40">';
}

function wpe_storage_api_pass() { 
    $options = get_option( 'wpe_storage_options_group' );
    isset( $options['wpe_storage_api_pass'] ) && !empty($options['wpe_storage_api_pass']) ? $value = $options['wpe_storage_api_pass'] :  $value = "";

    echo '<input type="password" name="wpe_storage_options_group[wpe_storage_api_pass]" value="' . $value . '" size="40">';
}

function wpe_storage_section_hosting_package() { 
    $options = get_option( 'wpe_storage_options_group' );
    $selected_value = isset( $options['wpe_storage_section_hosting_package'] ) ? $options['wpe_storage_section_hosting_package'] : "";
    ?>

    <select name="wpe_storage_options_group[wpe_storage_section_hosting_package]">
        <option value="">Select a Package...</option>
        <option value="bronze" <?php selected( $selected_value, "bronze" ); ?>>Bronze</option>
        <option value="silver" <?php selected( $selected_value, "silver" ); ?>>Silver</option>
        <option value="gold" <?php selected( $selected_value, "gold" ); ?>>Gold</option>
    </select>
<?php }

function wpe_storage_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }?>

    <div class="wrap">
        <h1>WP Engine Site Storage Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wpe_storage_options_group');
            do_settings_sections('wpe-storage');
            submit_button();

            var_dump(check_wp_engine_storage_usage());
            ?>
        </form>
    </div>
<?php }

// Action to register the plugin settings
add_action('admin_init', 'wpe_storage_register_settings');