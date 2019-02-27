<a href="https://travis-ci.org/catalyst/moodle-tool_etl">
<img src="https://api.travis-ci.org/catalyst/moodle-tool_etl.svg?branch=master">
</a>

# Extract, transform, load (ETL)
ETL is a Moodle admin tool that allows to extract, transform and then load any data from a source system to a target system.

## Installation
To install this plugin in your Moodle.
1. Get the code and copy/install it to: `<moodledir>/admin/tool/etl`
2. Run the upgrade using Moodle admin interface or command line, like `sudo -u www-data php admin/cli/upgrade.php`

**Note:** the user may be different to www-data on your system.

## Configuration
1. Log into your Moodle site as an administrator.
2. Navigate to  `Site administration ► Plugins ► Admin tools ► Extract, transform, load (ETL)`.
3. Create a new task: set Source, Target and Processor and configure each element as required. Also set a schedule for the task.
4. The tasks will be executed on Moodle cron.
5. You can see a history of any task execution.

## Existing elements

### Sources
* **FTP** - remote FTP server, login using username and password.
* **SFTP** - remote SFTP server, login using username and password.
* **SFTP with key authentication** - remote SFTP server, login using SSH key.
* **Server folder** - server local folder.
* **Database** - SQL query to export.
* **URL** - remote URL.

### Targets
* **Moodle sitedata** - a folder inside Moodle sitedata directory.
* **Server folder** - server local folder.
* **SFTP with key authentication** - remote SFTP server, login using SSH key.


### Processors
* **Default processor** - doesn't do any transformation, simply pass extracted data from a source to a target.
* **Lowercase processor** - transforms csv fields to lowercase 

## Developer notes
To create a new source, target or processor you'd just need to create a new class and extend a relevant base class and implement related interface. See existing elements as an example.

# Crafted by Catalyst IT

This plugin was developed by Catalyst IT Australia:

https://www.catalyst-au.net/

# Contributing and Support

Issues, and pull requests using github are welcome and encouraged!

https://github.com/catalyst/moodle-tool_etl/issues

If you would like commercial support or would like to sponsor additional improvements
to this plugin please contact us:

https://www.catalyst-au.net/contact-us
