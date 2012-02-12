# Auto-deploy with php and git(hub) on an EC2 Amazon AMI instance

This gist assumes:

 * you have a local repo
 * that pushes to a **private** github repo (origin)
 * and an EC2 Amazon AMI instance with LAMP running
   * Your webpages are served from /var/www/html/

# 1 - On your local machine

## Create the update script

The script I use is a little "verbose" in that I wanted a sanity check: it outputs the current directory, the user and then some git commands. Create a local file **github.php** with the following contents:

    <?php
        echo shell_exec('whoami');
        echo '<br />';
        echo shell_exec('echo $PWD');
        echo '<br />';
        echo shell_exec('git pull');
        echo '<br />';
        echo shell_exec('git status');

Add, commit and push this to github

    git add github.php
    git commit -m 'Added the github update script'
    git push -u origin master

# 2 - On the EC2 Machine

## Install git

    sudo yum install git-core

## Create an ssh directory for the apache user

    sudo mkdir /var/www/.ssh
    sudo chown -R apache:apache /var/www/.ssh/

## Generate a deploy key for apache user

    sudo -Hu apache ssh-keygen -t rsa # choose "no passphrase"
    sudo cat /var/www/.ssh/id_rsa.pub

# 3 - On GitHub.com

## Add the deploy key to your repo

1. https://github.com/you/yourapp/admin/keys
1. Paste the deploy key you generated on the EC2 machine

##Set up service hook in github

1. https://github.com/oodavid/1DayLater/admin/hooks
1. Select the **Post-Receive URL** service hook
1. Enter the URL to your update script - http://example.com/github.php
1. Click **Update Settings**

# 4 - On the EC2 Machine

## Pull the repo

    cd /var/www/
    sudo chown -R apache:apache html
    sudo -Hu apache git clone git@github.com:you/yourapp.git html

# Rejoice!

Now you're ready to go :-)

## Some notes

 * At this point you should be able to push to github and your site will automatically pull down code from github
 * You can manually trigger a pull by hitting http://example.com/github.php in your browser etc (you'll see the output too)
 * It would be trivial to setup another repo on your EC2 box for different branches (develop, release-candidate etc) - repeat most of the steps but checkout a branch after pulling the repo down

## Sources
 * [Build auto-deploy with php and git(hub) on an EC2 Amazon AMI instance](https://gist.github.com/1105010) - who in turn referenced:
   * [ec2-webapp / INSTALL.md](https://github.com/rsms/ec2-webapp/blob/master/INSTALL.md#readme)
   * [How to deploy your code from GitHub automatically](http://writing.markchristian.org/how-to-deploy-your-code-from-github-automatic)
