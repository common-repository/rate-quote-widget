<?php
/*
Plugin Name: Rate Quote Widget
Plugin URI: http://ratequotewidget.com/
Description: Mortgage lead qualification engine.
Version: 5.0
Author: Dan Green
Author URI: http://themortgagereports.com/rate-quote-widget
*/
// ------------------------------------------------------------- //
/**
 * Copyright 2010-2011 | RateQuoteWidget.com
 * Released under the GNU General Public License (GPL)
 * 
 * @link http://ratequotewidget.com
 * @license http://www.gnu.org/licenses/gpl.txt
 */
// ------------------------------------------------------------- //
/**
 * Block direct access to this file.
 */
if(!function_exists('add_action')):
	header("{$_SERVER['SERVER_PROTOCOL']} 403 Forbidden");
	header("Status: 403 Forbidden");
	die('Direct Access Denied.');
endif;
// ------------------------------------------------------------- //
define('BTB_RQW_PATH', realpath(dirname(__FILE__))); // Current Folder Path
// The URL to the current Directory where the Widget and dependencies reside
define('BTB_RQW_RELPATH', str_replace('\\', '/', substr(BTB_RQW_PATH, strlen(realpath(ABSPATH)))));
define('BTB_RQW_URL', site_url(BTB_RQW_RELPATH));
define('BTB_RQW_HOME', 'http://ratequotewidget.com/rqw'); // Base URL (DO NOT CHANGE)
// This can be configured in the Settings but you can uncomment the next Line to enforce your choice
// define('BTB_RQW_PREVENTCLONES', true); // This will prevent Double Output of the Widget on any given page
// ------------------------------------------------------------- //
/**
 * Get the API Key.
 * (Function for quick access to a Widget Setting.)
 * 
 * @return string|null
 */
function BTB_RQW_APIKey(){
	return (is_string($apikey = get_option('btbrqw_apikey', null)) and strlen($apikey = trim($apikey))) ? $apikey : null;
}
/**
 * Get CSS URL.
 * (Function for quick access to a Widget Setting.)
 * 
 * @return string
 */
function BTB_RQW_CSSource(){
	// If we have an apparent URL in the option, we return that
	if(is_string($css = get_option('btbrqw_css_override', null)) and strpos($css = trim($css), '://')) return $css;
	// If the default local file exists, we return it otherwise we return the hosted one
	return BTB_RQW_HOME.'/rqwnewg.css';
}
/**
 * Show Link?
 * (Function for quick access to a Widget Setting.)
 * 
 * @return bool
 */
function BTB_RQW_ShowLink(){
	return (bool)get_option('btbrqw_show_link', true);
}
/**
 * Prevent Output of the Widget more than once on any given page?
 * define('BTB_RQW_PREVENTCLONES', true or false); allows you to lock this setting,
 * otherwise it's accessible and changeable in the settings screen (see header of this file).
 * 
 * @return bool
 */
function BTB_RQW_PreventClones(){
	// Look for the definition first and return it's bool value
	if(defined('BTB_RQW_PREVENTCLONES')) return (bool)constant('BTB_RQW_PREVENTCLONES');
	// Or translate the option to a bool value
	return (bool)get_option('btbrqw_no_clones', false);
}
// ------------------------------------------------------------- //
/**
 * Reusable cURL GET function to download the FORM HTML.
 * $arguments is the QueryString added after ?.
 * 
 * @internal
 * @param string $url
 * @param array $arguments
 * @return string|null
 */
