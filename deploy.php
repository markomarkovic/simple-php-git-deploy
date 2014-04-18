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

/** SITE Name to get the right config file **/
if (isset($argv)) {
	$_GET['site'] = $argv[1];
	$_GET['sat'] = $argv[2];
}

if (isset($_GET['site']) && strlen($_GET['site']) > 0) {
	define('SITE_ENV_NAME',preg_replace('/[^-a-zA-Z0-9_]/', '', $_GET['site']));
} else {
	echo "No Deployment Site and Environment defined\n";
	exit;
}

/**
 * It's preferable to configure the script using `deploy-config.php` file.
 *
 * Rename `deploy-config.example.php` to `deploy-config.php` and edit the
 * configuration options there instead of here. That way, you won't have to edit
 * the configuration again if you download the new version of `deploy.php`.
 */
if (file_exists(SITE_ENV_NAME."-".basename(__FILE__, '.php').'-config.php'))
	require_once './'.SITE_ENV_NAME."-".basename(__FILE__, '.php').'-config.php';

/**
 * Protect the script from unauthorized access by using a secret access token.
 * If it's not present in the access URL as a GET variable named `sat`
 * e.g. deploy.php?sat=Bett...s the script is not going to deploy.
 *
 * @var string
 */
if (!defined('SECRET_ACCESS_TOKEN'))
	define('SECRET_ACCESS_TOKEN', 'BetterChangeMeNowOrSufferTheConsequences');

/**
 * The address of the remote Git repository that contains the code that's being
 * deployed.
 * If the repository is private, you'll need to use the SSH address.
 *
 * @var string
 */
if (!defined('REMOTE_REPOSITORY'))
	define('REMOTE_REPOSITORY', 'https://github.com/markomarkovic/simple-php-git-deploy.git');

/**
 * The branch that's being deployed.
 * Must be present in the remote repository.
 *
 * @var string
 */
if (!defined('BRANCH')) define('BRANCH', 'master');

/**
 * The location that the code is going to be deployed to.
 * Don't forget the trailing slash!
 *
 * @var string Full path including the trailing slash
 */
if (!defined('TARGET_DIR')) define('TARGET_DIR', '/tmp/simple-php-git-deploy/');

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
if (!defined('DELETE_FILES')) define('DELETE_FILES', false);

/**
 * The directories and files that are to be excluded when updating the code.
 * Normally, these are the directories containing files that are not part of
 * code base, for example user uploads or server-specific configuration files.
 * Use rsync exclude pattern syntax for each element.
 *
 * @var serialized array of strings
 */
if (!defined('EXCLUDE')) define('EXCLUDE', serialize(array(
	'.git',
	'webroot/uploads',
	'app/config/database.php',
)));

/**
 * Temporary directory we'll use to stage the code before the update. If it
 * already exists, script assumes that it contains an already cloned copy of the
 * repository with the correct remote origin and only fetches changes instead of
 * cloning the entire thing.
 *
 * @var string Full path including the trailing slash
 */
if (!defined('TMP_DIR')) define('TMP_DIR', '/tmp/spgd-'.md5(REMOTE_REPOSITORY).'/');

/**
 * Whether to remove the TMP_DIR after the deployment.
 * It's useful NOT to clean up in order to only fetch changes on the next
 * deployment.
 */
if (!defined('CLEAN_UP')) define('CLEAN_UP', true);

/**
 * Output the version of the deployed code.
 *
 * @var string Full path to the file name
 */
if (!defined('VERSION_FILE')) define('VERSION_FILE', TMP_DIR.'VERSION.txt');

/**
 * Time limit for each command.
 *
 * @var int Time in seconds
 */
if (!defined('TIME_LIMIT')) define('TIME_LIMIT', 30);

/**
 * OPTIONAL
 * Backup the TARGET_DIR into BACKUP_DIR before deployment.
 *
 * @var string Full backup directory path e.g. '/tmp/'
 */
if (!defined('BACKUP_DIR')) define('BACKUP_DIR', false);

/**
 * OPTIONAL
 * Whether to invoke composer after the repository is cloned or changes are
 * fetched. Composer needs to be available on the server machine, installed
 * globaly (as `composer`). See http://getcomposer.org/doc/00-intro.md#globally
 *
 * @var boolean Whether to use composer or not
 * @link http://getcomposer.org/
 */
if (!defined('USE_COMPOSER')) define('USE_COMPOSER', false);

/**
 * OPTIONAL
 * The options that the composer is going to use.
 *
 * @var string Composer options
 * @link http://getcomposer.org/doc/03-cli.md#install
 */
if (!defined('COMPOSER_OPTIONS')) define('COMPOSER_OPTIONS', '--no-dev');

// ===========================================[ Configuration end ]===

// If there's authorization error, set the correct HTTP header.
if (!isset($_GET['sat']) || $_GET['sat'] !== SECRET_ACCESS_TOKEN || SECRET_ACCESS_TOKEN === 'BetterChangeMeNowOrSufferTheConsequences') {
	header('HTTP/1.0 403 Forbidden');
}

if (!isset($_GET['sat']) || $_GET['sat'] !== SECRET_ACCESS_TOKEN) {
	die('ACCESS DENIED!\n');
}
if (SECRET_ACCESS_TOKEN === 'BetterChangeMeNowOrSufferTheConsequences') {
	die("You're suffering the consequences!\nChange the SECRET_ACCESS_TOKEN from it's default value!\n\n");
}

