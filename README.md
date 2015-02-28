Roundcube Webmail SAUserPrefs
=============================
This plugin adds the ability for users to edit they SpamAssassin user prefs
from within Roundcube. It interacts with preferences storied in a database via
SQL. For more information on setting up SpamAssassin to work with a database
please see the [SpamAssassin Wiki][usingsql].

Tested with SpamAssassin Version 3.3

Inspiration for this plugin was taken from:
[WebUserPrefs][webuserprefs]

ATTENTION
---------
This is just a snapshot from the GIT repository and is **NOT A STABLE version
of SAUserPrefs**. It is Intended for use with the **GIT-master** version of
Roundcube and it may not be compatible with older versions. Stable versions of
SAUserPrefs are available from the [Roundcube plugin repository][rcplugrepo]
(for 1.0 and above) or the [releases section][releases] of the GitHub
repository.

License
-------
This plugin is released under the [GNU General Public License Version 3+][gpl].

Even if skins might contain some programming work, they are not considered
as a linked part of the plugin and therefore skins DO NOT fall under the
provisions of the GPL license. See the README file located in the core skins
folder for details on the skin license.

Install
-------
* Place this plugin folder into plugins directory of Roundcube
* Add sauserprefs to $config['plugins'] in your Roundcube config

**NB:** When downloading the plugin from GitHub you will need to create a
directory called sauserprefs and place the files in there, ignoring the root
directory in the downloaded archive.

Config
------
The default config file is plugins/sauserprefs/config.inc.php.dist
Rename this to plugins/sauserprefs/config.inc.php
* You must set the database connection string
* Enter the table name, name of the username field, preference field, and value
field

Changing the order of the sections
----------------------------------
To change the order of the sections add a sections attribute with the sections
listed in the desired order to the sasectionslist object in
skins/[skin]/templates/sauserprefs.html. For example:
```html
<roundcube:object name="sasectionslist" id="sections-table"
  class="records-table" cellspacing="0"
  sections="general,tests,bayes,headers,report,addresses" />
```

Delete user bayesian data stored in database
--------------------------------------------
If the bayesian data is stored in the same database as the user prefs then it
is possible for users to delete their data from the UI.
See config file for example SQL

"SERVICE CURRENTLY NOT AVAILABLE! Error No. [500]" Error Message
----------------------------------------------------------------
On some setups users might see "SERVICE CURRENTLY NOT AVAILABLE! Error No.
[500]" shows up at the top of the sauserprefs screen. In this case there could
be a problem with the database connection. Try adding ?new_link=true to the end
of the sauserprefs DSN in the config file. For example:
```php
$config['sauserprefs_db_dsnw'] =
'mysql://username:password@localhost/database?new_link=true';
```

sauserprefs_save hook
---------------------
Before prefs are saved to the database the plugin hook sauserprefs_save is
executed, this allows you to perform any custom actions like extra validation
or setting specific values.
Arguments:
* section: (string) current prefs section
* cur_prefs: (array) the current user preferences
* new_prefs: (array) the new preferences
* global_prefs: (array) the global preferences

Return:
* new_prefs: (array) the new preferences
* abort: (boolean) if true the prefs will not be saved
* message: (string) optional reason why the prefs were not saved which will be
  shown to the user

sauserprefs_sections_list hook
------------------------------
This allows you to modify the sections list.
Arguments:
* list: (array) the current setions array
* cols: (array) column names to display

Return:
* list: (array) the new setions array
* cols: (array) column names to display

sauserprefs_section_name hook
-----------------------------
This allows you to modify the title displayed at top of the preferences screen.
Arguments:
* section: (string) selected section of the prefs
* title: (string) the title for the current section

Return:
* title: (string) the title for the current section

sauserprefs_list hook
---------------------
This allows you to modify the elements of the preferences screen before they
are displayed.
Arguments:
* section: (string) selected section of the prefs
* block: (array) array containing preferences blocks/options

Return:
* block: (array) array containing preferences blocks/options

Replacing the storage class
---------------------------
To replace the default sql storage class with your own you need to set a
special config options:
 * sauserprefs_storage: (string) the suffix of the storage class
   e.g. 'sql' for the default sql storage class
The Roundcube config object is passed to the constructor of the class

[usingsql]: http://wiki.apache.org/spamassassin/UsingSQL
[webuserprefs]: http://sourceforge.net/projects/webuserprefs/
[rcplugrepo]: http://plugins.roundcube.net/packages/johndoh/sauserprefs
[releases]: http://github.com/JohnDoh/Roundcube-Plugin-SpamAssassin-User-Prefs-SQL/releases
[gpl]: http://www.gnu.org/licenses/gpl.html