function BTB_RQW_FetchURL($url, $arguments = null, $verb = 'GET'){
	// Unless POST is explicit, we use GET
	if(is_object($arguments)) $arguments = get_object_vars($arguments);
	if(is_array($arguments) and !empty($arguments)){
		$arguments = http_build_query($arguments);
	}
	$curl_handle = curl_init();
	$verb = (is_string($verb) and !strcasecmp(trim($verb), 'POST')) ? 'POST' : 'GET';
	// If we have GET we add the $arguments to the QueryString
	if(!strcasecmp($verb, 'GET')){
		$glue = (strpos($url, '?') !== false) ? '&' : '?';
		$url = "{$url}{$glue}{$arguments}";
	}else{
		// Otherwise we prepare them for posting
		curl_setopt_array($curl_handle, array(
			CURLOPT_POST		=> true,
			CURLOPT_POSTFIELDS	=> $arguments,
		));
	}
	curl_setopt_array($curl_handle, array(
		CURLOPT_CONNECTTIMEOUT		=> 30,
		CURLOPT_TIMEOUT				=> 60,
		CURLOPT_HTTP_VERSION		=> CURL_HTTP_VERSION_1_0,
		CURLOPT_URL					=> $url,
		CURLOPT_FOLLOWLOCATION		=> true,
		CURLOPT_MAXREDIRS			=> 2,
		CURLOPT_AUTOREFERER			=> true,
		CURLOPT_BINARYTRANSFER		=> true,
		CURLOPT_HEADER				=> false,
		CURLOPT_RETURNTRANSFER		=> true,
		CURLOPT_VERBOSE				=> false,
		CURLOPT_FAILONERROR			=> false,
		CURLOPT_SSL_VERIFYPEER		=> false,
		CURLOPT_SSL_VERIFYHOST		=> false,
		CURLOPT_USERAGENT			=> !empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
		CURLOPT_REFERER				=> !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
		CURLOPT_HTTPHEADER			=> array(
			'Expect:',
			'Connection: close',
		), // Keep-Alives not good here
	));
	// Weirdest cURL bug ever ignoring CURLOPT_RETURNTRANSFER
	ob_start();
	$html = curl_exec($curl_handle);
	$wtfhtml = ob_get_clean();
	if(is_bool($html)) $html = $wtfhtml;
	// Now get status and wrap things up
	$status = (int)curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
	curl_close($curl_handle);
	return (($status == 200) or strlen($html = trim($html))) ? $html : null;
}
// ------------------------------------------------------------- //
/**
 * This function actually Outputs stuff.
 * If $output is TRUE the HTML is printed and a BOOL is returned.
 * If $output is FALSE the HTML is returned or NULL on error.
 * 
 * @param bool $output
 * @return bool|string
 */
function BTB_RQW_PrintWidget($output = true){
	if(!func_num_args()) $output = true; // Freaky error ignoring default.
	if(!($apikey = BTB_RQW_APIKey())) return $output ? false : null;
	$title = get_option('btbrqw_title', null);
	static $instance_id = 0; // Track calls on same page
	if(!is_string($title) or !strlen($title = trim($title))) $title = null;
	$html = BTB_RQW_FetchURL(BTB_RQW_HOME.'/rqwnewg.php', array(
		'rqw_apikey'		=> $apikey,
		'rqw_show_link'		=> ($showlink = BTB_RQW_ShowLink()) ? 'on' : 'off',
		'rqw_title'			=> $title, // Future use
		'rqw_instance_id'	=> $instance_id, // Instances of Widget on same page
		'rqw_referer_url'	=> 'http://'.$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"], // Passes current page URL
		// For future use (branding...)
		'rqw_blog_name'		=> get_bloginfo('name'),
		'rqw_blog_url'		=> get_bloginfo('url'),
		// Internal use
		'rqw_query_param'	=> !empty($_SESSION['query_param']) ? trim($_SESSION['query_param']) : null,
		'rqw_link_text'		=> null, // Undocumented
	), 'GET'); // Use POST to POST data back
	if(empty($html)) return $output ? false : null;
	// Let's purge styles
	$customcss = get_option('btbrqw_css_override', null);
	if(is_string($customcss = BTB_RQW_CSSource()) and strlen($customcss = trim($customcss)) and strpos($customcss, '://')){
		$customcss = sprintf('<link rel="stylesheet" href="%s" type="text/css" />', $customcss);
	}else $customcss = null;
	// Overwrite current CSS with custom stylesheet URL
	$html = $html.PHP_EOL.$customcss;
	$html = $html.PHP_EOL.'<script type="text/javascript" src="'.BTB_RQW_HOME.'/rqwnewg.js"></script>';
	$html = preg_replace('~[\s]+onchange[\s]*=[\s]*"toggle_mortage_rate\([\s]*\)[;]?"~i', null, $html);
	$html = trim($html);
	// Prevent double output?
	if(BTB_RQW_PreventClones() and defined('BTB_RQW_PRINTED')){
		return $output ? false : null;
	}
	$instance_id++; // Increment instance ID if we get here
	define('BTB_RQW_PRINTED', true); // Mark as first instance
	// Now finalize the process
	if($output) echo $html;
	return $output ? true : $html;
}
// ------------------------------------------------------------- //
/**
 * Provided for backward compatibility.
 * Please use BTB_RQW_PrintWidget(); instead.
 * 
 * @deprecated
 */
