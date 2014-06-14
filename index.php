<?php
	
	define("AF_API_KEY", ""); // API key goes ere
	define("AF_ID", "");// AF id goes here
	
	define("DATE_FORMAT_STRING", "l jS F Y \@ g:i a");
	
	if (!file_exists("build.config.php")) { // Generate config
		
		if (!file_exists("./repositories")) {
			mkdir("./repositories");
		}
		
		if (!file_exists("./builds")) {
			mkdir("./builds");
		}
		
		$buildDir = realpath("./builds") . DIRECTORY_SEPARATOR;
		
		file_put_contents("build.config.php", "<?php
			
			if (!defined('WEBHOOK')) {
				die(\"You are not allowed to see the webhook config\");
			}
			
			class BuildConfig {
				
				public static \$builds = \"{$buildDir}\"; // Path to put all of your built files
				
			}
		?>");
	}
	
	require_once("build.config.php");
	
	define("BUILD_DIR", BuildConfig::$builds);
	
	include_once "includes/rain.tpl.class.php";
	raintpl::$tpl_dir = "includes/template/";
	raintpl::$cache_dir = "includes/tmp/";
	
	$tpl = new raintpl(); //include Rain TPL
	$page = makePage();
	$tpl->assign($page);
	
	$tpl->draw("index"); // draw the template
	
	function makePage() {
		$builds = array();
		$changelogs = array();
		
		$directories = findSubDirectories(BUILD_DIR);
		
		foreach ($directories as $folder) {
			
			$zipFile = findZip($folder);
			$zipName = basename($zipFile);
			$changelog = findChangelog($folder);
			
			$folderName = basename($folder);
			
			$changelogs["Version " . $folderName] = trim(preg_replace('/\s\s+/', '</br>', file_get_contents($changelog)));
			
			$buildWebPath = BUILD_DIR . $folderName . "/";
			
			$builds[] = getDownloadURLs($buildWebPath, $zipName, $folder, filemtime($folder));
			
		}
		
		return array(
			"latest" => $builds[0]["adfly"],
			"builds" => $builds,
			"changelogs" => $changelogs
		);
		
	}
	
	function findZip($folder) {
		$subZips = glob($folder . "/*.zip");
		return $subZips[0];
	}
	
	function findChangelog($folder) {
		return $folder . "/changelog.txt"; 
	}
	
	function getDownloadURLs($dir, $file, $folderPath, $date) {
		
		$plainURL = "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["PHP_SELF"]) . "/" . $dir . $file; // Get the normal URL to the file
		
		$linkFile = $folderPath . "/link.txt";
		if (file_exists($linkFile)) {
			$link = file_get_contents($linkFile);
		} else {
			$link = getAdfly($plainURL); // Get the adfly link to the file
			file_put_contents($linkFile, $link);
		}
		$fileName = pathinfo($file, PATHINFO_FILENAME); // Get the name of the file.
		
		return array(
			"direct" => $plainURL,
			"adfly" => $link,
			"build" => basename($folderPath),
			"creation" => date(DATE_FORMAT_STRING, $date)
		);
	}
	
	function findSubDirectories($dir) {
		$directories = glob($dir . "/*", GLOB_ONLYDIR);
		usort($directories, "sortDirs");
		return $directories;
	}
	
	function sortDirs($a, $b) {
		return filemtime($b) - filemtime($a);
	}
	
	function getAdfly($url) {
		return file_get_contents("http://api.adf.ly/api.php?key=" . AF_API_KEY . "&uid=" . AF_ID . "&domain=adf.ly&url=" . $url);
	}
?>