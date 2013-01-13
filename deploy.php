<?php
/**
 * Simple PHP GIT deploy script
 *
 * Used to automatically update the code on the server when triggered
 * by a git hook on a repository.
 */

// Configuration

/**
 * Protect the script from unauthorized access by using a secret string.
 * If it's not present in the access URL as a GET variable named `sat`
 * e.g. deploy.php?sat=Bett...s the script is going to fail.
 */
define('SECRET_ACCESS_TOKEN', 'BetterChangeMeNowOrSufferTheConsequences');

/**
 * The address of the remote GIT repository that contains the code we're
 * updating
 */
define('REMOTE_REPOSITORY', 'https://github.com/markomarkovic/simple-php-git-deploy.git');

/**
 * This is where the code resides on the local machine.
 * Don't forget the trailing slash!
 */
define('TARGET_DIR', '/tmp/simple-php-git-deploy/');

/**
 * Which branch are we going to use for deployment.
 */
define('BRANCH', 'master');

/**
 * Weather to delete the files that are not in the repository but are on the
 * local machine.
 *
 * !!! WARNING !!! This can lead to a serious loss of data if you're not
 * careful. All files that are not in the repository are going to be deleted,
 * except the ones defined in IGNORE_DIRS section! BE CAREFUL!
 */
define('DELETE_FILES', false);

/**
 * The directories and files that are to be excluded when updating the code.
 * Normally, these are the directories containing files that are not part of
 * code base, for example user uploads or server-specific configuration files.
 * Use rsync exclude pattern syntax for each element.
 */
define('EXCLUDE', serialize(array(
	'.git',
	'webroot/uploads',
	'app/config/database.php',
)));

/**
 * Temporary directory we'll use to stage the code before the update.
 */
define('TMP_DIR', '/tmp/spgd-'.md5(REMOTE_REPOSITORY).'-'.time().'/');

// Configuration end.

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Simple PHP GIT deploy script</title>
	<style>
body {
	padding: 0 1em;
	background: #000;
	color: #fff;
}
h2 {
	color: #c33;
}
.prompt {
	color: #6be234;
}
.command {
	color: #729fcf;
}
	</style>
</head>
<body>
<?php
if (!isset($_GET['sid']) || $_GET['sid'] !== SECRET_ACCESS_TOKEN) {
	die('<h2>ACCESS DENIED!</h2>');
}
if (SECRET_ACCESS_TOKEN === 'BetterChangeMeNowOrSufferTheConsequences') {
	die("<h2>You're suffering the consequences!<br>Change the SECRET_ACCESS_TOKEN from it's default value!</h2>");
}
?>
<pre>

Checking the environment &hellip;

<?php
// Check if the needed programs are available
foreach (array('git', 'rsync') as $command) {
	$path = trim(shell_exec('which '.$command));
	if ($path == '') {
		die(sprintf('<b>%s</b> not available. It need to be installed on the server for this script to work.', $command));
	} else {
		printf('<b>%s</b> : %s'."\n"
			, $path
			, explode("\n", shell_exec($path.' --version'))[0]
		);
	}
}
?>

Working &hellip;

<?php
// The commands
$commands = array(
	'echo $PWD',
	'whoami',
	'git pull',
	'git status',
	'git submodule sync',
	'git submodule update',
	'git submodule status',
);

// Run the commands
$output = '';
foreach ($commands as $command){
	// Run the command
	$tmp = shell_exec($command.' 2>&1');
	// Output the result
	printf('
<span class="prompt">$</span> <span class="command">%s</span>
%s
'
		, htmlentities(trim($command))
		, htmlentities(trim($tmp))
	);
	flush(); // Try to output everything as it happens
}
?>

Done.
</pre>
</body>
</html>
