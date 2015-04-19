# Simple PHP Git deploy script
_Automatically deploy the code using PHP and Git._

## Requirements

* `git` and `rsync` are required on the server that's running the script
  (_server machine_).
  - Optionally, `tar` is required for backup functionality (`BACKUP_DIR` option).
  - Optionally, `composer` is required for composer functionality (`USE_COMPOSER`
  option).
* The system user running PHP (e.g. `www-data`) needs to have the necessary
  access permissions for the `TMP_DIR` and `TARGET_DIR` locations on
  the _server machine_.
* If the Git repository you wish to deploy is private, the system user running PHP
  also needs to have the right SSH keys to access the remote repository.

## Usage

 * Configure the script and put it somewhere that's accessible from the
   Internet. The preferred way to configure it is to use `deploy-config.php` file.
   Rename `deploy-config.example.php` to `deploy-config.php` and edit the
   configuration options there. That way, you won't have to edit the configuration
   again if you download the new version of `deploy.php`.
 * Configure your git repository to call this script when the code is updated.
   The instructions for GitHub and Bitbucket are below.

### GitHub

 1. _(This step is only needed for private repositories)_ Go to
    `https://github.com/USERNAME/REPOSITORY/settings/keys` and add your server
    SSH key.
 1. Go to `https://github.com/USERNAME/REPOSITORY/settings/hooks`.
 1. Click **Add webhook** in the **Webhooks** panel.
 1. Enter the **Payload URL** for your deployment script e.g. `http://example.com/deploy.php?sat=YourSecretAccessTokenFromDeployFile`.
 1. _Optional_ Choose which events should trigger the deployment.
 1. Make sure that the **Active** checkbox is checked.
 1. Click **Add webhook**.

### Bitbucket

 1. _(This step is only needed for private repositories)_ Go to
    `https://bitbucket.org/USERNAME/REPOSITORY/admin/deploy-keys` and add your
    server SSH key.
 1. Go to `https://bitbucket.org/USERNAME/REPOSITORY/admin/services`.
 1. Add **POST** service.
 1. Enter the URL to your deployment script e.g. `http://example.com/deploy.php?sat=YourSecretAccessTokenFromDeployFile`.
 1. Click **Save**.

### Generic Git

 1. Configure the SSH keys.
 1. Add a executable `.git/hooks/post_receive` script that calls the script e.g.

```sh
#!/bin/sh
echo "Triggering the code deployment ..."
wget -q -O /dev/null http://example.com/deploy.php?sat=YourSecretAccessTokenFromDeployFile
```

## Done!

Next time you push the code to the repository that has a hook enabled, it's
going to trigger the `deploy.php` script which is going to pull the changes and
update the code on the _server machine_.

For more info, read the source of `deploy.php`.

## Tips'n'Tricks

 * Because `rsync` is used for deployment, the `TARGET_DIR` doesn't have to be
   on the same server that the script is running e.g. `define('TARGET_DIR',
   'username@example.com:/full/path/to/target_dir/');` is going to work as long
   as the user has the right SSH keys and access permissions.
 * You can have multiple scripts with different configurations. Simply rename
   the `deploy.php` to something else, for example `deploy_master.php` and
   `deploy_develop.php` and configure them separately. In that case, the
   configuration files need to be named `deploy_master-config.php` and
   `deploy_develop-config.php` respectively.

---

If you find this script useful, consider donating BTC to `1fLnPZkMYw1TFNEsJZCciwDAmUhDw2wit`.
