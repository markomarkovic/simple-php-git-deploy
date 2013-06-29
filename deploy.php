<?php
/**
 * Deploy
 * Automated project deployment via Git and PHP.
 *
 * @version 1.0.8
 * @link https://github.com/markomarkovic/simple-php-git-deploy/
 *
 */

//--------------------------------------//
// REQUIRED CONFIGURATION
// Things you really must setup...

// Protect the script from unauthorized access by using a secret access token.
// Called with deploy.php?token=BetterChangeMeNowOrSufferTheConsequences
$secret_access_token = "BetterChangeMeNowOrSufferTheConsequences";

// The HTTPS or SSH address of the remote GIT repository you wish to deploy
// If the repo is private, you need to use the SSH address.
$repo_address = "https://github.com/markomarkovic/simple-php-git-deploy.git";

// The branch of the remote GIT repository (default is "master")
$repo_branch = "master";

// The location to which we'd like to deploy this code.
// Don't forget the trailing slash!
$target_directory = "/full-server-path-to-/my-deploy-location/";

// Temporary directory we'll use to stage the code before the update
// Say you have not already forgotten the trailing slash.
$tmp_directory = "/full-server-path-to-/my-deploy-location/tmp/";


//--------------------------------------//
// OPTIONAL CONFIGURATION
// Things you really don't need to touch...

// Create a backup of your current target directory prior to deploying.
// To enable, add a full path here to the desired location.
$backup_directory = "";

// Wether to delete the files that are not in the repository but are on the local machine.
// WARNING !!! This can lead to loss of data. If set to "true", all files not in the repository
// will be deleted from the destination. ...except the ones defined in the next section. 
$delete_files = false;

// Number of seconds to allow for each command before timeout.
$time_limit = 30;


//--------------------------------------//
// END CONFIGURATION – Apply variables

// Sets secret access token
define('SECRET_ACCESS_TOKEN', $secret_access_token);

// Sets the remote repository repo
define('REMOTE_REPOSITORY', $repo_address);

// Set deployment branch
define('BRANCH', $repo_branch);

// Sets target directory
define('TARGET_DIR', $target_directory);

// Sets delete file boolean
define('DELETE_FILES', $delete_files);

// Sets tmp directory
define('TMP_DIR', $tmp_directory.md5(REMOTE_REPOSITORY).'-'.time().'/');

// Sets timeout limit for each command.
define('TIME_LIMIT', $time_limit);

// Sets backup of the TARGET_DIR into BACKUP_DIR before deployment
define('BACKUP_DIR', $backup_directory);

// Sets a filename for a little version meta.
define('VERSION_FILE', TMP_DIR.'DEPLOYED_VERSION.txt');


/** TODO (merge into configuration at the top)
 * The directories and files that are to be excluded when updating the code.
 * Normally, these are the directories containing files that are not part of
 * code base, for example user uploads or server-specific configuration files.
 * Use rsync exclude pattern syntax for each element.
 *
 * @var serialized array of strings
 *
 */

define('EXCLUDE', serialize(array(
	'.git',
	'webroot/uploads',
	'app/config/database.php',
)));
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Deployer - <?php echo $repo_url ;?> => <?php echo $target_directory ;?></title>
        <style>
            body {background: #222; color: #efefef; padding: 0 2em 4em 2em; font-size:1.1em;}
            h2, .error {color: #c33; }
            .header {color:#666; display: block;}
            .prompt {color: #6be234; }
            .command {color: #729fcf; }
            .output {color: #999; }
        </style>
    </head>
    
    <body>
    <pre>

<?php
if (!isset($_GET['token']) || $_GET['token'] !== SECRET_ACCESS_TOKEN) {
    die('<h2>ACCESS DENIED</h2>');
}
if (SECRET_ACCESS_TOKEN === 'BetterChangeMeNowOrSufferTheConsequences') {
    die("<h2>You're suffering the consequences!<br>Change the SECRET_ACCESS_TOKEN from it's default value!</h2>");
}
?>

<span class="header">
 (                                    
 )\ )            (                    
(()/(    (       )\    (      (  (    
 /(_))  ))\`  ) ((_)(  )\ )  ))\ )(   
(_))_  /((_)(/(  _  )\(()/( /((_|()\  
 |   \(_))((_)_\| |((_))(_)|_))  ((_) 
 | |) / -_) '_ \) / _ \ || / -_)| '_| 
 |___/\___| .__/|_\___/\_, \___||_|   
          |_|          |__/                   

</span>

Running Deployer as <b><?php echo trim(shell_exec('whoami')); ?></b>.

Verifying your server environment&hellip;

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

Environment <span class="prompt">OK</span>.

Deploying <?php echo REMOTE_REPOSITORY; ?> <?php echo BRANCH."\n"; ?>
to <?php echo TARGET_DIR; ?>&hellip;

    <?php
    // The commands
    $commands = array();

    // === Pre-Deployment steps ===

    // Clone the repository into the TMP_DIR
    $commands[] = sprintf(
        '%s clone --depth=1 --branch %s %s %s'
        , $binaries['git']
        , BRANCH
        , REMOTE_REPOSITORY
        , TMP_DIR
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

    // === Deployment ===

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

    // === Post-Deployment steps ===

    // Remove the TMP_DIR
    $commands['cleanup'] = sprintf(
        'rm -rf %s'
        , TMP_DIR
    );

    // === Run the command steps ===

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
Sorry, errors were encountered.
Stopping the script to prevent possible data loss.
Please go see what things look like in $target_directory.
</div>

Cleaning up temporary files&hellip;

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
