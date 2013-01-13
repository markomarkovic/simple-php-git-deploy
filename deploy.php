<?php
/**
 * Simple PHP GIT deploy script
 *
 * Used to automatically update the code on the server when triggered
 * by a git hook on a repository.
 */

/**
 * Protect the script from unauthorized access by using a secret string.
 * If it's not present in the access url as a GET variable named `sat`
 * e.g. deploy.php?sat=Bett...s the script is going to fail.
 */
define('SECRET_ACCESS_TOKEN', 'BetterChangeMeNowOrSufferTheConsequences');

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
	$tmp = shell_exec($command);
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
