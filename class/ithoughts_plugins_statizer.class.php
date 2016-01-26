<?php

class ithoughts_plugins_statizer_interface{
	static protected $basePlugin;
	static protected $plugin_base;
	static protected $options;
	static protected $base_url;
	static protected $base_lang;
	static protected $base;
	static protected $scripts;
	static protected $optionsConfig;
	static protected $clientsideOverridable;
	static protected $serversideOverridable;
	static protected $handledAttributes;
	static protected $minify = ".min";
	static protected $plugin_key = "ithoughts_plugins_statizer";

	public function getPluginOptions($defaultsOnly = false){
		return self::$basePlugin->getOptions($defaultsOnly);
	}
	public static function getiThoughtsPluginsStatizer(){
		return self::$basePlugin;
	}
}
class ithoughts_plugins_statizer extends ithoughts_plugins_statizer_interface{
	private $plugin_dir;
	private $defaults;
	private $shortcodeDatas = array();
	private $themes = array(
		"various" => array(
			"axis" => array(
				"#fcc",
				"#cfc",
			),
			"series" => array(
				array( // Red
					"6A0500",
					"AA0800",
					"F60C00",
					"B70900",
					"900700"
				),
				array( // Green
					"006A1B",
					"00AA2B",
					"00F63E",
					"00B72E",
					"009024"
				),
				array( // Cyan
					"01686A",
					"02A7AA",
					"03F2F6",
					"02B3B7",
					"028E90"
				),
				array( // Blue
					"00196A",
					"0027AA",
					"0039F6",
					"002AB7",
					"002190"
				),
				array( // Pink
					"63006A",
					"9F00AA",
					"E600F6",
					"AB00B7",
					"870090"
				)
			)
		),
		"gerkindevelopment" => array(
			"axis" => array(
				"#fcc",
				"#cfc",
			),
			"series" => array(
				array(
					"13664B",
					"2DF2B2",
					"1FA67A",
					"21B383",
					"18805E"
				),
				array(
					"006A1B",
					"00AA2B",
					"00F63E",
					"00B72E",
					"009024"
				),
				array(
					"0F2A1A",
					"42B670",
					"266A41",
					"2B7749",
					"19442A"
				),
				array(
					"112A28",
					"49B6AB",
					"2A6A63",
					"2F776F",
					"1B4440"
				),
				array(
					"052A2A",
					"15B4B6",
					"0C696A",
					"0D7577",
					"084344"
				)
			)
		),
	);

