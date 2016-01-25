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

	public function getPluginOptions($defaultsOnly = false){
		return self::$basePlugin->getOptions($defaultsOnly);
	}
	public static function getiThoughtsPluginsStatizer(){
		return self::$basePlugin;
	}
}
class ithoughts_plugins_statizer extends ithoughts_plugins_statizer_interface{
	private $plugin_dir;
	private $plugins;
	private $shortcodeDatas = array();

	function __construct($plugin_base) {
		parent::$basePlugin		= &$this;
		parent::$plugin_base	= $plugin_base;
		parent::$base			= $plugin_base . '/class';
		parent::$base_lang		= $plugin_base . '/lang';
		parent::$base_url		= plugins_url( '', dirname(__FILE__) );
		$wp_upload = wp_upload_dir()["basedir"];
		$this->plugin_dir = $wp_upload."/ithoughts_plugins_statizer";
		$this->plugins = array("ithoughts-tooltip-glossary", "ithoughts-lightbox", "ithoughts-html-snippets");

		add_shortcode( 'update_scores', array($this, 'updateShortcode') );
		add_shortcode( 'plugins_stats', array($this, 'pluginStats') );


		add_action( 'init',								array( &$this,	'register_scripts_and_styles')	);
		add_action( 'ithoughts_plugin_statizer_cron',	array( &$this,	'refresh')						);
		add_action( 'wp_footer',						array( &$this,	'wp_enqueue_scripts')			);
	}
	public function updateShortcode(){
		echo "<pre>";
		var_dump( _get_cron_array() );
		echo "</pre>";
		/*
		do_action("ithoughts_plugin_statizer_cron", true);
		do_action("ithoughts_plugin_statizer_cron", false);
		*/
	}
	public function pluginStats($attrs, $content = ""){
		wp_enqueue_script("ithoughts_plugins_statizer-main");
		echo "<pre>";
		var_dump($attrs);
		echo "</pre>";
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
			foreach($pluginsNames as $pluginName){
				$plugins[$pluginName] = $this->getInfos($pluginName);
			}
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
			$details = array_replace_recursive($details, array("name" => true));
			echo "<pre>";
			var_dump($details);
			echo "</pre>";
		}

		// Filter plugins infos
		foreach($plugins as $plugin => $pluginData){
			$plugins[$plugin] = $this->filterPluginData($pluginData, $details);
			echo "<pre>";
			var_dump($plugins[$plugin]);
			echo "</pre>";
		}

		$hightchartsId = "plugins_data-".substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 15);
		$this->shortcodeDatas[count($this->shortcodeDatas)] = array(
			"plugins" => $plugins,
			"chartId" => $hightchartsId
		);

		echo '<div id="'.$hightchartsId.'"></div>';
	}
	public function wp_enqueue_scripts(){
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
		wp_register_script('ithoughts_aliases', parent::$base_url . '/submodules/iThoughts-WordPress-Plugin-Toolbox/ithoughts_aliases.js', array('jquery'), null, true);
		wp_register_script('highcharts', parent::$base_url . '/ext/highcharts/js/highcharts.js', null, null, true);
		wp_register_script('ithoughts_plugins_statizer-main', parent::$base_url . '/js/ithoughts_plugins_statizer.js', array('jquery', "ithoughts_aliases", "highcharts"), null, true);
	}

	public static function get_instance(){
		if(is_null(parent::$basePlugin)) {
			parent::$basePlugin = new ithoughts_plugins_statizer( dirname(dirname(__FILE__)) );  
		}
		return parent::$basePlugin;
	}

	public static function activationHook(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$self = self::get_instance();
		wp_schedule_event(time(), 'hourly', "ithoughts_plugin_statizer_cron", true);
		wp_schedule_event(time(), 'daily', "ithoughts_plugin_statizer_cron", false);
	}
	public static function deactivationHook(){
		if ( ! current_user_can( 'activate_plugins' ) )
			return;
		$self = self::get_instance();
		wp_clear_scheduled_hook("ithoughts_plugin_statizer_hourly");
		wp_clear_scheduled_hook("ithoughts_plugin_statizer_daily");
	}
	public function refresh($hourly){
		var_dump($hourly);
		if($hourly){
			foreach($this->plugins as $plugin){
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

				foreach($this->plugins as $plugin){
					$infos				= $this->getInfos($plugin);
					$pluginInfos		= $this->getPluginInfos($plugin);
					$downloadsLastsDays	= $this->getDownloadsLastsDays($plugin);
					$downloadsToday		= $this->getDownloadsToday($plugin);

					/*				echo "<h2>$plugin</h2>";
				echo "<h3>== Stored ==</h3>";
				var_dump( $infos );
				echo "<h3>== Infos ==</h3>";
				var_dump( $pluginInfos );
				echo "<h3>== Downloads last 50 days ==</h3>";
				var_dump( $downloadsLastsDays );
				echo "<h3>== Downloads today ==</h3>";
				var_dump( $downloadsToday );*/



					if(isset($pluginInfos["name"]))
						$infos["name"] = $pluginInfos["name"];
					if(isset($pluginInfos["downloaded"]) && $pluginInfos["downloaded"] != NULL){
						$powTenDLs = intval(log10($pluginInfos["downloaded"]));
						if(intval(log10($infos["downloaded"])) < $powTenDLs){
							$infos["events"]["downloads"][$dateT] = pow(10, $powTenDLs);
						}
					}
					if(isset($pluginInfos["downloaded"]))
						$infos["downloaded"] = $pluginInfos["downloaded"];
					if(isset($pluginInfos["creationDate"]))
						$infos["creationDate"] = $pluginInfos["creationDate"];
					if(isset($pluginInfos["active_installs"]) && $pluginInfos["active_installs"] != NULL){
						$infos["active"][$dateY] = $pluginInfos["active_installs"];
						$powTenACs = intval(log10($pluginInfos["active_installs"]));
						if(isset($infos["active"][$dateYY]) && intval(log10($infos["active"][$dateYY])) < $powTenACs){
							$infos["events"]["active"][$dateY] = pow(10, $powTenACs);
						}
					}
					if(isset($pluginInfos["version"]) && $infos["version"] != $pluginInfos["version"]){
						$infos["version"] = $pluginInfos["version"];
						$infos["events"]["versions"][$dateT] = $pluginInfos["version"];
					}

					if($pluginInfos["name"] != NULL){
						foreach($downloadsLastsDays as $date => $downloads){
							$infos["downloads"][$date] = $downloads;
						}
					}

					if(is_array($downloadsToday)){
						$downloadsTodayKeys = array_keys($downloadsToday);
						if(count($downloadsTodayKeys) > 0)
							$infos["downloadsToday"] = $downloadsToday[$downloadsTodayKeys[0]];
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
}