echo "Checking the environment ... \n\n Running as " . trim(shell_exec('whoami')) . "\n\n";

// Check if the required programs are available
$requiredBinaries = array('git', 'rsync');
if (defined('BACKUP_DIR') && BACKUP_DIR !== false) {
	$requiredBinaries[] = 'tar';
}
if (defined('USE_COMPOSER') && USE_COMPOSER === true) {
	$requiredBinaries[] = 'composer --no-ansi';
}
foreach ($requiredBinaries as $command) {
	$path = trim(shell_exec('which '.$command));
	if ($path == '') {
		die(sprintf('%s not available. It needs to be installed on the server for this script to work.\n', $command));
	} else {
		$version = explode("\n", shell_exec($command.' --version'));
		printf('%s : %s'."\n"
			, $path
			, $version[0]
		);
	}
}

echo "Environment OK.\n\n";
echo "Deploying ", REMOTE_REPOSITORY, BRANCH."\n";
echo "to ", TARGET_DIR, " ... \n\n";

// The commands
$commands = array();

// ========================================[ Pre-Deployment steps ]===

if (!is_dir(TMP_DIR)) {
	// Clone the repository into the TMP_DIR
	$commands[] = sprintf(
		'git clone --depth=1 --branch %s %s %s'
		, BRANCH
		, REMOTE_REPOSITORY
		, TMP_DIR
	);
} else {
	// TMP_DIR exists and hopefully already contains the correct remote origin
	// so we'll fetch the changes and reset the contents.
	$commands[] = sprintf(
		'git --git-dir="%s.git" --work-tree="%s" fetch origin %s'
		, TMP_DIR
		, TMP_DIR
		, BRANCH
	);
	$commands[] = sprintf(
		'git --git-dir="%s.git" --work-tree="%s" reset --hard FETCH_HEAD'
		, TMP_DIR
		, TMP_DIR
	);
}

// Update the submodules
$commands[] = sprintf(
	'git submodule update --init --recursive'
);

// Describe the deployed version
if (defined('VERSION_FILE') && VERSION_FILE !== '') {
	$commands[] = sprintf(
		'git --git-dir="%s.git" --work-tree="%s" describe --always > %s'
		, TMP_DIR
		, TMP_DIR
		, VERSION_FILE
	);
}

// Backup the TARGET_DIR
if (defined('BACKUP_DIR') && BACKUP_DIR !== false && is_dir(BACKUP_DIR)) {
	$commands[] = sprintf(
		'tar czf %s/%s-%s-%s.tar.gz %s*'
		, BACKUP_DIR
		, basename(TARGET_DIR)
		, md5(TARGET_DIR)
		, date('YmdHis')
		, TARGET_DIR // We're backing up this directory into BACKUP_DIR
	);
}

// Invoke composer
if (defined('USE_COMPOSER') && USE_COMPOSER === true) {
	$commands[] = sprintf(
		'composer --no-ansi --no-interaction --no-progress --working-dir=%s install %s'
		, TMP_DIR
		, (defined('COMPOSER_OPTIONS')) ? COMPOSER_OPTIONS : ''
	);
}

// ==================================================[ Deployment ]===

// Compile exclude parameters
$exclude = '';
foreach (unserialize(EXCLUDE) as $exc) {
	$exclude .= ' --exclude='.$exc;
}
// Deployment command
$commands[] = sprintf(
	'rsync -rltgoDzv %s %s %s %s'
	, TMP_DIR
	, TARGET_DIR
	, (DELETE_FILES) ? '--delete-after' : ''
	, $exclude
);

// =======================================[ Post-Deployment steps ]===

// Remove the TMP_DIR (depends on CLEAN_UP)
if (CLEAN_UP) {
	$commands['cleanup'] = sprintf(
		'rm -rf %s'
		, TMP_DIR
	);
}

// =======================================[ Run the command steps ]===

foreach ($commands as $command) {
	set_time_limit(TIME_LIMIT); // Reset the time limit for each command
	if (file_exists(TMP_DIR) && is_dir(TMP_DIR)) {
		chdir(TMP_DIR); // Ensure that we're in the right directory
	}
	$tmp = array();
	exec($command.' 2>&1', $tmp, $return_code); // Execute the command
	// Output the result
	printf("\$ %s \n%s\n"
		, htmlentities(trim($command))
		, htmlentities(trim(implode("\n", $tmp)))
	);
	flush(); // Try to output everything as it happens

	// Error handling and cleanup
	if ($return_code !== 0) {
		printf("Error encountered!\nStopping the script to prevent possible data loss.\nCHECK THE DATA IN YOUR TARGET DIR!\n\n");
		if (CLEAN_UP) {
			$tmp = shell_exec($commands['cleanup']);
			printf("Cleaning up temporary files ...\n\$ %s\n%s\n\n"
				, htmlentities(trim($commands['cleanup']))
				, htmlentities(trim($tmp))
			);
		}
		error_log(sprintf(
			"Deployment error! %s\n\n"
			, __FILE__
		));
		break;
	}
}

echo "Done.\n\n";
?>
