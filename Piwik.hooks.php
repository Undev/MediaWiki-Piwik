<?php

class PiwikHooks {

	/**
	 * Initialize the Piwik Hook
	 *
	 * @param string $skin
	 * @param string $text
	 * @return bool
	 */
	public static function PiwikSetup ($skin, &$text = '')
	{
		$text .= PiwikHooks::AddPiwik( $skin->getTitle() );
		return true;
	}

	/**
	 * Add piwik script
	 * @param string $title
	 * @return string
	 */
	public static function AddPiwik ($title) {

		global $wgPiwikIDSite, $wgPiwikURL, $wgPiwikIgnoreSysops,
		       $wgPiwikIgnoreBots, $wgUser, $wgScriptPath,
		       $wgPiwikCustomJS, $wgPiwikActionName, $wgPiwikUsePageTitle,
		       $wgPiwikDisableCookies;

		// Is piwik disabled for bots?
		if ( $wgUser->isAllowed( 'bot' ) && $wgPiwikIgnoreBots ) {
			return "<!-- Piwik extension is disabled for bots -->";
		}

		// Ignore Wiki System Operators
		if ( $wgUser->isAllowed( 'protect' ) && $wgPiwikIgnoreSysops ) {
			return "<!-- Piwik tracking is disabled for users with 'protect' rights (i.e., sysops) -->";
		}

		// Missing configuration parameters 
		if ( empty( $wgPiwikIDSite ) || empty( $wgPiwikURL ) ) {
			return "<!-- You need to set the settings for Piwik -->";
		}

		if ( $wgPiwikUsePageTitle ) {
			$wgPiwikPageTitle = $title->getPrefixedText();

			$wgPiwikFinalActionName = $wgPiwikActionName;
			$wgPiwikFinalActionName .= $wgPiwikPageTitle;
		} else {
			$wgPiwikFinalActionName = $wgPiwikActionName;
		}

		// Check if disablecookies flag
		if ($wgPiwikDisableCookies) {
			$disableCookiesStr = PHP_EOL . '  _paq.push(["disableCookies"]);';
		} else $disableCookiesStr = null;

		// Check if we have custom JS
		if (!empty($wgPiwikCustomJS)) {

			// Check if array is given
			// If yes we have multiple lines/variables to declare
			if (is_array($wgPiwikCustomJS)) {

				// Make empty string with a new line
				$customJs = PHP_EOL;

				// Store the lines in the $customJs line
				foreach ($wgPiwikCustomJS as $customJsLine) {
					$customJs .= $customJsLine;
				}

				// CustomJs is string
			} else $customJs = PHP_EOL . $wgPiwikCustomJS;

			// Contents are empty
		} else $customJs = null;

		// Prevent XSS
		$wgPiwikFinalActionName = Xml::encodeJsVar( $wgPiwikFinalActionName );

		$categories = self::getCategories($title);
		$scriptCategories = '';
		if (!empty($categories)) {
			$i = 0;
			foreach(self::getCategories($title) as $category) {
				if (++$i > 5) break;
				$scriptCategories .= <<<PIWIK
  _paq.push(["setCustomVariable", {$i}, "Category", "{$category}", "page"]);

PIWIK;
			}
		}

		// Piwik script
		$script = <<<PIWIK
<!-- Piwik -->
<script type="text/javascript">
  var _paq = _paq || [];{$disableCookiesStr}{$customJs}
  _paq.push(["setCustomVariable", 1, "User", "{$wgUser}", "visit"]);
{$scriptCategories}
  _paq.push(["trackPageView"]);
  _paq.push(["enableLinkTracking"]);

  (function() {
    var u=(("https:" == document.location.protocol) ? "https" : "http") + "://{$wgPiwikURL}/";
    _paq.push(["setTrackerUrl", u+"piwik.php"]);
    _paq.push(["setSiteId", "{$wgPiwikIDSite}"]);
    var d=document, g=d.createElement("script"), s=d.getElementsByTagName("script")[0]; g.type="text/javascript";
    g.defer=true; g.async=true; g.src=u+"piwik.js"; s.parentNode.insertBefore(g,s);
  })();
</script>
<!-- End Piwik Code -->

<!-- Piwik Image Tracker -->
<noscript><img src="http://{$wgPiwikURL}/piwik.php?idsite={$wgPiwikIDSite}&amp;rec=1" style="border:0" alt="" /></noscript>
<!-- End Piwik -->
PIWIK;

		return $script;

	}

	private static function getCategories(Title $title)
	{
		$categoriesTree = self::array_values_recursive($title->getParentCategoryTree());
		$categoriesTree = array_unique($categoriesTree);
		$categories = array();
		foreach ($categoriesTree as $category) {
			if (strpos($category, ':')) {
				$category = explode(':', $category);
				$categories[] = $category[1];
			}
		}

		return $categories;
	}

	private static function array_values_recursive($array)
	{
		$arrayKeys = array();

		foreach ($array as $key => $value) {
			$arrayKeys[] = $key;
			if (!empty($value)) {
				$arrayKeys = array_merge($arrayKeys, self::array_values_recursive($value));
			}
		}

		return $arrayKeys;
	}
}