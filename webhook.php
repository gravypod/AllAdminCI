<?php
	
	define("WEBHOOK", true);
	
	if (!isset($_POST["payload"])) {
		die("Not a webhooks payload");
	}
	
	if (!file_exists("webhook.config.php")) { // Generate config
		
		if (!file_exists("./repositories")) {
			mkdir("./repositories");
		}
		
		$gitDir = realpath("./repositories") . DIRECTORY_SEPARATOR; // Generate where we put our repositories
		
		file_put_contents("webhook.config.php", "<?php
			
			if (!defined('WEBHOOK')) {
				die(\"You are not allowed to see the webhook config\");
			}
			
			class WebhookConfig {
				
				public static \$useAuth = true; // Use HTTP authentication to authenticate with github. URL is http(s)://(username):(password)@example.com/path.php
				public static \$username = \"user\"; // Username for the webhook
				public static \$password = \"pass\"; // Password for the webhook
				public static \$gitDirectory = \"{$gitDir}\"; // Directory to clone repos to.
				public static \$gitCommand = \"git\"; // Command to use when 
			}
		?>");
	}
	
	require_once("webhook.config.php");
	require_once("build.config.php");
	
	if (WebhookConfig::$useAuth) { // Check to see if we want to use HTTP auth
		
		if (!isset($_SERVER["PHP_AUTH_USER"])) { // Has the user sent login data?
			
			die("Username and password not set for HTTP Auth.");
			
		} else { // Check login data
			
			$inputUser = $_SERVER["PHP_AUTH_USER"];
			$inputPass = $_SERVER["PHP_AUTH_PW"];
			
			if (WebhookConfig::$username != $inputUser || WebhookConfig::$password != $inputPass) {
			
				die("Bat login!");
				
			}
			
		}
		
	}
	
	$payload = json_decode($_POST["payload"], true);
	
	if (!isset($payload["pusher"])) {
		
		die("Not a git-push event.");
		
	}
	
	set_time_limit(0);
	
	$repository = $payload["repository"];
	$repoName = $repository["name"];
	$repoInfoPath = WebhookConfig::$gitDirectory . $repoName . DIRECTORY_SEPARATOR;
	$buildPath = $repoInfoPath . "build" . DIRECTORY_SEPARATOR;
	$repoPath = $repoInfoPath . "git" . DIRECTORY_SEPARATOR;
	$changeLog = $repoInfoPath . "changelog.txt";
	
	$repoURI = $repository["url"] . ".git";
	$branch = $repository["master_branch"];
	
	cloneRepo(WebhookConfig::$gitCommand, $repoPath, $repoURI);
	
	file_put_contents($changeLog, $repository["description"], FILE_APPEND);
	
	$buildScript = $repoPath . "build.php";
	
	if (!file_exists($buildScript)) {
		
		die("No build scripts");
		
	}
	
	define("REPO_NAME", $repoName);
	define("REPO_PATH", $repoPath);
	define("REPO_BRANCH", $branch);
	define("CHANGELOG", $changeLog);
	define("BUILD_PATH", $buildPath);
	
	require_once($buildScript);
	
	
	if (!needsBuild()) {
		die("No new build needed");
	}
	
	$version = getVersion();
	
	$releaseBuildDir = BuildConfig::$builds; // TODO: Configure
	
	if (file_exists($releaseBuildDir)) {
		die("No build needed.");
	}
	
	build();
	
	$zipName = getZipName();
	
	rename($changeLog, $releaseBuildDir . "changelog.txt");
	zip($buildPath, $releaseBuildDir . $zipName);
	
	function cloneRepo($destination, $uri) {
		cloneRepo(WebhookConfig::$gitCommand, $destination, $uri);
	}
	
	function cloneRepo($command, $destination, $uri) {
		
		if (!file_exists($repoPath)) { // FILE_APPEND
			
			mkdir($repoInfoPath);
			mkdir($repoPath);
			mkdir($buildPath);
			
			shell_exec("cd {$destination} && {$command} reset --hard HEAD && {$command} clone {$uri}");
			
		} else {
			
			shell_exec("cd {$destination} && {$command} reset --hard HEAD && {$command} pull");
			
		}
		
	}
	
	function zip($source, $destination) {
		
		if (!extension_loaded('zip') || !file_exists($source)) {
			
			return false;
			
		}
	
		$zip = new ZipArchive();
		
		if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
			
			return false;
			
		}
	
		$source = str_replace('\\', '/', realpath($source));
	
		if (is_dir($source)) {
			
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
			
			foreach ($files as $file) {
				
				$file = str_replace('\\', '/', $file);
				
				// Ignore "." and ".." folders
				if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..'))) {
					
					continue;
					
				}
				
				$file = realpath($file);
				
				if (is_dir($file)) {
					
					$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
					
				} else if (is_file($file)) {
					
					$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
					
				}
				
			}
			
		} else if (is_file($source)) {
			
			$zip->addFromString(basename($source), file_get_contents($source));
			
		}
		
		return $zip->close();
	}

?>