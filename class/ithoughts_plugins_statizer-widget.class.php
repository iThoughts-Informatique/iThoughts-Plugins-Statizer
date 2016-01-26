<?php

class ithoughts_plugins_statizer_widget extends WP_Widget{
	public function __construct() {
		parent::__construct(
			'ithoughts_plugins_statizer-widget',
			__('Stats for your plugins', 'ithoughts_plugins_statizer'),
			array( 
				'classname'   => 'ithoughts_plugins_statizer_widget',
				'description' => __('Display a widget with stats of a plugin', 'ithoughts_plugins_statizer'),
			)
		);
	}

	private function generate_meta_keys(){
		global $wpdb;
		$query = "
        SELECT DISTINCT($wpdb->postmeta.meta_key) 
        FROM $wpdb->posts 
        LEFT JOIN $wpdb->postmeta 
        ON $wpdb->posts.ID = $wpdb->postmeta.post_id 
        WHERE $wpdb->postmeta.meta_key != '' 
        AND $wpdb->postmeta.meta_key NOT RegExp '(^[_0-9].+$)' 
        AND $wpdb->postmeta.meta_key NOT RegExp '(^[0-9]+$)'
    ";
		$meta_keys = $wpdb->get_col($wpdb->prepare($query, $post_type));
		set_transient('meta_keys', $meta_keys, 60*60*24); # 1 Day Expiration
		return $meta_keys;
	}

	private function get_meta_keys(){
		$cache = get_transient('meta_keys');
		$meta_keys = $cache ? $cache : generate_foods_meta_keys();
		return $meta_keys;
	}


	// Admin form
	public function form( $instance=array() ) {
		wp_enqueue_script('ithoughts-plugins-statizer-options');
		wp_enqueue_style('ithoughts-plugins-statizer-taggle');

		$instance =  wp_parse_args( $instance, array(
			'title' => __('Plugin stat', 'ithoughts_plugins_statizer'),
			"theme" => "various",
			'plugins' => array(),
			'postmeta' => "",
			'days' => 10,
			'ajaxed' => true
		) );

		$options = ithoughts_plugins_statizer_interface::getiThoughtsPluginsStatizer()->getOptions();
		$pluginsMonitored = '<option value="">'.__("( None )", "ithoughts_plugins_statizer").'</option>';
		foreach($options["plugins"] as $plugin){
			$pluginsMonitored .= "<option value=\"$plugin\"".(in_array($plugin, $instance["plugins"]) ? " selected=\"selected\"" : "").">$plugin</option>";
		}
		$metaKeys = get_meta_keys();
		$metaDatalist = "";
		foreach($metaKeys as $metaKey){
			$metaDatalist .= "<option value=\"$metaKey\">";
		}
?>
<table>
	<tr>
		<td>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title', 'ithoughts_tooltip_glossary'); ?></label>
		</td>
		<td>
			<input autocomplete="off" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</td>
	</tr>
	<tr>
		<td>
			<label for="<?php echo $this->get_field_id('theme'); ?>"><?php _e("Theme","ithoughts_plugins_statizer"); ?></label>
		</td>
		<td>
			<input type="text" id="<?php echo $this->get_field_id('theme'); ?>" name="<?php echo $this->get_field_name('theme'); ?>" value="<?php echo $instance["theme"]; ?>"/>
		</td>
	</tr>
	<tr>
		<td>
			<label for="<?php echo $this->get_field_id('plugins[]'); ?>"><?php _e("Plugins monitored","ithoughts_plugins_statizer"); ?></label>
		</td>
		<td>
			<select id="<?php echo $this->get_field_id('plugins[]'); ?>" name="<?php echo $this->get_field_name('plugins[]'); ?>" multiple="multiple">
				<?php echo $pluginsMonitored ?>
			</select>
		</td>
	</tr>
	<tr>
		<td>
			<label for="<?php echo $this->get_field_id('postmeta'); ?>"><?php _e("Or post-meta","ithoughts_plugins_statizer"); ?></label>
		</td>
		<td>
			<input type="text" list="metaDatalist" id="<?php echo $this->get_field_id('postmeta'); ?>" name="<?php echo $this->get_field_name('postmeta'); ?>" value="<?php echo $instance["postmeta"]; ?>"/>
			<datalist id="metaDatalist">
				<?php echo $metaDatalist; ?>
			</datalist>
		</td>
	</tr>
	<tr>
		<td>
			<label for="<?php echo $this->get_field_id('days'); ?>"><?php _e("Days displayed","ithoughts_plugins_statizer"); ?></label>
		</td>
		<td>
			<input type="number" min="0" id="<?php echo $this->get_field_id('days'); ?>" name="<?php echo $this->get_field_name('days'); ?>" value="<?php echo $instance["days"]; ?>"/>
		</td>
	</tr>
	<tr>
		<td>
			<label for="<?php echo $this->get_field_id('ajaxed'); ?>"><?php _e("Load via Ajax","ithoughts_plugins_statizer"); ?></label>
		</td>
		<td>
			<input type="checkbox" id="<?php echo $this->get_field_id('ajaxed'); ?>" name="<?php echo $this->get_field_name('ajaxed'); ?>" value="ajaxed" <?php echo $instance["ajaxed"] ? " checked" : ""; ?>/>
		</td>
	</tr>
</table>
<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance				= $old_instance;
		$instance['title']		= strip_tags( $new_instance['title'] );
		$instance['theme']	= $new_instance['theme'];
		$instance['plugins']	= $new_instance['plugins'];
		$instance['postmeta']	= $new_instance['postmeta'];
		$instance['days']		= $new_instance['days'];
		$instance['ajaxed']		= $new_instance['ajaxed'] === "ajaxed";

		return $instance;
	} // update

	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );

		echo $args["before_widget"];
		if( !empty($title) ){
			echo $args["before_title"] . $title . $args["after_title"];
		}

		$days = isset($instance['days']) && intval($instance['days']) > 0 ? intval($instance['days']) : 25;

		$plugins = array();
		if(isset($instance['plugins']))
			$plugins = $instance['plugins'];
		if(isset($instance['postmeta']) && strlen($instance['postmeta']) > 0){
			$meta = get_post_meta( get_the_ID(), $instance['postmeta'], true );
			if($meta)
				$plugins = explode(",",$meta);
		}
		foreach($plugins as $index => $plugin){
			$plugins[$index] = trim($plugin);
		}
		$details = array(
			"downloads" => true,
			"active" => true
		);

		$id = "plugins_data-".substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 15);
		$baseChartData = array(
			"title" => $instance['title'],
			"id" => $id,
			"ajax" => $instance["ajaxed"] == "ajaxed"
		);
		$theme = NULL;
		if(isset($instance["theme"]) && $instance["theme"] && strlen($instance["theme"]))
			$theme = $instance["theme"];
		$baseChartData["theme"] = $theme;
		if($instance["ajaxed"] == "ajaxed"){
			$baseChartData["plugins"] = $plugins;
			$baseChartData["details"] = $details;
			$baseChartData["maxDays"] = $days;
		} else {	
			$data = apply_filters("ithoughts_plugins_statizer-crunch_data", $plugins, $details, $id, $theme, $days);
			ithoughts_plugins_statizer_interface::getiThoughtsPluginsStatizer()->addShortcodePlugin($data);
		}
		echo apply_filters("ithoughts_plugins_statizer-format_base_chart", $baseChartData);

		echo $args["after_widget"];
	} //widget
}