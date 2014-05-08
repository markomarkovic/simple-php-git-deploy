<?php
/**
 * Simple PHP Git deploy script
 *
 * Automatically deploy the code using PHP and Git.
 *
 * @version 1.2.2-multideployments
 * @link    https://github.com/markomarkovic/simple-php-git-deploy/
 */

// =========================================[ Configuration start ]===

/**
 * It's preferable to configure the script using `deploy-config.php` file.
 *
 * Rename `deploy-config.example.php` to `deploy-config.php` and edit the
 * configuration options there instead of here. That way, you won't have to edit
 * the configuration again if you download the new version of `deploy.php`.
 */
if (file_exists(basename(__FILE__, '.php').'-config.php')) require_once basename(__FILE__, '.php').'-config.php';

/**
 * Protect the script from unauthorized access by using a secret access token.
 * If it's not present in the access URL as a GET variable named `sat`
 * e.g. deploy.php?sat=Bett...s the script is not going to deploy.
 *
 * @var string
 */
if (!defined('SECRET_ACCESS_TOKEN')) define('SECRET_ACCESS_TOKEN', 'BetterChangeMeNowOrSufferTheConsequences');

/**
 * The address of the remote Git repository that contains the code that's being
 * deployed.
 * If the repository is private, you'll need to use the SSH address.
 *
 * @var string
 */
if (!defined('REMOTE_REPOSITORY')) define('REMOTE_REPOSITORY', 'https://github.com/markomarkovic/simple-php-git-deploy.git');

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

/**
 * If you want to deploy multiple branches to different locations you can
 * create multiple configurations here. See the relevant constant documentation
 * for the exact meaning of the options. You must use the above constant for the
 * default/production configuration (e.g. master branch)
 *
 * You can safely leave out a specific configuration option for a branch (e.g. TARGET_DIR).
 * If so the default options from the constant will be used
 *
 * The branch name can have PCRE regular expressions. The configuration options
 * can use the matching groups. E.g. branch name 'feature-(\w+)' and TARGET_DIR
 * '/tmp/deployments/experimental-features/$1'
 *
 * Use the GET 'branch' parameter to select a single branch to deploy. By default
 * All branches which have configuration here will be deployed. 
 *
 * Array structure is as follows:
 * <code>
 * array(
 *		'BRANCH' => array(
 *			'TARGET_DIR'        => '/tmp/simple-php-git-deploy/'
 *			'DELETE_FILES'      => false
 *			'EXCLUDE'           => array('.git', 'webroot/uploads', 'app/config/database.php')
 *			'COMPOSER_OPTIONS'  => '--no-dev'
 *     )
 * )
 * </code>
 * @var (mixed[])[]
 */
 if(!isset($DEPLOYMENTS)) $DEPLOYMENTS = array(
	BRANCH => array(
		'TARGET_DIR'        => TARGET_DIR,
		'DELETE_FILES'      => DELETE_FILES,
		'EXCLUDE'           => EXCLUDE,
		'COMPOSER_OPTIONS'  => COMPOSER_OPTIONS,
	),
 );

// ===========================================[ Configuration end ]===

// ===========================================[ Function definitions start ]===

/**
* Execute a command and print the output
* @param string $command the command to be executed
* @param string $error_recovery_commands Command to be executed on error
* @param boolean $return_output whether to return output as a string
* @return (boolean|string[]) true or output as string array depending on $return_output when command was succesfully executed, false on error
*/
function execute_command($command, $error_recovery_command = "", $return_output = false) {
	set_time_limit(TIME_LIMIT); // Reset the time limit for each command
	if (file_exists(TMP_DIR) && is_dir(TMP_DIR)) {
		chdir(TMP_DIR); // Ensure that we're in the right directory
	}
	$output = array();
	exec($command.' 2>&1', $output, $return_code); // Execute the command
	// Output the result
	printf(
        "    <span class=\"prompt\">$</span> <span class=\"command\">%s</span>\n\n"
        , htmlentities(trim($command))
    );
    
    if(!empty($output)) {
        printf("<div class=\"output\">%s\n</div>\n"
        , htmlentities(implode("\n",array_map("trim",$output)))
        );
    }
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
		if ($error_recovery_command != "") {
			$tmp = shell_exec($error_recovery_command);
			printf('


	Cleaning up temporary files ...

	<span class="prompt">$</span> <span class="command">%s</span>
	<div class="output">%s</div>
	'
			, htmlentities(trim($error_recovery_command))
			, htmlentities(trim($tmp))
			);
		}
		error_log(sprintf(
		'Deployment error! %s'
		, __FILE__
		));
		return false;
	}else {
        if($return_output) {
            return $output;
        }else {
            return true;
        }
	}
}

// ===========================================[ Function definitions end ]===

// ===========================================[ Start of script ]===

