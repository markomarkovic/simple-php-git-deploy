<?php
/**
 * Simple PHP Git deploy script
 *
 * Automatically deploy the code using PHP and Git.
 *
 * @version 1.2.2
 * @link    https://github.com/markomarkovic/simple-php-git-deploy/
 */

// =========================================[ Configuration start ]===

class DeploySettings{
	// let set the settings
	function __construct() {
		$this->getSettings();
	}

	/**
	 * Protect the script from unauthorized access by using a secret access token.
	 * If it's not present in the access URL as a GET variable named `sat`
	 * e.g. deploy.php?sat=Bett...s the script is not going to deploy.
	 *
	 * @var string
	 */
	public $access_token      = 'BetterChangeMeNowOrSufferTheConsequences';

	/**
	 * The address of the remote Git repository that contains the code that's being
	 * deployed.
	 * If the repository is private, you'll need to use the SSH address.
	 *
	 * @var string
	 */
	public $remote_repository = 'https://github.com/markomarkovic/simple-php-git-deploy.git';

	/**
	 * The branch that's being deployed.
	 * Must be present in the remote repository.
	 *
	 * @var string
	 */
	public $branch = 'master';

	/**
	 * The location that the code is going to be deployed to.
	 * Don't forget the trailing slash!
	 *
	 * @var string Full path including the trailing slash
	 */
	public $target_dir = '/tmp/simple-php-git-deploy/';

	/**
	 * Whether to delete the files that are not in the repository but are on the
	 * local (server) machine.
	 *
	 * !!! WARNING !!! This can lead to a serious loss of data if you're not
	 * careful. All files that are not in the repository are going to be deleted,
	 * except the ones defined in EXCLUDE section.
	 * BE CAREFUL!
	 *
	 * @var boolean
	 */
	public $delete_files = false;

	/**
	 * Delete the files by using the hook post data
	 *
	 * !! This will not work when manually triggered !!
	 *
	 * @var boolean
	 */
	public $delete_branch_files = true;

	/**
	 * The directories and files that are to be excluded when updating the code.
	 * Normally, these are the directories containing files that are not part of
	 * code base, for example user uploads or server-specific configuration files.
	 * Use rsync exclude pattern syntax for each element.
	 *
	 * @var serialized array of strings
	 */
	public $exclude = array('.git', 'webroot/uploads', 'app/config/database.php');

    /**
	 * Temporary directory we'll use to stage the code before the update. If it
	 * already exists, script assumes that it contains an already cloned copy of the
	 * repository with the correct remote origin and only fetches changes instead of
	 * cloning the entire thing.
	 *
	 * Will be set in getSettings()
	 * @var string Full path including the trailing slash
	 */
	public $tmp_dir = '';

	/**
	 * Whether to remove the TMP_DIR after the deployment.
	 * It's useful NOT to clean up in order to only fetch changes on the next
	 * deployment.
	 */
	public $clean_up = true;

	/**
	 * Output the version of the deployed code.
	 *
	 * will be filled based on tmp_dir in getSettings()
	 * @var string Full path to the file name
	 */
	public $version_file = '';

	/**
	 * Time limit for each command.
	 *
	 * @var int Time in seconds
	 */
	public $time_limit = 30;

	/**
	 * OPTIONAL
	 * Backup the TARGET_DIR into BACKUP_DIR before deployment.
	 *
	 * @var string Full backup directory path e.g. '/tmp/'
	 */
	public $backup_dir = false;

	/**
	 * OPTIONAL
	 * Whether to invoke grunt after the repository is cloned or changes are
	 * fetched. Grunt needs to be available on the server machine
	 *
	 * @var boolean Whether to use grunt or not
	 * @link http://gruntjs.com/
	 */
	public $run_grunt = false;

	/**
	 * OPTIONAL
	 * Whether to invoke composer after the repository is cloned or changes are
	 * fetched. Composer needs to be available on the server machine, installed
	 * globaly (as `composer`). See http://getcomposer.org/doc/00-intro.md#globally
	 *
	 * @var boolean Whether to use composer or not
	 * @link http://getcomposer.org/
	 */
	public $use_composer = false;

	/**
	 * OPTIONAL
	 * The options that the composer is going to use.
	 *
	 * @var string Composer options
	 * @link http://getcomposer.org/doc/03-cli.md#install
	 */
	public $composer_options = '--no-dev';

