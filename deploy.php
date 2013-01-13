<?php
/**
 * Simple PHP GIT deploy script
 *
 * Used to automatically update the code on the server when triggered
 * by a git hook on a repository.
 */

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
.prompt {
	color: #6be234;
}
.command {
	color: #729fcf;
}
	</style>
</head>
<body>
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
