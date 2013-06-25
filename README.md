# Deployer
_Automated code deployment using git and php._

## Requirements

`git`, `rsync`, and `tar` binaries are required on the server that's running the script (server machine).

Also, the system user that's running PHP needs to have the right ssh keys to access the remote repository (If it's a private repo) and have the required permissions to update the files on the server machine.

## Usage

 * Configure `deploy.php` and put it somewhere that's accessible from the Internet.
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