function widget_btb_RQW(){
	BTB_RQW_PrintWidget(true);
	$error = defined('E_USER_DEPRECATED') ? E_USER_DEPRECATED : E_USER_WARNING;
	trigger_error('Please switch to BTB_RQW_PrintWidget() instead of '.__FUNCTION__.'().', $error);
}
// ------------------------------------------------------------- //
/**
 * The Rate Quote Widget.
 */
class BTB_RQW_Widget extends WP_Widget{
	// Easily change Widget name in Widget List
	const WidgetName = 'Rate Quote Widget';
	// Easily change Widget description in Widget List
	const WidgetDescription = 'Better leads, less time, more money.';
	/**
	 * Widget init code.
	 */
	public function __construct(){
		// Register the Widget (name and description can be easily changed using the consts above)
		parent::__construct('widget_btb_RQW', self::WidgetName, array(
			'description' => self::WidgetDescription,
		));
	}
	/**
	 * Widget output.
	 */
	public function widget($args, $instance){
		// Allow plugins to hook into 'btbrqw_show_in_sidebar' and block sidebar showing
		if(!apply_filters('btbrqw_show_in_sidebar', true)) return;
		// Carry on, business as usual
		extract($args, EXTR_OVERWRITE|EXTR_PREFIX_ALL, 'arg');
		extract($instance, EXTR_OVERWRITE|EXTR_PREFIX_ALL, 'inst');
		// If we can't get the HTML, we don't show the Widget at all.
		if(!($html = BTB_RQW_PrintWidget(false))) return;
		// And now we output the Widget.
		echo $arg_before_widget, $html, $arg_after_widget;
	}
	/**
	 * Nothing happens here.
	 */
	function update($new_instance, $old_instance){
		return $new_instance;
	}
	/**
	 * The settings: none.
	 * But we show a link to the actual Settings Page:
	 * /wp-admin/options-general.php?page=RateQuoteWidget
	 */
	public function form($instance){ ?>
		<p>To setup <strong>Rate Quote Widget</strong> go to:<br />
			<a href="<?php echo admin_url('options-general.php?page=RateQuoteWidget'); ?>" target="_blank" style="text-decoration: none;">
				<strong>Settings &raquo; Rate Quote Widget</strong></a></p>
	<?php return 'noform'; }
};
// ------------------------------------------------------------- //
/**
 * Main class containing actions/filters.
 */
