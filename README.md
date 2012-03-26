
# Deploy your site with git

This gist assumes:

* you have a local git repo
* with an online remote repository (github / bitbucket etc)
* and a cloud server (Rackspace cloud / Amazon EC2 etc)
  * your (PHP, Java, Perl, RoR, JSP) scripts are served from /var/www/html/
  * your webpages are executed by apache
  * apache's home directory is /var/www/ 
  * ***(this describes a pretty standard apache setup on Redhat / Ubuntu / CentOS / Amazon AMI etc)***

# 1 - On your local machine

## 1.1 - Grab a deployment script for your site

* [deploy.php](#file_deploy.php)
* *other scripts yet to be written*

## 1.2 - Add, commit and push this to github

        git add deploy.php
        git commit -m 'Added the git deployment script'
        git push -u origin master

# 2 - On your server

## 2.1 - Install git...

After you've installed git, make sure it's a relatively new version - old scripts quickly become problematic as github / bitbucket / whatever will have the latests and greatest, if you don't have a recent version you'll need to figure out how to upgrade it :-)

        git --version

### ...on CentOS 5.6

        # Add a nice repo
        rpm -Uvh http://repo.webtatic.com/yum/centos/5/latest.rpm
        # Install git
        yum install --enablerepo=webtatic git-all

### ...using generic yum

        sudo yum install git-core

## 2.2 - Setup git

        # Setup
        git config --global user.name "Server"
        git config --global user.email "server@server.com"

## 2.3 - Create an ssh directory for the apache user

        sudo mkdir /var/www/.ssh
        sudo chown -R apache:apache /var/www/.ssh/

## 2.4 - Generate a deploy key for apache user

        sudo -Hu apache ssh-keygen -t rsa # choose "no passphrase"
        sudo cat /var/www/.ssh/id_rsa.pub

# 3 - On your git origin (github / bitbucket)

## Add the deploy key to your repo

1. https://github.com/you/yourapp/admin/keys
1. Paste the deploy key you generated on the EC2 machine

##Set up service hook in github

1. https://github.com/oodavid/1DayLater/admin/hooks
1. Select the **Post-Receive URL** service hook
1. Enter the URL to your update script - http://example.com/github.php
1. Click **Update Settings**

# 4 - On the Server

## Pull the code

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
