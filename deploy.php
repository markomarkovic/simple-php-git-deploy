<?php
/**
 * GIT DEPLOYMENT SCRIPT
 *
 * Used for automatically deploying websites via github or bitbucket, more deets here:
 *
 *		https://gist.github.com/1809044
 */

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

// Run the commands for output
$output = '';
foreach ($commands as $command){
	// Run it
	$tmp = shell_exec($command);
	// Output
	$output .= "<span class=\"prompt\">\$</span> <span class=\"output\">{$command}\n</span>";
	$output .= htmlentities(trim($tmp)) . "\n";
}

// Make it pretty for manual user access (and why not?)
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>GIT DEPLOYMENT SCRIPT</title>
	<style>
body {
	padding: 0 1em;
	background: #000;
	color: #fff;
}
.prompt {
	color: #6be234;
}
.output {
	color: #729fcf;
}
	</style>
</head>
<body>
<pre>
 .  ____  .    ____________________________
 |/      \|   |                            |
[| <span style="color: #FF0000;">&hearts;    &hearts;</span> |]  | Git Deployment Script v0.1 |
 |___==___|  /              &copy; oodavid 2012 |
              |____________________________|

<?php echo $output; ?>
</pre>
</body>
</html>