class BTB_RQW{
	/**
	 * Hook into WordPress.
	 */
	static public function Construct(){
		add_action('widgets_init', array(__CLASS__, 'WidgetsInit'));
		add_action('admin_menu', array(__CLASS__, 'AdminMenu'));
		add_action('admin_notices', array(__CLASS__, 'AdminNotices'));
		add_action('wp_print_scripts', array(__CLASS__, 'PrintScripts'));
		add_action('wp_print_styles', array(__CLASS__, 'PrintStyles'));
		register_activation_hook(__FILE__, array(__CLASS__, 'Activation'));
		register_uninstall_hook(__FILE__, array(__CLASS__, 'Uninstall'));
		add_filter('the_content', array(__CLASS__, 'TheContent'), 11);
		add_filter('contextual_help', array(__CLASS__, 'ContextualHelp'));
		add_action('add_meta_boxes', array(__CLASS__, 'AddMetaBoxes'), 10, 2);
		add_action('wp_insert_post', array(__CLASS__, 'WPInsertPost'));
		add_filter('btbrqw_show_in_sidebar', array(__CLASS__, 'ShowInSidebar'));
	}
	/**
	 * Allow disabling of RQW.
	 * 
	 * @param bool $bool
	 * @return bool
	 */
	static public function ShowInSidebar($bool){
		if(!is_singular()) return $bool;
		// Don't handle this unless on is_singular()-s
		$postID = intval(get_queried_object_id());
		// Prevent display of sidebar widget if user chose so
		if((bool)get_post_meta($postID, '_btbrqw_nowidget', true)){
			return false;
		}
		// Prevent display of sidebar widget if shortcode already used and clones are prevented
		if(BTB_RQW_PreventClones() and preg_match('~\[ratequotewidget[\s]*/?\]~i', get_post_field('post_content', $postID))){
			return false;
		}
		return $bool;
	}
	/**
	 * Add new Metabox to postypes that support editor.
	 * 
	 * @param mixed $bool
	 */
	static public function AddMetaBoxes($postype, $post){
		if(!post_type_supports($postype, 'editor')) return;
		add_meta_box('btbrqw-mb', 'Rate Quote Widget', array(__CLASS__, 'DisableWidgetMB'), $postype, 'side', 'low');
	}
	/**
	 * Update state of NoWidget in post settings.
	 */
	static public function WPInsertPost($postID){
		update_post_meta($postID, '_btbrqw_nowidget', intval($_REQUEST['BTBRQW_NoWidget']));
	}
	/**
	 * Allow user to disable RQW on certain posts?
	 */
	static public function DisableWidgetMB($post, $box){ ?>
		<p>
			<input type="checkbox" <?php checked(true, (bool)get_post_meta($post->ID, '_btbrqw_nowidget', true)); ?>
				name="BTBRQW_NoWidget" ID="BTBRQW_NoWidget" value="1" />
			<label class="setting-description" for="BTBRQW_NoWidget">
				Disable <a href="<?php echo admin_url('options-general.php?page=RateQuoteWidget'); ?>"
					target="_blank" style="text-decoration: none;"><strong>RateQuoteWidget</strong></a> in <em>Sidebars</em>?
			</label>
		</p>
	<?php }
	/**
	 * Register Widget.
	 */
	static public function WidgetsInit(){
		register_widget('BTB_RQW_Widget');
	}
	/**
	 * Easter egg with YouTube video in Help.
	 * 
	 * @param string $help
	 * @return string
	 */
	static public function ContextualHelp($help){
		if(strcasecmp($_GET['page'], 'RateQuoteWidget')) return $help;
		ob_start();
	?>
		<div>
			<h3>Rate Quote Widget Presentation</h3>
			<iframe style="border: 5px solid #222; padding: 1px;" width="640" height="390"
				src="http://www.youtube.com/embed/B3-PCfpR0Lo?hd=1" frameborder="0" allowfullscreen="allowfullscreen"></iframe>
			<p>Visit <a href="http://ratequotewidget.com/" target="_blank">ratequotewidget.com</a> to learn more.</p>
			<h4>Plugin Path</h4>
			<p>The Plugin is located inside your WordPress folder in <strong><?php echo BTB_RQW_RELPATH; ?>/</strong>.
				If you have custom CSS files for your Rate Quote Widget, this is where you place them.</p>
		</div>
	<?php return $help.ob_get_clean(); }
	/**
	 * Shortcode that prints the Widget.
	 * Use like this: [ratequotewidget/]
	 * 
	 * @param string $content
	 * @return string
	 */
	static public function TheContent($content){
			// No point in continuing if '[ratequotewidget' is not found in $content
			if(stripos($content, '[ratequotewidget') === false) return $content;
			$html = !($html = BTB_RQW_PrintWidget(false)) ? null : '<div style="margin: 25px auto;" class="rqw-inline">'.$html.'</div>';
			// Replace Shortcode manually
			$content = preg_replace('~<p[^>]*>[\s]*\[ratequotewidget[\s]*/?\](\s*\[/ratequotewidget\])?[\s]*</p>~i', $html, $content);
			$content = preg_replace('~\[ratequotewidget[\s]*/?\](\s*\[/ratequotewidget\])?~i', $html, $content);
			return $content;
	}
	/**
	 * Removes all Database storage on uninstall.
	 * 
	 * @internal
	 */
	static public function Uninstall(){
		global $wpdb;
		// All option names starting with btbrqw_* are belong to us
		$wpdb->query("DELETE FROM {$wpdb->options} WHERE `option_name` LIKE 'btbrqw_%';");
		// All meta names starting with btbrqw_* or _btbrqw_* are also belong to us
		$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE (`option_name` LIKE '_btbrqw_%') OR (`option_name` LIKE 'btbrqw_%');");
	}
	/**
	 * Rejects Activation for old PHP versions.
	 * Imports older 'btb_BTB_RQW_settings' option into new separate elements.
	 * 
	 * @internal
	 */
	static public function Activation(){
		if(version_compare(PHP_VERSION, '5.2', '<')){
			deactivate_plugins(__FILE__, true);
			wp_die('Plugin could not be activated because your '.PHP_VERSION.' PHP Version is incompatible. Ask your web host to upgrade your PHP version to 5.2 or higher.', 'Outdated PHP Detected');
		}
		// Extract old Option format and update to new settings
		if(($options = get_option('btb_BTB_RQW_settings', null)) and is_array($options)){
			foreach($options as $name => $value){
				update_option("btb{$name}", $value);
			}
			delete_option('btb_BTB_RQW_settings'); // Remove old Option
		}
	}
	/**
	 * Pull in Widget CSS.
	 */
	static public function PrintStyles(){
		if(is_string($cssource = BTB_RQW_CSSource()) and strpos($cssource, '://')){
			wp_enqueue_style('btbrqw', $cssource, null);
		}
	}
	/**
	 * Pull in jQuery.
	 * If it's not included it all fails badly and some themes don't include it by default.
	 */
	static public function PrintScripts(){
		wp_enqueue_script('jquery'); // It is registered so we just enqueue it.
	}
	/**
	 * Notify user of missing API key.
	 */
	static public function AdminNotices(){
		if(!current_user_can('activate_plugins')) return; // Only Administrators
		// Show notice if API KEY is empty
		if(!($apikey = BTB_RQW_APIKey())):
			echo '<div id="message" class="updated fade"><p>';
			echo '<strong>Rate Quote Widget</strong> requires an <strong>API Key</strong>.', PHP_EOL;
			echo 'Without one, the form will be hidden from your website.', PHP_EOL;
			echo 'To get an API Key for the <a href="', admin_url('options-general.php?page=RateQuoteWidget'), '">Rate Quote Widget</a>, visit ',
				'<a href="http://ratequotewidget.com/?apikey" target="_blank">http://ratequotewidget.com</a>.';
			echo '</p></div>';
		endif;
	}
	/**
	 * Register the Admin Page in Settings > Rate Quote Widget.
	 */
	static public function AdminMenu(){
		if(!current_user_can('activate_plugins')) return; // Only Administrators
		$page = add_options_page(
			'Rate Quote Widget by Dan Green (http://themortgagreports.com/rate-quote-widget/)', 'Rate Quote Widget',
			'activate_plugins', 'RateQuoteWidget', array(__CLASS__, 'AdminPage')
		);
		// Hook the load to capture posts
		if(!empty($page)){
			add_action("load-{$page}", array(__CLASS__, 'AdminPageLoad'));
		}
	}
	/**
	 * Capture POSTs and update settings.
	 */
	static public function AdminPageLoad(){
		if(strcasecmp($_SERVER['REQUEST_METHOD'], 'POST')) return;
		check_admin_referer('rate_quote_widget_config');
		$_POST = stripslashes_deep($_POST); // Strip slashes (WP is crazy)
		update_option('btbrqw_apikey', trim($_POST['APIKey']));
		$_POST['CSSource'] = trim($_POST['CSSource']);
		update_option('btbrqw_css_override', $_POST['CSSource']);
		update_option('btbrqw_show_link', (bool)$_POST['ShowLink']);
		update_option('btbrqw_no_clones', (bool)$_POST['NoClones']);
		update_option('btbrqw_title', trim($_POST['WTitle']));
		wp_redirect($_SERVER['REQUEST_URI']);
		die();
	}
	/**
	 * Output the settings screen.
	 */
	static public function AdminPage(){ ?>
		<style type="text/css">
			div.wrap span.faded { color: #ccc; text-shadow: 1px 1px 2px #fff; }
			div.wrap div.cssurl { margin: 2px auto; padding: 5px 10px; border: 1px solid #eee; border-radius: 5px; background: #f7f7f7; }
			div.wrap acronym.hinter { border-bottom: 1px dotted #aaa; cursor: help; font-weight: bold; }
			div#CSSURLs { border: 1px solid transparent; }
		</style>
		<div class="wrap">
			<h2 style="font-family: georgia; font-style: italic;">Rate Quote Widget</h2>
			<p>We built Rate Quote Widget to convert leads efficiently and effectively.<br />
				Leveraging proven buyer psychology patterns and advanced lead generation techniques,
				Rate Quote Widget sorts through your inbound leads to find the most ready, willing and qualified customers. Fast.</p>
			<h3>Configure Rate Quote Widget</h3>
			<form method="post">
				<?php wp_nonce_field('rate_quote_widget_config'); ?>
				<table class="form-table">
					<tr>
						<th><label for="BTB_RQW_WTitle"><strong>Widget Title</strong></label></th>
						<td>
							<input type="text" name="WTitle" id="BTB_RQW_WTitle" value="<?php echo esc_attr(trim(get_option('btbrqw_title', null))); ?>" size="75" /><br />
							<label class="setting-description" for="BTB_RQW_WTitle">
								Default Value : <strong>Live Rate Quotes</strong>.
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="BTB_RQW_APIKey"><strong>API ID</strong></label></th>
						<td>
							<input type="text" name="APIKey" id="BTB_RQW_APIKey" value="<?php echo esc_attr(BTB_RQW_APIKey()); ?>" size="75" /><br />
							<label class="setting-description" for="BTB_RQW_APIKey">
								Login to <a href="http://ratequotewidget.com/" target="_blank">lookup your API Key</a>.
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="BTB_RQW_CSSource"><strong>Override CSS</strong></label></th>
						<td>
							<input type="text" name="CSSource" id="BTB_RQW_CSSource" value="<?php echo esc_attr(BTB_RQW_CSSource()); ?>" class="widefat" /><br />
							<label class="setting-description" for="BTB_RQW_CSSource">
								<span class="faded">
									Want to alter your Rate Quote Widget style? Create a custom CSS file and provide its path here.<br />
								</span>
							</label>
							<div>
								<strong>Remote CSS</strong> <code><?php echo BTB_RQW_HOME.'/rqwng.css'; ?></code> <em>(Default CSS for Rate Quote Widget)</em>.<br />
								<?php if(($cssfiles = glob(BTB_RQW_PATH.'/*.css')) and !empty($cssfiles)){ ?>
									<p><strong>#<?php echo number_format($count = count($cssfiles)); ?>
										A CSS stylesheet <?php echo ($count > 1) ? 's have' : ' has'; ?></strong> been found in the
										<acronym title="Path: <?php echo esc_attr(BTB_RQW_RELPATH); ?>" class="hinter">Plugin's Directory</acronym>.
										<a href="#" id="ToggleCSSURLs">Toggle Visibility&hellip;</a></p>
									<div id="CSSURLs">
										<?php foreach($cssfiles as $offset => $cssfile){
											$cssfile = basename($cssfile);
											echo '<div class="cssurl">', BTB_RQW_URL, '/<strong>', $cssfile, '</strong></div>';
										} ?>
										<!--<p><em>Copy any and use as Custom CSS for the Widget.</em></p>-->
									</div>
									<script type="text/javascript">
									/* <![CDATA[ */
									jQuery(function(){
										jQuery('#ToggleCSSURLs').click(function(){
											jQuery('#CSSURLs').slideToggle();
											return false;
										});
										jQuery('#CSSURLs').hide();
									});
									/* ]]> */
									</script>
								<?php } ?>
							</div>
						</td>
					</tr>
					<tr>
						<th><label for="BTB_RQW_NoClones"><strong>Prevent Double Output</strong></label></th>
						<td>
							<?php if(defined('BTB_RQW_PREVENTCLONES')): ?>
								<input type="checkbox" disabled="disabled" <?php checked(true, (bool)constant('BTB_RQW_PREVENTCLONES')); ?> />
							<?php else: ?>
								<input type="checkbox" <?php checked(true, BTB_RQW_PreventClones()); ?> name="NoClones" ID="BTB_RQW_NoClones" />
							<?php endif; ?>
							<label class="setting-description" for="BTB_RQW_NoClones">
								Prevent the Rate Quote Widget from displaying more than once per page.<br />
								<!--<em>* Note: "Double Output" suppression is overriden when the Rate Quote Widget is manually disabled for a given page/post.</em>-->
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="BTB_RQW_ShowLink"><strong>Show Link to Author</strong></label></th>
						<td>
							<input type="checkbox" <?php checked(true, BTB_RQW_ShowLink()); ?> value="1" name="ShowLink" ID="BTB_RQW_ShowLink" />
							<label class="setting-description" for="BTB_RQW_ShowLink">
							</label>
						</td>
					</tr>
				</table>
				<p><input type="submit" class="button-primary" value="Update Settings" /></p>
			</form>
			<p>&nbsp;</p>
			<h3>To automatically use the Rate Quote Widget</h3>
			<p>Go to your <code>Appearance &raquo; Widgets</code> screen and drag the <strong>Rate Quote Widget</strong> where you'd like it to appear in your site's sidebar.</p>
			<h3>To manually use the Rate Quote Widget</h3>
			<p>
				Paste <code>&lt;?php BTB_RQW_PrintWidget(); ?&gt;</code> in your theme where you want the form to be shown.<br />
				For pages only (i.e. not posts), you may paste the shortcode <code>[ratequotewidget/]</code> in your blog editor. The Rate Quote Widget will appear where you use this shortcode.			
			</p>
			<div style="margin: 25px auto; margin-top: 50px; border-top: 1px solid #ddd;">
				<p style="float: right; color: #000; text-shadow: 1px 1px 1px #eee;">&copy; <?php echo date("Y");?>, Rate Quote Widget, LLC.</p>
				<p>You may also like <a href="http://mortga.ge/charts" target="_blank">Real Estate Chart Of The Day</a>.</p>
			</div>
		</div>
	<?php }
};
BTB_RQW::Construct(); // Kick in
// ------------------------------------------------------------- //
?>