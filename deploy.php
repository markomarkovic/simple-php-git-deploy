<?php
/**
 * Simple PHP GIT deploy script
 *
 * Automatically deploy the code using php and git.
 *
 * @version 1.0.6
 * @link    https://github.com/markomarkovic/simple-php-git-deploy/
 */

// =========================================[ Configuration start ]===

/**
 * Protect the script from unauthorized access by using a secret string.
 * If it's not present in the access URL as a GET variable named `sat`
 * e.g. deploy.php?sat=Bett...s the script is going to fail.
 *
 * @var string
 */
define('SECRET_ACCESS_TOKEN', 'BetterChangeMeNowOrSufferTheConsequences');

/**
 * The address of the remote GIT repository that contains the code we're
 * updating.
 *
 * @var string
 */
define('REMOTE_REPOSITORY', 'https://github.com/markomarkovic/simple-php-git-deploy.git');

/**
 * Which branch are we going to use for deployment.
 *
 * @var string
 */
define('BRANCH', 'master');

/**
 * This is where the code resides on the local machine.
 * Don't forget the trailing slash!
 *
 * @var string Full path including the trailing slash
 */
define('TARGET_DIR', '/tmp/simple-php-git-deploy/');

/**
 * Weather to delete the files that are not in the repository but are on the
 * local machine.
 *
 * !!! WARNING !!! This can lead to a serious loss of data if you're not
 * careful. All files that are not in the repository are going to be deleted,
 * except the ones defined in EXCLUDE section! BE CAREFUL!
 *
 * @var boolean
 */
define('DELETE_FILES', false);

/**
 * The directories and files that are to be excluded when updating the code.
 * Normally, these are the directories containing files that are not part of
 * code base, for example user uploads or server-specific configuration files.
 * Use rsync exclude pattern syntax for each element.
 *
 * @var serialized array of strings
 */
define('EXCLUDE', serialize(array(
	'.git',
	'webroot/uploads',
	'app/config/database.php',
)));

/**
 * Temporary directory we'll use to stage the code before the update.
 *
 * @var string Full path including the trailing slash
 */
define('TMP_DIR', '/tmp/spgd-'.md5(REMOTE_REPOSITORY).'-'.time().'/');

/**
 * Output the version of the deployed code.
 *
 * @var string Full path to the file name
 */
define('VERSION_FILE', TMP_DIR.'DEPLOYED_VERSION.txt');

/**
 * Time limit for each command.
 *
 * @var int Time in seconds
 */
define('TIME_LIMIT', 30);

/**
 * OPTIONAL
 * Backup the TARGET_DIR into BACKUP_DIR before deployment
 *
 * @var string Full backup directory path e.g. '/tmp/'
 */
define('BACKUP_DIR', false);

// ===========================================[ Configuration end ]===

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Simple PHP GIT deploy script</title>
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
if (!isset($_GET['sat']) || $_GET['sat'] !== SECRET_ACCESS_TOKEN) {
	die('<h2>ACCESS DENIED!</h2>');
}
if (SECRET_ACCESS_TOKEN === 'BetterChangeMeNowOrSufferTheConsequences') {
	die("<h2>You're suffering the consequences!<br>Change the SECRET_ACCESS_TOKEN from it's default value!</h2>");
}
?>
<pre>

Checking the environment ...

Running as <b><?php echo trim(shell_exec('whoami')); ?></b>.

<?php
// Check if the needed programs are available
$binaries = array();
foreach (array('git', 'rsync', 'tar') as $command) {
	$path = trim(shell_exec('which '.$command));
	if ($path == '') {
		die(sprintf('<div class="error"><b>%s</b> not available. It need to be installed on the server for this script to work.</div>', $command));
	} else {
		$binaries[$command] = $path;
		$version = explode("\n", shell_exec($path.' --version'));
		printf('<b>%s</b> : %s'."\n"
			, $path
			, $version[0]
		);
	}
}
?>

Environment OK.

Deploying <?php echo REMOTE_REPOSITORY; ?> <?php echo BRANCH."\n"; ?>
to        <?php echo TARGET_DIR; ?> ...

<?php
// The commands
$commands = array();

// ========================================[ Pre-Deployment steps ]===

// Clone the repository into the TMP_DIR
$commands[] = sprintf(
	'%s clone --depth=1 %s %s'
	, $binaries['git']
	, REMOTE_REPOSITORY
	, TMP_DIR
);

// Checkout the BRANCH
$commands[] = sprintf(
	'%s --git-dir="%s.git" --work-tree="%s" checkout %s'
	, $binaries['git']
	, TMP_DIR
	, TMP_DIR
	, BRANCH
);

// Update the submodules
$commands[] = sprintf(
	'%s submodule update --init --recursive'
	, $binaries['git']
);

// Describe the deployed version
if (defined('VERSION_FILE') && VERSION_FILE !== '') {
	$commands[] = sprintf(
		'%s --git-dir="%s.git" --work-tree="%s" describe --always > %s'
		, $binaries['git']
		, TMP_DIR
		, TMP_DIR
		, VERSION_FILE
	);
}

// Backup the TARGET_DIR
if (defined('BACKUP_DIR') && BACKUP_DIR !== false && is_dir(BACKUP_DIR)) {
	$commands[] = sprintf(
		'%s czf %s/%s-%s-%s.tar.gz %s*'
		, $binaries['tar']
		, BACKUP_DIR
		, basename(TARGET_DIR)
		, md5(TARGET_DIR)
		, date('YmdHis')
		, TARGET_DIR // We're backing up this directory into BACKUP_DIR
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
	'%s -rltgoDzv %s %s %s %s'
	, $binaries['rsync']
	, TMP_DIR
	, TARGET_DIR
	, (DELETE_FILES) ? '--delete-after' : ''
	, $exclude
);

// =======================================[ Post-Deployment steps ]===

// Remove the TMP_DIR
$commands['cleanup'] = sprintf(
	'rm -rf %s'
	, TMP_DIR
);

// =======================================[ Run the command steps ]===

foreach ($commands as $command) {
	set_time_limit(TIME_LIMIT); // Reset the time limit for each command
	if (file_exists(TMP_DIR) && is_dir(TMP_DIR)) {
		chdir(TMP_DIR); // Ensure that we're in the right directory
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
		$tmp = shell_exec($commands['cleanup']);
		printf('
<div class="error">
Error encountered!
Stopping the script to prevent possible data loss.
CHECK THE DATA IN YOUR TARGET DIR!
</div>


Cleaning up temporary files ...

<span class="prompt">$</span> <span class="command">%s</span>
<div class="output">%s</div>
'
			, htmlentities(trim($commands['cleanup']))
			, htmlentities(trim($tmp))
		);
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
