# Deployer
_Automated project deployment via Git and PHP._

## Requirements

* `git`, `rsync`, and `tar` binaries are required on the machine to which you would like to deploy.

* The system user that's running PHP (e.g. `www-data`) needs to have the necessary permissions required to update files on the server machine.

* If the Git repo you wish to deploy is private, the system user running PHP also needs to have a valid ssh key in order to access the remote repository.

## Usage

 * Configure `deploy.php` and put it somewhere that's publicly accessible from the Internet.
 * Configure your git repository to call this script when the code is updated. The instructions for GitHub and Bitbucket are below.

### GitHub

 1. Go to `https://github.com/USERNAME/REPOSITORY/settings/keys` and add your server SSH key (only needed for private repositories)
 1. Go to `https://github.com/USERNAME/REPOSITORY/admin/hooks`
 1. Select the **WebHook URLs** service hook
 1. Enter the URL to your deployment script e.g. `http://example.com/deploy.php?sat=YourSecretAccessTokenFromDeployFile`
 1. Click **Update Settings**

### Bitbucket

 1. Go to `https://bitbucket.org/USERNAME/REPOSITORY/admin/deploy-keys` and add your server SSH key (only needed for private repositories)
 1. Go to `https://bitbucket.org/USERNAME/REPOSITORY/admin/services`
 1. Add **POST** service
 1. Enter the URL to your deployment script e.g. `http://example.com/deploy.php?sat=YourSecretAccessTokenFromDeployFile`
 1. Click **Save**

### Generic GIT

 1. Configure the SSH keys
 1. Add a executable `.git/hooks/post_receive` script that calls the script e.g.

```sh
#!/bin/sh
echo "Triggering the code deployment ..."
wget -q -O /dev/null http://example.com/deploy.php?sat=YourSecretAccessTokenFromDeployFile
```

## Done!

Next time you push the code to the repository that has a hook enabled, it's going to trigger the `deploy.php` script which is going to pull the changes and update the code on the server machine.

For more info, read the source of `deploy.php`.

---

_Inspired by [a Gist by oodavid](https://gist.github.com/1809044)_