	function getSettings(){
	    // get settings for from the xml file
	    if(!file_exists('settings.xml')) {
	        die('<h2>No Settings file found!</h2>');
	    }

	    // parse the settings using the simple xml library
	    if(!$settings = simplexml_load_file("settings.xml")){
	        die("Could not parse settings file please check the XML format");
	    }

	    // overwrite the default settings based on the xml settings
	    foreach ($settings as $setting => $value) {
	    	// if we need to cast the string type to something else
	    	if($attributes = $value->attributes()) {
	    		if(isset($attributes['typecast'])) {
	    			if($attributes['typecast'] == 'bool' || $attributes['typecast'] == 'boolean') {
		    			$value = ($value->__toString() == 'true' || $value->__toString() == 1);
	    			} else {
		    			settype($value, $attributes['typecast']);

		    			// if it is an array remove the attributes from the array
		    			if($attributes['typecast'] == 'array') {
		    				unset($value['@attributes']);
		    			}
	    			}
	    		}
	    	}

    		$this->$setting = $value;
	    }

	    // set the tmp dir
		$this->tmp_dir      = '/tmp/spgd-'.md5($this->remote_repository).'/';
		$this->version_file = $this->tmp_dir.'VERSION.txt';
	}
}

// get the settings
$settings = new DeploySettings();

// ===========================================[ Configuration end ]===

