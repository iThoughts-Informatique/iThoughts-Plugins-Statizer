<?php

class ithoughts_plugins_statizer_admin extends ithoughts_plugins_statizer_interface{
	public function __construct(){
		add_action( 'admin_init',			array( &$this,	'register_scripts_and_styles')	);
		add_action( "admin_menu",	array(&$this, "menuPages"));
		add_action( "admin_enqueue_scripts", array( &$this, "enqueue_scripts"));

		add_action( "wp_ajax_ithoughts_plugins_statizer_update_options", array( &$this, "update_options"));
	}

	public function menuPages(){
		add_options_page(__("iThoughts Plugins Statizer", "ithoughts_plugins_statizer"), __("iThoughts Plugins Statizer", "ithoughts_plugins_statizer"), "manage_options", "ithoughts_plugins_statizer", array(&$this, "options"));
	}

	public function register_scripts_and_styles(){
		wp_register_script(
			'ithoughts-simple-ajax',
			parent::$base_url . '/submodules/iThoughts-WordPress-Plugin-Toolbox/js/simple-ajax-form'.parent::$minify.'.js',
			array('jquery-form',"ithoughts_aliases"),
			null
		);

		wp_register_script(
			'ithoughts-plugins-statizer-taggle',
			parent::$base_url . '/ext/taggle/src/taggle.js',
			array("ithoughts_aliases"),
			null
		);
		wp_register_script(
			'ithoughts-plugins-statizer-options',
			parent::$base_url . '/resources/ithoughts_plugins_statizer-options'.parent::$minify.'.js',
			array("ithoughts_aliases",'ithoughts-plugins-statizer-taggle',"ithoughts-simple-ajax"),
			null
		);

		wp_register_style(
			"ithoughts-plugins-statizer-taggle",
			parent::$base_url . '/ext/taggle/assets/css/taggle.css',
			false
		);
	}

	public function enqueue_scripts(){
	}
	public function options(){
		wp_enqueue_script('ithoughts-plugins-statizer-options');
		wp_enqueue_style('ithoughts-plugins-statizer-taggle');
		$ajax         = admin_url( 'admin-ajax.php' );
		$pluginsMonitored = implode(", ", parent::$options["plugins"]);
?>
<div class="wrap">
	<div id="ithoughts-plugins-statizer-options" class="meta-box meta-box-50 metabox-holder">
		<div class="meta-box-inside admin-help">
			<div class="icon32" id="icon-options-general">
				<br>
			</div>
			<h2><?php _e('Options', 'ithoughts_plugins_statizer'); ?></h2>
			<div id="dashboard-widgets-wrap">
				<div id="dashboard-widgets">
					<div id="normal-sortables">
						<form action="<?php echo $ajax; ?>" method="post" class="simpleajaxform" data-target="update-response">
							<table>
								<tr>
									<td>
										<label for="pluginsMonitored"><?php _e("Plugins monitored","ithoughts_plugins_statizer"); ?></label>
									</td>
									<td>
										<div id="pluginsMonitored" class="taggle" data-values="<?php echo $pluginsMonitored; ?>"/>
									</td>
								</tr>
								<tr>
									<td colspan="2">
										<input autocomplete="off" type="hidden" name="action" value="ithoughts_plugins_statizer_update_options"/>
										<button class="alignleft button-primary"><?php _e('Update options', 'ithoughts_plugins_statizer'); ?></button>
									</td>
								</tr>
							</table>
						</form>
						<div id="update-response" class="clear confweb-update"></div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<?php
	}

	public function update_options(){
		if(isset($_POST) && $data = $_POST){
			$opts = array(
				"plugins" => array()
			);
			if(isset($data["taggles"]))
				$opts["plugins"] = $data["taggles"];

			
			update_option( 'ithoughts_plugins_statizer', $opts );
			parent::$options = $opts;
			$outtxt = ('<p>' . __('Options updated', 'ithoughts_plugins_statizer') . '</p>') ;
			die( json_encode(array(
				"text" =>$outtxt,
				"valid" => true
			)));
		} else {
			wp_die();
		}
	}
}