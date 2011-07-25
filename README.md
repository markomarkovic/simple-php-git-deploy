Kind of continue from the other gist [how to install LAMP on an Amazon AMI](https://gist.github.com/1105007)

##Install git

```
sudo yum install git-core
```

##Create ssh directory since it doesn't exists by default on the Amazon AMI

```
sudo mkdir /var/www/.ssh
sudo chown -R apache:apache /var/www/.ssh/
```

##Generate key for apache user

```
sudo -Hu apache ssh-keygen -t rsa  # chose "no passphrase"
sudo cat /var/www/.ssh/id_rsa.pub
# Add the key as a "deploy key" at https://github.com/you/myapp/admin
```

##Get the repo

```
cd /var/www/
sudo chown -R apache:apache html
sudo -Hu apache git clone git@github.com:yourUsername/yourApp.git html
```

##Setup the update script

```
sudo -Hu apache nano html/update.php
```

```
<?php `git pull`; ?>
```

##Set up service hook in github

1. Go to Repository Administration for your repo (http://github.com/username/repository/admin)
2. Click Service Hooks, and you'll see a list of available services. Select Post-Receive URL.
3. Enter the URL for your update script (e.g. http://example.com/update.php) and click Update Settings.

##Sources

* [ec2-webapp / INSTALL.md](https://github.com/rsms/ec2-webapp/blob/master/INSTALL.md#readme)
* [How to deploy your code from GitHub automatically](http://writing.markchristian.org/how-to-deploy-your-code-from-github-automatic)