// If there's authorization error, set the correct HTTP header.
if (!isset($_GET['sat']) || $_GET['sat'] != $settings->access_token || $settings->access_token == 'BetterChangeMeNowOrSufferTheConsequences') {
	header('HTTP/1.0 403 Forbidden');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="robots" content="noindex">
	<title>Simple PHP Git deploy script</title>
	<style>
body { padding: 0 1em; background: #222; color: #fff; }
h2, .error { color: #c33; }
.prompt { color: #6be234; }
.command { color: #729fcf; }
.output { color: #999; }
	</style>
</head>
<body>
<?php
if (!isset($_GET['sat']) || $_GET['sat'] != $settings->access_token) {
	die('<h2>ACCESS DENIED!</h2>');
}
if ($settings->access_token === 'BetterChangeMeNowOrSufferTheConsequences') {
	die("<h2>You're suffering the consequences!<br>Change the access_token from it's default value!</h2>");
}

// if there is post data from git
if(isset($HTTP_RAW_POST_DATA)) {
	$json_post_data = json_decode($HTTP_RAW_POST_DATA);
} else {
	$json_post_data = false;
}

// if this is a git call get which branch we hooked
if($json_post_data) {
	if($branch = array_pop(preg_split("/[\/]+/", $json_post_data->ref))) {
		if($branch != $settings->branch) {
			die('<h2>We won\'t continue because this is not our branch!</h2>');
		}
	}
}
?>
<pre>

Checking the environment ...

Running as <b><?php echo trim(shell_exec('whoami')); ?></b>.

<?php
// Check if the required programs are available
$requiredBinaries = array('git', 'rsync');
if($settings->backup_dir) {
	$requiredBinaries[] = 'tar';
}
if ($settings->use_composer) {
	$requiredBinaries[] = 'composer --no-ansi';
}
foreach ($requiredBinaries as $command) {
	$path = trim(shell_exec('which '.$command));
	if ($path == '') {
		die(sprintf('<div class="error"><b>%s</b> not available. It needs to be installed on the server for this script to work.</div>', $command));
	} else {
		$version = explode("\n", shell_exec($command.' --version'));
		printf('<b>%s</b> : %s'."\n"
			, $path
			, $version[0]
		);
	}
}
?>

Environment OK.

Deploying <?php echo $settings->remote_repository; ?> <?php echo $settings->branch."\n"; ?>
to        <?php echo $settings->target_dir; ?> ...

<?php
// The commands
$commands = array();

// ========================================[ Pre-Deployment steps ]===

if (!is_dir($settings->tmp_dir)) {
	// Clone the repository into the TMP_DIR
	$commands[] = sprintf(
		'git clone --depth=1 --branch %s %s %s'
		, $settings->branch
		, $settings->remote_repository
		, $settings->tmp_dir
	);
} else {
	// TMP_DIR exists and hopefully already contains the correct remote origin
	// so we'll fetch the changes and reset the contents.
	$commands[] = sprintf(
		'git --git-dir="%s.git" --work-tree="%s" fetch origin %s'
		, $settings->tmp_dir
		, $settings->tmp_dir
		, $settings->branch
	);
	$commands[] = sprintf(
		'git --git-dir="%s.git" --work-tree="%s" reset --hard FETCH_HEAD'
		, $settings->tmp_dir
		, $settings->tmp_dir
	);
}

// Update the submodules
$commands[] = sprintf(
	'git submodule update --init --recursive'
);

// Describe the deployed version
if ($settings->version_file) {
	$commands[] = sprintf(
		'git --git-dir="%s.git" --work-tree="%s" describe --always > %s'
		, $settings->tmp_dir
		, $settings->tmp_dir
		, $settings->version_file
	);
}

// Backup the TARGET_DIR
if ($settings->backup_dir && is_dir($settings->backup_dir)) {
	$commands[] = sprintf(
		'tar czf %s/%s-%s-%s.tar.gz %s*'
		, $settings->backup_dir
		, basename($settings->target_dir)
		, md5($settings->target_dir)
		, date('YmdHis')
		, $settings->target_dir // We're backing up this directory into BACKUP_DIR
	);
}

// Invoke composer
if ($settings->use_composer) {
	$commands[] = sprintf(
		'composer --no-ansi --no-interaction --no-progress --working-dir=%s install %s'
		, $settings->tmp_dir
		, ($settings->composer_options) ? $settings->composer_options : ''
	);
}

// ==================================================[ Deployment ]===

// Compile exclude parameters
$exclude = '';
foreach ($settings->exclude as $exc) {
	$exclude .= ' --exclude='.$exc;
}
// Deployment command
$commands[] = sprintf(
	'rsync -rltgoDzv %s %s %s %s'
	, $settings->tmp_dir
	, $settings->target_dir
	, ($settings->delete_files) ? '--delete-after' : ''
	, $exclude
);

// =======================================[ Post-Deployment steps ]===

// Remove the TMP_DIR (depends on CLEAN_UP)
if ($settings->clean_up) {
	$commands['cleanup'] = sprintf(
		'rm -rf %s'
		, $settings->tmp_dir
	);
}

if ($settings->delete_branch_files) {
	if($json_post_data) {
		$delete_files = array();
		// get the commit belonging to this hook
		foreach ($json_post_data->commits as $commit) {
			if($commit->removed) {
				$delete_files = array_merge($commit->removed, $delete_files);
			}
			// diff the files which are added again
			if($commit->added) {
				$delete_files = array_diff($delete_files, $commit->added);
			}
		}

		// also add the head commit
		if($commit = $json_post_data->head_commit) {
			if($commit->removed) {
				$delete_files = array_merge($commit->removed, $delete_files);
			}
			// diff the files which are added again
			if($commit->added) {
				$delete_files = array_diff($delete_files, $commit->added);
			}
		}

		// get the files unique so only delete once
		$delete_files = array_unique($delete_files);
		// remove excluded
		$delete_files = array_diff($delete_files, $settings->exclude);

		// add the remove to the commands
		foreach ($delete_files as $key => $file) {
			$commands['delete_branch_file_'.$key] = sprintf(
				'rm -rf %s'
				, $settings->target_dir.$file
			);
		}
	}
}

// =======================================[ Run grunt ]===

if($settings->run_grunt) {
	$commands['grunt'] = sprintf('cd '.$settings->target_dir.'; source /var/www/.profile; grunt sass; grunt uglify;');
}


// =======================================[ Run the command steps ]===

foreach ($commands as $command) {
	set_time_limit($settings->time_limit); // Reset the time limit for each command
	if (file_exists($settings->tmp_dir) && is_dir($settings->tmp_dir)) {
		chdir($settings->tmp_dir); // Ensure that we're in the right directory
	}
	$tmp = array();
	exec($command.' 2>&1', $tmp, $return_code); // Execute the command
	// Output the result
	printf('
<span class="prompt">$</span> <span class="command">%s</span>
<div class="output">%s</div>
'
		, htmlentities(trim($command))
		, htmlentities(trim(implode("\n", $tmp)))
	);
	flush(); // Try to output everything as it happens

	// Error handling and cleanup
	if ($return_code !== 0) {
		printf('
<div class="error">
Error encountered!
Stopping the script to prevent possible data loss.
CHECK THE DATA IN YOUR TARGET DIR!
</div>
'
		);
		if ($settings->clean_up) {
			$tmp = shell_exec($commands['cleanup']);
			printf('


Cleaning up temporary files ...

<span class="prompt">$</span> <span class="command">%s</span>
<div class="output">%s</div>
'
				, htmlentities(trim($commands['cleanup']))
				, htmlentities(trim($tmp))
			);
		}
		error_log(sprintf(
			'Deployment error! %s'
			, __FILE__
		));
		break;
	}
}
?>

Done.
</pre>
</body>
</html>
