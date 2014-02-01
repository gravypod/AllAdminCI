<?php
	
	define("AF_API_KEY", ""); // API key goes ere
	define("AF_ID", "");// AF id goes here
	define("BUILD_DIR", "builds/");
	define("DATE_FORMAT_STRING", "l jS F Y \@ g:i a");
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
		
		$directories = findSubDirectories(BUILD_DIR); // Find all build folders
		
		foreach ($directories as $folder) {
			
			$zipFile = findZip($folder); // Find the build file zip
			$zipName = basename($zipFile); // Get its name
			$changelog = findChangelog($folder); // Find the changelog
			
			$folderName = basename($folder); // Find the folder name (AKA the all admin version)
			
			$changelogs["Version " . $folderName] = file_get_contents($changelog); // Add the changelog
			
			$buildWebPath = BUILD_DIR . $folderName . "/"; // Create the path to the build folder
			
			$builds[] = getDownloadURLs($buildWebPath, $zipName, filemtime($folder)); // Get the links needed and push to builds array for rendering 
			
		}
		
		return array( // Create page for rendering
			"latest" => $builds[0]["adfly"], // Latest build found
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
	
	function getDownloadURLs($dir, $file, $date) {
		
		$plainURL = "http://" . $_SERVER["HTTP_HOST"] . dirname($_SERVER["PHP_SELF"]) . "/" . $dir . $file; // Get the normal URL to the file
		
		$adflyURL = getAdfly($plainURL); // Get the adfly link to the file
		
		$fileName = pathinfo($file, PATHINFO_FILENAME); // Get the name of the file.
		
		$versionMetadata = extractVersionInfo($fileName);
		
		return array(
			"direct" => $plainURL,
			"adfly" => $adflyURL,
			"mcver" => $versionMetadata[0],
			"forgever" => $versionMetadata[1],
			"aaver" => $versionMetadata[2],
			"creation" => date(DATE_FORMAT_STRING, $date)
		);
	}
	
	function extractVersionInfo($fileName) {
		$versionString = substr($fileName, strrpos($fileName, "-") + 1);
		return explode("_", $versionString);
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