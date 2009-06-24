<?php
/* Author: Philip Weir */

$labels = array();
$labels['sauserprefs']  = 'Spam';
$labels['sauserprefssettings']  = 'Spam settings';
$labels['spamgeneralsettings']  = 'General Settings';
$labels['spamthres']  = 'Score threshold';
$labels['spamsubject']  = 'Subject tag';
$labels['spamsubjectblank']  = '(leave blank for none)';
$labels['spamlang']  = 'Message languages';
$labels['enabled']  = 'Enabled';
$labels['disabled']  = 'Disabled';
$labels['headers'] = 'Message Headers';
$labels['foldheaders'] = 'Allow multiple lines in headers';
$labels['spamlevelstars'] = 'Use \'Spam Level\' header';
$labels['spamlevelchar'] = 'Spam level character';
$labels['spamtests'] = 'Internet Based Spam Tests';
$labels['userazor1'] = 'Use Razor v1';
$labels['userazor2'] = 'Use Razor v2';
$labels['usepyzor'] = 'Use Pyzor';
$labels['usebayes'] = 'Use naive Bayesian style classifier';
$labels['usedcc'] = 'Use DCC';
$labels['skiprblchecks'] = 'Use Realtime Blackhole List';
$labels['spamreportsettings']  = 'Report Settings';
$labels['spamreport0']  = 'Include report in headers of original message';
$labels['spamreport1']  = 'Include original as MIME attachment to report';
$labels['spamreport2']  = 'Include original as text-only attachment to report';
$labels['spamaddressrules']  = 'Address Rules';
$labels['whitelist_from']  = 'Accept Mail From';
$labels['blacklist_from']  = 'Reject Mail From';
$labels['whitelist_to']  = 'Accept Mail Sent To';
$labels['addrule']  = 'Add Rule';
$labels['rule']  = 'Rule';
$labels['importfromaddressbook'] = 'Import addresses from Address Book';
$labels['deleteall'] = 'Delete All';
$labels['autoscore'] = 'Auto';
$labels['defaultscore'] = 'Default Score';
$labels['otherscore'] = 'Other (%s)';
$labels['usedefault'] = 'Restore Default Settings';

$messages = array();
$messages['sauserprefchanged'] = 'Successfully changed spam settings';
$messages['sauserpreffailed'] = 'Error: Cannot change spam settings';
$messages['spamthresexp'] = 'Anything above the threshold is marked as spam. Increasing this threshold will increase the amount of spam missed, but will reduce the risk of false positives.';
$messages['spamlangexp'] = 'Select all the languages you expect to receive e-mail in. Any messages received that are written in non selected languages will be treated as possible spam.';
$messages['headersexp'] = 'SpamAssassin adds headers to processed messages providing information about the results. Use the options below to configure these headers.';
$messages['spamtestssexp'] = 'Some checks use internet based services to help identify spam. Select the ones you wish to use:';
$messages['spamreport']  = 'When a message is identified as spam a report of all tests and scores assigned is created...';
$messages['autowhitelist'] = 'Email addresses in your Address Book are automatically added to the \'Accept Mail From\' list.';
$messages['whitelistexp'] = 'An asterisk (*) can be used as a wild card for greater flexibility. For example: *@example.com or *@*.example.com.';
$messages['spamaddressexists'] = 'A rule already exists for this address';
$messages['spamenteraddress'] = 'Please enter an address to add';
$messages['spamaddresserror'] = 'Error: Address appears invalid';
$messages['spamaddressdelete'] = 'Are you sure you want to delete this rule?';
$messages['spamaddressdeleteall'] = 'Are you sure you want to delete all address rules?';
$messages['noaddressrules'] = 'No address rules found.';
$messages['importingaddresses'] = 'Importing addresses...';
$messages['usedefaultconfirm'] = 'Are you sure you want to restore the default settings? This will delete all your settings, including your address rules.';

?>