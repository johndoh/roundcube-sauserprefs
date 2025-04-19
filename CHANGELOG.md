# Roundcube Webmail SAUserPrefs

## Version 1.20.2 (2025-04-19, rc-1.5)

- Fix PHP8 warnings (#74)
- Fix warning on contact edit
- Fix handling of old style table sorting prefs (#78)

## Version 1.20.1 (2022-10-29, rc-1.5)

- Fix saving/restoring table sort settings (#72)
- Fix assignment of SAv4 mode when no `host_config` present (#73)

## Version 1.20 (2022-06-18, rc-1.5)

- Drop support for PHP < 7.0
- Fix miss-matched score_allow/blocklist labels (#68)
- Add backwards compatibility for `_score_user_black/whitelist` (#68)
- Add support for SA v4 (#57)

## Version 1.19.3 (2021-12-19, rc-1.5)

- Fix inheritance of scroll bar style in Elastic skin

## Version 1.19.2 (2021-11-07, rc-1.5)

- Fix adding/removing addresses in address rules

## Version 1.19.1 (2021-10-30, rc-1.5)

- Revert accidental PHP version requirement change (PHP 7.0 will be required for next version)

## Version 1.19 (2021-10-19, rc-1.5)

- Support fixed table headers in Elastic skin (req rc 81dcf4a)
- Support Dark Mode in Elastic
- Support for customizing Elastic skin
- Replace references to `white/blacklist` with `allow/blocklist` (WIP see #57)
- Drop support for PHP < 5.5
- Add basic support for `TX Rep AWL` plugin

## Version 1.18.4 (2021-01-01, rc-1.4)

- Fix bug where blank entries could be added to `allowlist` on contact sync

## Version 1.18.3 (2020-11-20, rc-1.4)

- Improve address rule input
- Fix bug where Delete All was not working on Address Rules

## Version 1.18.2 (2020-04-27, rc-1.4)

- Revert depreciation of `sortedASC` & `sortedDESC`

## Version 1.18.1 (2020-01-11, rc-1.4)

- (Elastic) Fix display of Delete button in Bayes settings

## Version 1.18 (2019-10-27, rc-1.4)

- Depreciate `sortedASC` & `sortedDESC` class, use `sorted-asc` & `sorted-desc` instead
- Replace `sauserprefs_score_inc` config with `sauserprefs_score_options`
- Add `use_auto_whitelist` option
- Add `score USER_IN_BLACKLIST` option
- Add `score USER_IN_WHITELIST` option
- Add method to override any test score, see readme for more info
- Make use of icons rather than checkboxes in language option list skin configurable
- Remove unnecessary stylesheet (69f50b1)
- Add Elastic skin support
- Require Roundcube jQueryUI plugin (for import addresses dialog)
- Replace with `saprefsframe` template object `contentframe` (req rc 647a7e9)
- Add `sauserprefs_allowed_hosts` config option
- Add `sauserprefs_host_config` config option
- Replace `sauserprefs_languages` config with `sauserprefs_langs_allowed`
- Allow for localised language names
- Add support for Taskwatermark plugin
- Change `enabled/disabled` classes used in langs table to `lang-enabled/lang-disabled` for better compatibility with skins
- Remove support for depreciated config options:
  - `sauserprefs_whitelist_sync`
  - `sauserprefs_whitelist_abook_id`
  - `sauserprefs_bayes_delete`
- Remove `pagetitle` template object

## Version 1.17.1 (2017-09-29, rc-1.3)

- Fix bug saving locales/langs options
- Correct error message text from storage class (#39)

## Version 1.17 (2017-06-14, rc-1.3)

- "Flattened" the larry theme: fresher look by removing shadows and gradients

## Version 1.16 (2017-01-02, rc-1.1)

- More fixes for bayes learn thresholds
- Correct PHP warning when no address rules defined

## Version 1.15 (2015-04-02, rc-1.1)

- Add plugin hook `sauserprefs_sections_list`
- Add plugin hook `sauserprefs_section_name`
- Add plugin hook `sauserprefs_list`
- Remove unnecessary config option `sauserprefs_bayes_delete`
- Depreciate config option `sauserprefs_whitelist_sync`
- Depreciate config option `sauserprefs_whitelist_abook_id`
- Split address book sync and import options
- Allow for syncing and importing of multiple address books
- Improve extensibility of storage class (again)
- Fix setting default score for bayes learn thresholds

## Version 1.14 (2015-01-06, rc-1.1)

- Fix setting `requried_score` to default
- Improve extensibility of storage class, add override options
- Update after dev-accessibility merged to RC master
- Drop IE6 support

## Version 1.13 (2014-03-30, rc-1.0)

- Support the address format `*.exmaple.com` for black/white listing
- Make address and langs tables user sortable

## Version 1.12.1 (2014-01-30, rc-1.0)

- Fix default setting for fold headers option (typo)
- Correct skin folder structure for Roundcube packaging

## Version 1.12 (2013-12-01, rc-1.0)

- Use new settings_actions hook to create settings tab (c49c35c)
- Update config file var names to match core

## Version 1.11 (2013-05-19, rc-1.0)

_code branching/tagging no longer sync'd to roundcube versions_
- Change file structure to match core and use `include_path`

## Version 1.10 (2013-03-03, rc-0.9)

- merge PDO branch (de56ea1909)
- rename default skin to classic (c40419bdfe)
- `rcube_ui` > `rcube_utils` (r6091)
- Update for Roundcube framework

## Version 1.9 (2012-07-07, rc-0.8)

- Version ready for rc-0.8

## Version 1.8 (2012-04-08, rc-0.8)

- Make storage class to handle SQL work
- Add new config option `sauserprefs_bayes_delete` to separate function from SQL
- Remove `sauserprefs_deprecated_prefs` config option
- Make `username` configurable

## Version 1.7 (2012-01-22, rc-0.8)

- Add initial support for Larry

## Version 1.6 (2011-12-04, rc-0.7)

- Use core function to sanitize input
- Add plugin hook `sauserprefs_save`
- Fix double request when clicking on tab using Firefox (from r4472)
- Seperate `ok_locales` and `ok_langs`

## Version 1.5 (2011-01-14, rc-0.5)

- Update hooks (r3883)

## Version 1.4 (2010-08-16, rc-0.4)

- Update hooks (r3840)

## Version 1.3 (2010-08-01, rc-0.4)

- Skin update after r3757
- Use core config function (**WARNING**: Complete re-write of config file!)

## Version 1.2 (2009-11-04, rc-0.4)

- Bug fix: remove error message when saving without changes
- Allow disabling of entire sections via `dont_override`
- Allow whitelist to synchronise with any address book
- Make GLOBAL users configurable
- Allow overriding of prefs name in db (`deprecated_prefs`)
- Fix localisation of scores

## Version 1.0 (2009-09-23, rc-0.3)

- Added panel for Bayes settings
- Replace `general_settings` config option with `dont_override`
- Added contextual help
- Re-designed interface to match User Preferences
- Added `Default Score` option to spam score dropdown
- Added `Other` option to spam score dropdown (if value is not in the available options)
- Added ability to restore spam settings to default
- Remove deprecated pref `requred_hits` and replace with `requred_score`
- Swap `split()` for `explode()`, PHP 5.3 compatibility
- Added invert message language selection
- Added delete icon
- Added support for plugin template system
- Removed `display_order` config var, this is now controlled from the template
- Added new contact add/save/delete hooks
- Removed need for `contact_id` field in prefs table
- Added import and delete all functions for address rules
- Small changes to init functions for when labels are added to UI
- Translated from sauserprefs patch