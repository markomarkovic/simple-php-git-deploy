<?php
/**
 * Deployment configuration
 *
 * It's preferable to configure the script using this file istead of directly.
 *
 * Rename this file to `deploy-config.php` and edit the
 * configuration options here instead of directly in `deploy.php`.
 * That way, you won't have to edit the configuration again if you download the
 * new version of `deploy.php`.
 *
 * @version 1.2.2-multideployments
 */

/**
 * Protect the script from unauthorized access by using a secret access token.
 * If it's not present in the access URL as a GET variable named `sat`
 * e.g. deploy.php?sat=Bett...s the script is not going to deploy.
 *
 * @var string
 */
define('SECRET_ACCESS_TOKEN', 'BetterChangeMeNowOrSufferTheConsequences');

/**
 * The address of the remote Git repository that contains the code that's being
 * deployed.
 * If the repository is private, you'll need to use the SSH address.
 *
 * @var string
 */
define('REMOTE_REPOSITORY', 'https://github.com/markomarkovic/simple-php-git-deploy.git');

/**
 * The branch that's being deployed.
 * Must be present in the remote repository.
 *
 * @var string
 */
define('BRANCH', 'master');

/**
 * The location that the code is going to be deployed to.
 * Don't forget the trailing slash!
 *
 * @var string Full path including the trailing slash
 */
define('TARGET_DIR', '/tmp/simple-php-git-deploy/');

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
 * Temporary directory we'll use to stage the code before the update. If it
 * already exists, script assumes that it contains an already cloned copy of the
 * repository with the correct remote origin and only fetches changes instead of
 * cloning the entire thing.
 *
 * @var string Full path including the trailing slash
 */
define('TMP_DIR', '/tmp/spgd-'.md5(REMOTE_REPOSITORY).'/');

/**
 * Whether to remove the TMP_DIR after the deployment.
 * It's useful NOT to clean up in order to only fetch changes on the next
 * deployment.
 */
define('CLEAN_UP', true);

/**
 * Output the version of the deployed code.
 *
 * @var string Full path to the file name
 */
define('VERSION_FILE', TMP_DIR.'VERSION.txt');

/**
 * Time limit for each command.
 *
 * @var int Time in seconds
 */
define('TIME_LIMIT', 30);

/**
 * OPTIONAL
 * Backup the TARGET_DIR into BACKUP_DIR before deployment.
 *
 * @var string Full backup directory path e.g. '/tmp/'
 */
define('BACKUP_DIR', false);

/**
 * OPTIONAL
 * Whether to invoke composer after the repository is cloned or changes are
 * fetched. Composer needs to be available on the server machine, installed
 * globaly (as `composer`). See http://getcomposer.org/doc/00-intro.md#globally
 *
 * @var boolean Whether to use composer or not
 * @link http://getcomposer.org/
 */
define('USE_COMPOSER', false);

/**
 * OPTIONAL
 * The options that the composer is going to use.
 *
 * @var string Composer options
 * @link http://getcomposer.org/doc/03-cli.md#install
 */
define('COMPOSER_OPTIONS', '--no-dev');

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
$DEPLOYMENTS = array(
	BRANCH => array(
		'TARGET_DIR'		=> TARGET_DIR,
		'DELETE_FILES'		=> DELETE_FILES,
		'EXCLUDE'			=> EXCLUDE,
		'COMPOSER_OPTIONS'	=> COMPOSER_OPTIONS,
	),
	/*
	'development-branch' => array(
		'TARGET_DIR'        => '/alternate/path',
	),
	*/
 );