// If there's authorization error, set the correct HTTP header.
if (!isset($_GET['sat']) || $_GET['sat'] !== SECRET_ACCESS_TOKEN || SECRET_ACCESS_TOKEN === 'BetterChangeMeNowOrSufferTheConsequences') {
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
.output_variable { color: #ffcc11;}
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
Repository: <span class="output_variable"><?php echo REMOTE_REPOSITORY; ?></span>

<?php
// ========================================[ Pre-Deployment steps ]===
if(!isset($_GET['branch'])) {
	if(count($DEPLOYMENTS) > 1) {
		echo "You can select which branch to deploy by appending &amp;branch=xxx to the url.\n";
		echo "Will now do all all deployments\n\n";
	}
}

// Make sure the local repository is available and up-to-date
if (!is_dir(TMP_DIR)) {
	// Clone the repository into the TMP_DIR
	if(execute_command(sprintf(
		'git clone --depth=1 %s %s'
		, REMOTE_REPOSITORY
		, TMP_DIR
	)) === false) {
        die(); // Error will be displayed in execute_command
    }
} else {
	// TMP_DIR exists and hopefully already contains the correct remote origin
	// so we'll fetch the changes and reset so checkouts don't give errors
	if(execute_command(sprintf(
		'git --git-dir="%s.git" --work-tree="%s" fetch -p origin'
		, TMP_DIR
		, TMP_DIR
	)) === false) {
        die(); // Error will be displayed in execute_command
    }
	if(!execute_command(sprintf(
		'git --git-dir="%s.git" --work-tree="%s" reset --hard FETCH_HEAD'
		, TMP_DIR
		, TMP_DIR
	))) {
        die(); // Error will be displayed in execute_command
    }
}

// Get all the remote branches from git
$remote_branches = execute_command(sprintf(
    'git --git-dir="%s.git" --work-tree="%s" branch -r'
	, TMP_DIR
	, TMP_DIR
	),"",true);
if($remote_branches === false) {
    die(); // Error will have been displayed in execute_command
}

// Strip "origin/" and remove pointers (e.g. "origin/HEAD -> origin/master" to just "HEAD")
foreach($remote_branches as &$rembranch) {
       $rembranch = preg_replace('#^\S+?/(\S+)( -> .*)?$#', '$1', trim($rembranch));
}

foreach($remote_branches as $branch) {
    // Check if branch parameter is set 
    if(isset($_GET['branch']) && $branch !== $_GET['branch'] && !preg_match('~'.$branch.'~', $_GET['branch'])) {
		// branch parameter doesn't match branch
        continue;
	}
    
    // Try to find relevant configuration for this branch
    $branch_config = null;
    // Try exact match
    if(isset($DEPLOYMENTS[$branch])){
        $branch_config = $DEPLOYMENTS[$branch];
    }else {
        // Try regex match
        foreach($DEPLOYMENTS as $branch_regex => $possible_config) {
			// ~ is not valid in a git branch name and should be safe to use as a delimiter
            if(preg_match('~'.$branch_regex.'~', $branch)) {
                $branch_config = $possible_config;
		// Regex replace the configuration options, so e.g. $1 can be the branch name in the option
		foreach($branch_config as &$option) {
			$option = preg_replace('~'.$branch_regex.'~', $option, $branch);
		}
                break;
            }
        }
    }
    if($branch_config === null) {
        printf("No deployment configuration found for branch <span class=\"output_variable\">%s</span>\n\n", $branch);
        continue;
    }
	
	// Set to defaults if not specified
	if(!isset($branch_config['TARGET_DIR']))       $branch_config['TARGET_DIR']       = TARGET_DIR;
	if(!isset($branch_config['DELETE_FILES']))     $branch_config['DELETE_FILES']     = DELETE_FILES;
	if(!isset($branch_config['EXCLUDE']))          $branch_config['EXCLUDE']          = EXCLUDE;
	if(!isset($branch_config['COMPOSER_OPTIONS'])) $branch_config['COMPOSER_OPTIONS'] = COMPOSER_OPTIONS;
    
    // Protect all the poor sods who did forget the target dir trailing slash
    if(substr($branch_config['TARGET_DIR'], -1) !== "/") $branch_config['TARGET_DIR'] .= '/';
	
    printf("Deploying <span class=\"output_variable\">%s</span>\n", $branch);
    printf("to        <span class=\"output_variable\">%s</span> ...\n\n", $branch_config['TARGET_DIR']);

	// The commands
	$commands = array();

	// ========================================[ Pre-Deployment steps ]===

	// Make sure the working tree is clean
	$commands[] = sprintf(
		'git --git-dir="%s.git" --work-tree="%s" reset --hard'
		, TMP_DIR
		, TMP_DIR
	);
	// Checkout the right branch. This syntax is valid with Git 1.6.6+
	$commands[] = sprintf(
		'git --git-dir="%s.git" --work-tree="%s" checkout origin/%s'
        , TMP_DIR
        , TMP_DIR
		, $branch
	);

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
	foreach (unserialize($branch_config['EXCLUDE']) as $exc) {
		$exclude .= ' --exclude='.$exc;
	}
    
    // Make sure target directory exists
    if(!is_dir($branch_config['TARGET_DIR'])) {
        $commands[] = sprintf(
            'mkdir -p %s'
            ,$branch_config['TARGET_DIR']
        );
    }
    
	// Deployment command
	$commands[] = sprintf(
		'rsync -rltgoDzv %s %s %s %s'
		, TMP_DIR
		, $branch_config['TARGET_DIR']
		, ($branch_config['DELETE_FILES']) ? '--delete-after' : ''
		, $exclude
	);

	// =======================================[ Post-Deployment steps ]===

	// Remove the TMP_DIR (depends on CLEAN_UP)
	if (CLEAN_UP) {
		$cleanup_command = sprintf(
			'rm -rf %s'
			, TMP_DIR
		);
        $commands[] = $cleanup_command;
	} else {
        $cleanup_command = "";
    }

	// =======================================[ Run the command steps ]===

	foreach ($commands as $command) {
		if(!execute_command($command, $cleanup_command)) {
			break; // Error will be displayed in execute_command
		}
	}
}
?>

Done.
</pre>
</body>
</html>