	function __construct($plugin_base) {
		if(defined("WP_DEBUG") && WP_DEBUG)
			parent::$minify = "";
		parent::$basePlugin		= &$this;
		parent::$plugin_base	= $plugin_base;
		parent::$base			= $plugin_base . '/class';
		parent::$base_lang		= $plugin_base . '/lang';
		parent::$base_url		= plugins_url( '', dirname(__FILE__) );

		$this->defaults = array(
			"plugins" => array()
		);
		parent::$options		= $this->initOptions();

		$wp_upload = wp_upload_dir()["basedir"];
		$this->plugin_dir = $wp_upload."/ithoughts_plugins_statizer";

		add_shortcode( 'update_scores', array($this, 'updateShortcode') );
		add_shortcode( 'plugins_stats', array($this, 'pluginStats') );


		add_action( 'init',								array( &$this,	'register_scripts_and_styles')	);
		add_action( 'ithoughts_plugins_statizer_cron',	array( &$this,	'refresh')						);
		add_action( 'wp_enqueue_scripts',						array( &$this,	'wp_enqueue_scripts')			);
		add_action( 'wp_ajax_ithoughts_plugins_statizer-get_chart_ajax',			array(&$this, 'get_chart_ajax') );
		add_action( 'wp_ajax_nopriv_ithoughts_plugins_statizer-get_chart_ajax',	array(&$this, 'get_chart_ajax') );
		add_action( 'plugins_loaded',				array(&$this,	'localisation')							);
		add_action( "wp_footer",					array(&$this , "footer"));

		add_filter( "ithoughts_plugins_statizer-crunch_data", array($this, "crunch_datas"), 10, 5);
		add_filter( "ithoughts_plugins_statizer-format_base_chart", array($this, "format_base_chart"));

		require_once( parent::$base . '/ithoughts_plugins_statizer-widget.class.php' );
		add_action( 'widgets_init', array($this, 'widgets_init') );

	}
	public function addShortcodePlugin($plugin){
		$this->shortcodeDatas[count($this->shortcodeDatas)] = $plugin;
	}
	public function widgets_init(){
		register_widget( 'ithoughts_plugins_statizer_widget' );
	}
	public function updateShortcode(){
		/**/
		do_action("ithoughts_plugins_statizer_cron", true);
		do_action("ithoughts_plugins_statizer_cron", false);
		/**/
	}
	public function get_chart_ajax(){
		if(isset($_POST) && $post = $_POST["data"]){
			$plugins = $post["plugins"];
			$details = $post["details"];
			$chartId = $post["chartId"];
			$maxDays = isset($post["maxDays"]) && intval($post["maxDays"]) > 0 ? intval($post["maxDays"]) : NULL;
			$theme = isset($post["theme"]) ? $post["theme"] : "various";
				wp_send_json_success(apply_filters("ithoughts_plugins_statizer-crunch_data", $plugins, $details, $chartId, $theme, $maxDays));
		}
		wp_die();
	}
	public function pluginStats($attrs, $content = ""){
		wp_enqueue_script("ithoughts_plugins_statizer-main");
		wp_enqueue_style("ithoughts_plugins_statizer-main");
		if(!isset($attrs["plugins"]) && !isset($attrs["plugin"])){
			return;
		}

		$plugins = array();
		{
			$pluginsStr = (isset($attrs["plugins"]) ? $attrs["plugins"] : $attrs["plugin"]);
			$pluginsNames = explode(",", $pluginsStr);
			foreach($pluginsNames as $index => $plugin){
				$pluginsNames[$index] = trim($plugin);
			}
			$plugins = $pluginsNames;
		}

		$details = array();
		{
			$detailsStr = (isset($attrs["details"]) ? $attrs["details"] : "");
			$detailsArray = explode(",",$detailsStr);
			foreach($detailsArray as $index => $detail){
				$str = trim($detail);
				if(strpos($str, ".") !== false){
					$array = explode(".", $str);
					$ordonedArray = true;
					for($i = count($array) - 1; $i > 0; $i--){
						$ordonedArray  = array($array[$i] => $ordonedArray);
					}
					if(!(isset($details[$array[$i]]) && $details[$array[$i]] === true))
						$details[$array[$i]] = array_merge_recursive($ordonedArray, isset($details[$array[$i]]) ? $details[$array[$i]] : array());
				} else {
					$details[$str] = true;
				}
			}
		}

		$hightchartsId = "plugins_data-".substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 15);

		$baseChartData = array(
			"title" => $attrs["title"],
			"id" => $hightchartsId
		);
		$theme = isset($attrs["theme"]) ? $attrs["theme"] : NULL;
		if(!(isset($attrs["ajax"]) && $attrs["ajax"] === "true")){
			$baseChartData["ajax"] = false;
			if($theme)
				$this->addShortcodePlugin(apply_filters("ithoughts_plugins_statizer-crunch_data", $plugins, $details, $hightchartsId, $theme));
			else
				$this->addShortcodePlugin(apply_filters("ithoughts_plugins_statizer-crunch_data", $plugins, $details, $hightchartsId));
		} else {
			$baseChartData["ajax"] = true;
			$baseChartData["plugins"] = $plugins;
			$baseChartData["details"] = $details;
			if($theme)
				$baseChartData["theme"] = $theme;
		}

