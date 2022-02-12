<?php
/*
Plugin Name: Under Construction Password
Plugin URI: https://github.com/bitwisecreative/wordpress-under-construction-password
Description: A very simple WordPress plugin to "lock" the site with a password (so at least the client can view it) while it is under construction. To start, activate the plugin, then enable it in "Settings->Under Construction Password"
Version: 0.0.1
Author: Bitwise Creative
Author URI: https://github.com/bitwisecreative/wordpress-under-construction-password
License: MIT
*/

// Exit if called directly
if (!function_exists('add_action')) {
    exit;
}

add_action('admin_menu', 'ucp_add_admin_menu');
add_action('admin_init', 'ucp_settings_init');

function ucp_add_admin_menu() {
    //add_options_page( string $page_title, string $menu_title, string $capability, string $menu_slug, callable $function = '', int $position = null )
    add_options_page('Under Construction Password', 'Under Construction Password', 'manage_options', 'ucp', 'ucp_options_page');
}

function ucp_settings_init() {
    register_setting('ucp', 'ucp_settings');
    // add_settings_section( string $id, string $title, callable $callback, string $page )
    add_settings_section('ucp_settings_section', __('Settings', 'wordpress') , 'ucp_settings_section_callback', 'ucp');

    // add_settings_field( string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = array() )
    add_settings_field('ucp_enabled', __('Enabled', 'wordpress') , 'ucp_setting_enabled_render', 'ucp', 'ucp_settings_section');

    add_settings_field('ucp_password', __('Password', 'wordpress') , 'ucp_setting_password_render', 'ucp', 'ucp_settings_section');

    add_settings_field('ucp_lock_page_html', __('Lock Page HTML', 'wordpress') , 'ucp_setting_lock_page_html_render', 'ucp', 'ucp_settings_section');
}

function ucp_setting_enabled_render() {
    $options = get_option('ucp_settings');
?>
    <select name='ucp_settings[ucp_enabled]'>
        <option value='0' <?php selected($options['ucp_enabled'], 0); ?>>No</option>
        <option value='1' <?php selected($options['ucp_enabled'], 1); ?>>Yes</option>
    </select>
    <?php
}

function ucp_setting_password_render() {
    $options = get_option('ucp_settings');
?>
    <input type="text" name="ucp_settings[ucp_password]" value="<?php echo ($options['ucp_password']) ? $options['ucp_password'] : ''; ?>" />
    <?php
}

function ucp_setting_lock_page_html_render() {
    $options = get_option('ucp_settings');
?>
    <textarea class="code" name="ucp_settings[ucp_lock_page_html]" id="ucp-textarea-html" rows="10" cols="80"><?php echo ($options['ucp_lock_page_html']) ? htmlentities($options['ucp_lock_page_html']) : default_lock_page_html(); ?></textarea>
    <p><a href="javascript:void(0);" onclick="loadDefaultHtml()" id="ucp-load-default-html">Load default HTML</a></p>
    <script>
        function loadDefaultHtml(){
            var html=`<?php echo default_lock_page_html(); ?>`;
            console.log(html);
            document.getElementById('ucp-textarea-html').innerHTML=html;
        }
    </script>
    <?php
}

function default_lock_page_html() {
    $name = get_bloginfo('name');
    $description = get_bloginfo('description');
    $css = get_bloginfo('stylesheet_url'); // seems useless but I'll leave it...
    $html = '<!doctype html>
    <html lang="en">
    <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>' . $name . ' - Under Construction</title>
    <meta name="description" content="' . $description . '" />
    <link rel="stylesheet" href="' . $css . '" type="text/css" />
    <style>
    :root {
        --color: #777;
    }
    body{
        font-family:Calibri, Candara, Segoe, Segoe UI, Optima, Arial, sans-serif;
        text-align:center;
        color:var(--color);
    }
    hr{
        background:none;
        border:none;
        border-bottom:1px solid var(--color);
    }
    input{
        margin:8px;
        padding:8px;
    }
    h1 {
        background: var(--color);
        color: #fff;
        padding: 16px;
    }
    </style>
    </head>
    <body>
        <h3>' . $name . '</h3>
        <p>' . $description . '</p>
        <hr />
        <h1>Under Construction</h1>
        <hr />
        <form method="post">
            <input type="password" name="ucp_password" /><br />
            <input type="submit" value="Submit" />
        </form>
    </body>
    </html>';
    return $html;
}

function ucp_settings_section_callback() {
    echo __('Enable and set a password to lock your site.', 'wordpress');
}

function ucp_options_page() {
?>
    <form action='options.php' method='post'>

        <h2>Under Contruction Password</h2>

        <?php
    settings_fields('ucp');
    do_settings_sections('ucp');
    submit_button();
?>

    </form>
    <?php
}

//
// RUN
//
function ucp_init() {
    // Check enabled
    $options = get_option('ucp_settings');
    $enabled = (int)$options['ucp_enabled'];
    if (!$enabled) {
        return;
    }
    // Disable in admin, admin users, etc...
    $script_blacklist = ['wp-login.php'];
    if (is_admin() || is_rest_request() || in_array(script_name() , $script_blacklist) || current_user_can('administrator')) {
        return;
    }
    // Check for session
    if (!session_id()) {
        session_start();
    }
    if (isset($_SESSION['ucp_auth']) && $_SESSION['ucp_auth']) {
        return;
    }
    // Check for form submit...
    if (isset($_POST['ucp_password'])) {
        $options = get_option('ucp_settings');
        if ($_POST['ucp_password'] === $options['ucp_password']) {
            $_SESSION['ucp_auth'] = true;
            return;
        }
    }
    // Fail auth and display form
    header('HTTP/1.1 503 Service Temporarily Unavailable');
    header('Status: 503 Service Temporarily Unavailable');
    header('Retry-After: 86400'); // 1 day
    echo $options['ucp_lock_page_html'];
    exit;
}
add_action('init', 'ucp_init');
//
function is_rest_request() {
    return defined('REST_REQUEST') && REST_REQUEST;
}
function script_name() {
    $ret = false;
    if (isset($_SERVER['SCRIPT_NAME'])) {
        $s = explode('/', $_SERVER['SCRIPT_NAME']);
        $ret = array_pop($s);
    }
    return $ret;
}

