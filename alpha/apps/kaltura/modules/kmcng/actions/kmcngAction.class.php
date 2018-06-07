<?php
/**
 * @package    Core
 * @subpackage KMCNG
 */
class kmcngAction extends kalturaAction
{
	const LIVE_ANALYTICS_UICONF_TAG = 'livea_player';

	public function execute()
	{
		if (!kConf::hasParam('kmcng'))
		{
			KalturaLog::warning("kmcng config doesn't exist in configuration.");
			return sfView::ERROR;
		}

		$kmcngParams = kConf::get('kmcng');

		// Check for forced HTTPS
		if (!isset($kmcngParams["kmcng_debug_mode"]))
		{
			if ((!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on'))
			{
				header("Location: " . infraRequestUtils::PROTOCOL_HTTPS . "://" . $_SERVER['SERVER_NAME'] . $_SERVER["REQUEST_URI"]);
				die();
			}
			header("Strict-Transport-Security: max-age=63072000; includeSubdomains; preload");
		}

		//disable cache
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");

		if (!isset($kmcngParams["kmcng_version"]))
		{
			KalturaLog::warning("kmcng_version doesn't exist in configuration.");
			return sfView::ERROR;
		}

		$kmcngVersion = $kmcngParams["kmcng_version"];
		$baseDir = kConf::get("BASE_DIR", 'system');
		$basePath = $baseDir . "/apps/kmcng/$kmcngVersion/";
		$deployUrl = "/apps/kmcng/$kmcngVersion/";

		$path = $basePath . "index.html";
		$content = file_get_contents($path);
		if ($content === false)
		{
			KalturaLog::warning("Couldn't locate kmcng path: $path");
			return sfView::ERROR;
		}

		$config = $this->initConfig($deployUrl, $kmcngParams);
		$config = json_encode($config);
		$config = str_replace("\\/", '/', $config);

		$content = str_replace("<base href=\"/\">", "<base href=\"/index.php/kmcng/\">", $content);
		$content = preg_replace("/src=\"(?!(http:)|(https:)|\/)/i", "src=\"{$deployUrl}", $content);
		$content = preg_replace("/href=\"(?!(http:)|(https:)|\/)/i", "href=\"{$deployUrl}", $content);

		$content = str_replace("var kmcConfig = null", "var kmcConfig = " . $config, $content);
		echo $content;
	}

	private function initConfig($deployUrl, $kmcngParams)
	{
		$this->liveAUiConf = uiConfPeer::getUiconfByTagAndVersion(self::LIVE_ANALYTICS_UICONF_TAG, kConf::get("liveanalytics_version"));
		$this->contentUiconfsLivea = isset($this->liveAUiConf) ? array_values($this->liveAUiConf) : null;
		$this->contentUiconfLivea = (is_array($this->contentUiconfsLivea) && reset($this->contentUiconfsLivea)) ? reset($this->contentUiconfsLivea) : null;

		$this->previewUIConf = uiConfPeer::getUiconfByTagAndVersion('KMCng', $kmcngParams["kmcng_version"]);
		$this->contentUiconfsPreview = isset($this->previewUIConf) ? array_values($this->previewUIConf) : null;
		$this->contentUiconfPreview = (is_array($this->contentUiconfsPreview) && reset($this->contentUiconfsPreview)) ? reset($this->contentUiconfsPreview) : null;

		$secureCDNServerUri = "https://" . kConf::get("cdn_api_host_https");
		if (isset($kmcngParams["kmcng_debug_mode"]))
			$secureCDNServerUri = "http://" . kConf::get("cdn_api_host");

		$serverAPIUri = kConf::get("www_host");
		if (isset($kmcngParams["kmcng_custom_uri"]))
			$serverAPIUri = $kmcngParams["kmcng_custom_uri"];

		$studio = null;
		if (kConf::hasParam("studio_version") && kConf::hasParam("html5_version"))
		{
			$studio = array(
				"uri" => '/apps/studio/' . kConf::get("studio_version") . "/index.html",
				"html5_version" => kConf::get("html5_version"),
				"html5lib" => $secureCDNServerUri . "/html5/html5lib/" . kConf::get("html5_version") . "/mwEmbedLoader.php"
			);
		}

		$studioV3 = null;
		if (kConf::hasParam("studio_v3_version") && kConf::hasParam("html5_version"))
		{
			$studioV3 = array(
				"uri" => '/apps/studioV3/' . kConf::get("studio_v3_version") . "/index.html",
				"html5_version" => kConf::get("html5_version"),
				"html5lib" => $secureCDNServerUri . "/html5/html5lib/" . kConf::get("html5_version") . "/mwEmbedLoader.php"
			);
		}

		$liveAnalytics = null;
		if (kConf::hasParam("liveanalytics_version") && isset($this->contentUiconfLivea) && kConf::hasParam("map_zoom_levels") && kConf::hasParam("cdn_static_hosts"))
		{
			$liveAnalytics = array(
				"uri" => '/apps/liveanalytics/' . kConf::get("liveanalytics_version") . "/index.html",
				"uiConfId" => $this->contentUiconfLivea->getId(),
				"map_urls" => array_map(function ($s)
                {
                    return "$s/content/static/maps/v1";
                }, kConf::get("cdn_static_hosts")),
                "map_zoom_levels" => kConf::get("map_zoom_levels")
			);
		}

		$liveDashboard = null;
		if (kConf::hasParam("live_dashboard_version"))
		{
			$liveDashboard = array(
				"uri" => '/apps/liveDashboard/' . kConf::get("live_dashboard_version") . "/index.html"
			);
		}

		$editor = null;
		if ($kmcngParams["kmcng_kea_version"])
		{
			$editor = array(
				"uri" => '/apps/kea/' . $kmcngParams["kmcng_kea_version"] . "/index.html"
			);
		}

		$usageDashboard = null;
		if (kConf::get("usagedashboard_version"))
		{
			$usageDashboard = array(
				"uri" => '/apps/usage-dashboard/' . kConf::get("usagedashboard_version") . "/index.html"
			);
		}

		$config = array(
			'kalturaServer' => array(
				'uri' => $serverAPIUri,
				'deployUrl' => $deployUrl,
				'previewUIConf' => $this->contentUiconfPreview->getId(),
				),
			'cdnServers' => array(
				'serverUri' => "http://" . kConf::get("cdn_api_host"),
				'securedServerUri' => $secureCDNServerUri
			),
			"externalApps" => array(
				"studio" => $studio,
				"studioV3" => $studioV3,
				"liveAnalytics" => $liveAnalytics,
				"liveDashboard" => $liveDashboard,
				"usageDashboard" => $usageDashboard,
				"editor" => $editor
			),
			"externalLinks" => array(
				"previewAndEmbed" => $kmcngParams['previewAndEmbed'],
				"kaltura" => $kmcngParams['kaltura'],
				"entitlements" => $kmcngParams['entitlements'],
				"uploads" => $kmcngParams['uploads'],
				"live" => $kmcngParams['live']
			)
		);

		return $config;
	}
}