		return apply_filters("ithoughts_plugins_statizer-format_base_chart", $baseChartData);
	}
	public function format_base_chart($attrs){
		if(isset($attrs["ajax"]) && $attrs["ajax"] === true){
			return "<div id=\"{$attrs["id"]}\" data-title=\"{$attrs["title"]}\" data-ajaxed=\"true\" data-plugins=\"".urlencode(json_encode($attrs["plugins"]))."\" data-details=\"".urlencode(json_encode($attrs["details"]))."\"".(isset($attrs["maxDays"]) ? " data-maxDays=\"{$attrs["maxDays"]}\"" : "").(isset($attrs["theme"]) ? " data-theme=\"{$attrs["theme"]}\"" : "")."><div class=\"chart\"></div><div class=\"customLegend\"></div></div>";
		}
		return "<div id=\"{$attrs["id"]}\" data-title=\"{$attrs["title"]}\"".(isset($attrs["maxDays"]) ? " data-maxDays=\"{$attrs["maxDays"]}\"" : "")."><div class=\"chart\"></div><div class=\"customLegend\"></div></div>";
	}
	public function crunch_datas($plugins, $details, $chartId, $theme = NULL, $maxDays = NULL){
		if($theme === NULL)
			$theme = "various";
		if($maxDays === NULL)
			$maxDays = 100;
		$plugins_infos = array();
		foreach($plugins as $pluginName){
			$plugins_infos[$pluginName] = $this->getInfos($pluginName);
		}

		// Filter plugins infos
		$details = array_replace_recursive($details, array("name" => true, "creationDate" => true));
		foreach($plugins_infos as $plugin => $pluginData){
			$plugins_infos[$plugin] = $this->filterPluginData($pluginData, $details);
		}
		$data = array(
			"plugins" => $plugins_infos,
			"chartId" => $chartId,
			"maxDays" => $maxDays,
			"colors" => $this->themes[$theme]
		);
		return $data;
	}
	public function wp_enqueue_scripts(){
		wp_localize_script("ithoughts_plugins_statizer-main", "ithoughts_plugins_statizer", array(
			"ajax" => admin_url('admin-ajax.php'),
			"lang" => array(
				"dateformat" => array(
					"full" => _x("%a, %b %e, %Y", "Full date", "ithoughts_plugins_statizer"),
					"week" => _x("%e. %b", "Week date", "ithoughts_plugins_statizer"),
					"month" => _x("%b '%y", "Month based date", "ithoughts_plugins_statizer"),
				),
				"highcharts" => array(
					"months" => array(__('January',"ithoughts_plugins_statizer"), __('February',"ithoughts_plugins_statizer"), __('March',"ithoughts_plugins_statizer"), __('April',"ithoughts_plugins_statizer"), __('May',"ithoughts_plugins_statizer"), __('June',"ithoughts_plugins_statizer"),  __('July',"ithoughts_plugins_statizer"), __('August',"ithoughts_plugins_statizer"), __('September',"ithoughts_plugins_statizer"), __('October',"ithoughts_plugins_statizer"), __('November',"ithoughts_plugins_statizer"), __('December',"ithoughts_plugins_statizer")),
					"weekdays" => array(__('Sunday',"ithoughts_plugins_statizer"), __('Monday',"ithoughts_plugins_statizer"), __('Tuesday',"ithoughts_plugins_statizer"), __('Wednesday',"ithoughts_plugins_statizer"), __('Thursday',"ithoughts_plugins_statizer"), __('Friday',"ithoughts_plugins_statizer"), __('Saturday',"ithoughts_plugins_statizer"))
				),
				"labels" => array(
					"active" => __("Active","ithoughts_plugins_statizer"),
					"downloads" => __("Downloads","ithoughts_plugins_statizer"),
					"events" => __("Events","ithoughts_plugins_statizer"),
					"yAxisDownloads" => __("Downloads per day","ithoughts_plugins_statizer"),
					"yAxisActive" => __("Active installs","ithoughts_plugins_statizer"),
					"xAxisDate" => __("Date","ithoughts_plugins_statizer"),
					"events" => __("Events","ithoughts_plugins_statizer"),
					"downloadSerie" => __("<b>{0}</b> downloads","ithoughts_plugins_statizer"),
					"activeSerie" => __("<b>{0}</b> active installs","ithoughts_plugins_statizer"),
					"eventVersion" => __("Release of v{0} of <b>{1}</b>","ithoughts_plugins_statizer"),
					"eventDownloads" => __("More than {0} downloads of <b>{1}</b>","ithoughts_plugins_statizer"),
					"eventActive" => __("More than {0} active installs of <b>{1}</b>","ithoughts_plugins_statizer"),
				)
			)
		));
	}
	public function footer(){
		wp_localize_script("ithoughts_plugins_statizer-main", "ithoughts_plugins_statizer_plugins", $this->shortcodeDatas);
	}
	private function filterPluginData($pluginData, $details){
		$ret = array();
		foreach($details as $detail => $took){
			if($took && isset($pluginData[$detail])){
				if(is_array($took)){
					$det = $this->filterPluginData($pluginData[$detail], $took);
					if(!empty($det))
						$ret[$detail] = $det;
				} else {
					$ret[$detail] = $pluginData[$detail];
				}
			}
		}
		return $ret;
	}
	public function register_scripts_and_styles(){
		wp_register_script('ithoughts_aliases', parent::$base_url . "/submodules/iThoughts-WordPress-Plugin-Toolbox/js/ithoughts_aliases".parent::$minify.".js", array('jquery'), null, false);
		wp_register_script('highcharts', parent::$base_url . '/ext/highcharts/js/highcharts.js', null, null, true);
		wp_register_script('highstock', parent::$base_url . '/ext/highstock/js/highstock.js', /*/array("highcharts")/*/null/**/, null, true);
		wp_register_script('ithoughts_plugins_statizer-main', parent::$base_url . "/resources/ithoughts_plugins_statizer".parent::$minify.".js", array('jquery', "ithoughts_aliases", "highstock"), null, true);

		wp_register_style('ithoughts_plugins_statizer-main', parent::$base_url . "/resources/ithoughts_plugins_statizer".parent::$minify.".css" );
	}

	public static function get_instance(){
		if(is_null(parent::$basePlugin)) {
			parent::$basePlugin = new ithoughts_plugins_statizer( dirname(dirname(__FILE__)) );  
		}
		return parent::$basePlugin;
	}



	public function getOptions($onlyDefaults = false){
		if($onlyDefaults)
			return $this->defaults;

		return parent::$options;
	}
	private function initOptions(){
		$opts = array_merge($this->getOptions(true), get_option( parent::$plugin_key, $this->getOptions(true) ));
		return $opts;
	}



	public static function activationHook(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$self = self::get_instance();
		wp_schedule_event(time(), 'hourly', "ithoughts_plugins_statizer_cron", true);
		wp_schedule_event(time(), 'daily', "ithoughts_plugins_statizer_cron", false);
	}
	public static function deactivationHook(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$self = self::get_instance();
		wp_clear_scheduled_hook("ithoughts_plugins_statizer_hourly");
		wp_clear_scheduled_hook("ithoughts_plugins_statizer_daily");
	}
	public function refresh($hourly){
		var_dump($hourly);
		if($hourly){
			foreach(parent::$options["plugins"] as $plugin){
				$infos				= $this->getInfos($plugin);

				$downloadsToday		= $this->getDownloadsToday($plugin);
				$downloadsTodayKeys = array_keys($downloadsToday);
				$infos["downloadsToday"] = $downloadsToday[$downloadsTodayKeys[0]];

				$this->setInfos($plugin, $infos);
			}
		} else {
			try{
				if(file_exists($this->plugin_dir)){
					if(!is_dir($this->plugin_dir)){
						unlink($this->plugin_dir);
						mkdir($this->plugin_dir);
					}
				} else {
					mkdir($this->plugin_dir);
				}

				$dateT = new DateTime();
				$dateY = clone $dateT;
				$dateY->add(DateInterval::createFromDateString('yesterday'));
				$dateYY = clone $dateY;
				$dateYY->add(DateInterval::createFromDateString('yesterday'));

				$dateT = $dateT->format("Y-m-d");
				$dateY = $dateY->format("Y-m-d");
				$dateYY = $dateYY->format("Y-m-d");
				/*echo "$dateT $dateY $dateYY";*/

				foreach(parent::$options["plugins"] as $plugin){
					$infos				= $this->getInfos($plugin);
					$pluginInfos		= $this->getPluginInfos($plugin);
					$downloadsLastsDays	= $this->getDownloadsLastsDays($plugin);
					$downloadsToday		= $this->getDownloadsToday($plugin);

					echo "<h2>$plugin</h2>";
					echo "<h3>== Stored ==</h3>";
					var_dump( $infos );
					echo "<h3>== Infos ==</h3>";
					var_dump( $pluginInfos );
					echo "<h3>== Downloads last 50 days ==</h3>";
					var_dump( $downloadsLastsDays );
					echo "<h3>== Downloads today ==</h3>";
					var_dump( $downloadsToday );



					if(isset($pluginInfos["name"]))
						$infos["name"] = $pluginInfos["name"];
					if(isset($pluginInfos["downloaded"]) && $pluginInfos["downloaded"] != NULL){
						$powTenDLs = intval(log10(intval($pluginInfos["downloaded"])));
						if(intval(log10($infos["downloaded"])) < $powTenDLs){
							$infos["events"]["downloads"][$dateT] = intval(pow(10, $powTenDLs));
						}
					}
					if(isset($pluginInfos["downloaded"]))
						$infos["downloaded"] = intval($pluginInfos["downloaded"]);
					if(isset($pluginInfos["creationDate"]))
						$infos["creationDate"] = $pluginInfos["creationDate"];
					if(isset($pluginInfos["active_installs"]) && $pluginInfos["active_installs"] !== NULL){ 
						$active = intval($pluginInfos["active_installs"]);
						if($active == 0)
							$active = 1;
						$infos["active"][$dateY] = $active;
						$powTenACs = intval(log10($active));
						if(isset($infos["active"][$dateYY]) && intval(log10($infos["active"][$dateYY])) < $powTenACs){
							$infos["events"]["active"][$dateY] = intval(pow(10, $powTenACs));
						}
					}
					if(isset($pluginInfos["version"]) && $infos["version"] != $pluginInfos["version"]){
						$infos["version"] = $pluginInfos["version"];
						$infos["events"]["versions"][$dateY] = $pluginInfos["version"];
					}

					if($pluginInfos["name"] != NULL){
						foreach($downloadsLastsDays as $date => $downloads){
							$infos["downloads"][$dateT] = intval($downloads);
						}
					}

					if(is_array($downloadsToday)){
						$downloadsTodayKeys = array_keys($downloadsToday);
						if(count($downloadsTodayKeys) > 0)
							$infos["downloadsToday"] = intval($downloadsToday[$downloadsTodayKeys[0]]);
					}

					$this->setInfos($plugin, $infos);
				}



			} catch(Exception $e){
				var_dump($e);
			}
		}
	}
	private function getInfos($plugin_name){
		if(!file_exists($this->plugin_dir."/$plugin_name.json")){
			$desc = fopen($this->plugin_dir."/$plugin_name.json", "w");
			fclose($desc);
		}
		$infos = json_decode(file_get_contents($this->plugin_dir."/$plugin_name.json"), true);
		$infos = array_replace_recursive(
			array(
				"name" => NULL,
				"downloaded" => 0,
				"downloads" => array(),
				"downloadsToday" => 0,
				"active" => array(),
				"events" => array(
					"versions" => array(),
					"downloads" => array(),
					"active" => array()
				),
				"creationDate" => NULL,
			),
			$infos == null ? array() : $infos
		);
		$infos["downloaded"] = intval($infos["downloaded"]);
		$infos["downloadsToday"] = intval($infos["downloadsToday"]);
		foreach($infos["downloads"] as $date => $value)
			$infos["downloads"][$date] = intval($value);
		foreach($infos["active"] as $date => $value)
			$infos["active"][$date] = intval($value);
		return $infos;
	}
	private function getPluginInfos($plugin_name){
		$request_infos = array(
			'action' => 'plugin_information',
			'request' => serialize(
				(object)array(
					'slug' => $plugin_name,
					'fields' => array(
						'downloaded' => true,
						'active_installs' => true
					)
				)
			)
		);
		$body = wp_remote_post( 'http://api.wordpress.org/plugins/info/1.0/', array('body' => $request_infos));
		if($body instanceof WP_Error)
			var_dump($body);
		else {
			$body = $body['body'];
			$data = (array)unserialize($body);
			if(isset($data["name"]) && isset($data["version"]) && isset($data["active_installs"]) && isset($data["downloaded"]) && isset($data["added"])){
				return array(
					"name" => $data["name"],
					"version" => $data["version"],
					"active_installs" => $data["active_installs"],
					"downloaded" => $data["downloaded"],
					"creationDate" => $data["added"],
				);
			} else return null;
		}
	}
	private function getDownloadsLastsDays($plugin_name){
		$body = wp_remote_post("http://api.wordpress.org/stats/plugin/1.0/downloads.php?slug=$plugin_name&limit=50", array());
		if($body instanceof WP_Error)
			var_dump($body);
		else {
			$body = $body['body'];
			$data = json_decode($body, true);
			if(is_array($data))
				return $data;
			else
				return array();
		}
	}
	private function getDownloadsToday($plugin_name){
		$body = wp_remote_post("http://api.wordpress.org/stats/plugin/1.0/downloads.php?slug=$plugin_name&limit=1", array());
		if($body instanceof WP_Error)
			var_dump($body);
		else {
			$body = $body['body'];
			$data = json_decode($body, true);
			if(is_array($data))
				return $data;
			else
				return array();
		}
	}
	private function setInfos($plugin_name, $data){
		file_put_contents($this->plugin_dir."/$plugin_name.json", json_encode($data));
	}
	public function localisation(){
		load_plugin_textdomain( 'ithoughts_plugins_statizer', false, plugin_basename( dirname( __FILE__ ) )."/../lang" );
	}
}