*__THIS REPO IS DEPRECATED. GET THE NEW, UPDATED VERSION [HERE](https://github.com/sbacic/wpdemo2).__*

**WordpressDemo Plugin**

WPDemo is a plugin for Wordpress that allows you to simultaneously demo your plugin or theme to multiple users. Each user is given his own demo instance and does not affect 
the demo instances of other users.

*Features*
+   dynamically create demo instances
+   automatically remove stale instances via WPCron
+   easy setup - automatically clones tables and fixes prefixes so you don't have to

*Installation*

1.  Create a new Wordpress installation and setup your theme or plugin as you normally would. Make a regular installation, _not_ multisite.
2.  Make sure the wp-content directory is writable by PHP. Also make sure your database has proper permissions (create table, edit, etc).
3.  Create a new user that will be used to showcase your theme or plugin. __Make sure this user cannot modify or uninstall plugins.__
4.  Activate the WPDemo plugin. 
5.  Go to Config.php and make sure it matches your setup. If you're using the default table prefix ("wp_"), the default upload dir ("wp-content/uploads/") and don't have any tables starting with "wpdemo_" in your database, you can skip this step. 
6.  Copy this block of code to your wp-config.php file, just before "require_once(ABSPATH . 'wp-settings.php');":

    `require_once(ABSPATH . 'wp-content/plugins/wpdemo/generate.php');`
7. Backup your Wordpress installation, including the install directory and the database.
8. Visit your website. 

*Additional checks*
You are strongly encouraged to test your plugin in the demo environment your visitors will be using to make sure everything works properly. While WpDemo tries to handle everything automatically, 
sometimes it doesn't always work perfectly. Here are a couple of things you should check:

1. Check your database to see if the demo tables were properly created. Also check your wp-content directory to see if the uploads directory was cloned.
2. Try logging into the admin section.

*Uninstalling*

1. Remove the WPDemo code from wp-config.
2. Deactivate the WPDemo plugin in your admin backend.

On deactivation, all existing demo instances are immediately wiped.

*Security*

Granting access to you admin backend is obviously filled with certain security risks. Here are a few tips on how to minimize the risk:

* Grant only the needed privileges. If the user doesn't need to do something, don't let them do it.
* Disable anything that lets the user change the actual files, particularly things such as plugins or themes. Media is ok, because the user never works on the original uploads directory, only a clone.
* Ideally, you'd use a separate database for your demo. In case somebody does manage to do damage, it will be limited to the demo installation (and if you followed the installation instructions, you already have a backup).
* On the same note, please don't use the same Wordpress installation for running any other sites (such as your main site). That's just asking for trouble. Use a separate Wordpress installation.

*FAQ*

* How does this plugin work?
The plugin dynamically generates a new database prefix every time a new user visits the site and inserts it into wp-config. It then clones the original database tables and the uploads directory and does some tidying up so that the prefixes work properly with the demo instance.
* Is this plugin safe?
WPDemo doesn't do anything special beyond making sure that every visitor gets to play on his own copy of the database without disturbing others. If granting access to the admin backend on the original site would allow the user to cause damage, WPDemo isn't going to prevent that. Exercise caution when deciding what the user should be allowed to do and use the appropriate roles. 
* I want to change something to the original database but can't get to it. What do I do?
Just comment out the WPDemo code from wp-config, make the changes you want and uncomment the code.
