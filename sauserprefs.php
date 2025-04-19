<?php

/**
 * SAUserPrefs
 *
 * Plugin to allow the user to manage their SpamAssassin settings using an SQL database
 *
 * @requires jQueryUI plugin
 *
 * @author Philip Weir
 *
 * Copyright (C) Philip Weir
 *
 * This program is a Roundcube (https://roundcube.net) plugin.
 * For more information see README.md.
 * For configuration see config.inc.php.dist.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Roundcube. If not, see https://www.gnu.org/licenses/.
 */
class sauserprefs extends rcube_plugin
{
    public $task = 'mail|addressbook|settings';
    public $allowed_prefs = ['sauserprefs_sort'];
    private $storage;
    private $sections = [];
    private $cur_section;
    private $global_prefs;
    private $user_prefs;
    private $score_prefs = [];
    private $addressbook_import = [];
    private $addressbook_sync = [];
    private $sa_langs = [
        'af', 'am', 'ar', 'be', 'bg', 'bs', 'ca', 'cs',
        'cy', 'da', 'de', 'el', 'en', 'eo', 'es', 'et',
        'eu', 'fa', 'fi', 'fr', 'fy', 'ga', 'gd', 'he',
        'hi', 'hr', 'hu', 'hy', 'id', 'is', 'it', 'ja',
        'ka', 'ko', 'la', 'lt', 'lv', 'mr', 'ms', 'ne',
        'nl', 'no', 'pl', 'pt', 'qu', 'rm', 'ro', 'ru',
        'sa', 'sco', 'sk', 'sl', 'sq', 'sr', 'sv', 'sw',
        'ta', 'th', 'tl', 'tr', 'uk', 'vi', 'yi', 'zh',
        'zh.big5', 'zh.gb2312',
    ];
    private $sa_locales = ['en', 'ja', 'ko', 'ru', 'th', 'zh'];
    private $sa_user;
    private $bayes_query;
    private $rcube;
    private $no_override = [];

    private $awl_plugin = 'use_auto_welcomelist';

    // old => new
    public static $deprecated_prefs = [
        'required_hits' => 'required_score',
        'use_auto_whitelist' => 'use_auto_welcomelist',
        'score USER_IN_BLACKLIST' => 'score USER_IN_BLOCKLIST',
        'score USER_IN_WHITELIST' => 'score USER_IN_WELCOMELIST',
        'whitelist_from' => 'welcomelist_from',
        'blacklist_from' => 'blocklist_from',
        'whitelist_to' => 'welcomelist_to',
    ];

    public static $SAv4 = false;
    public static $SAv4_prefs = [
        'use_auto_whitelist' => 'use_auto_welcomelist',
        'score USER_IN_BLACKLIST' => 'score USER_IN_BLOCKLIST',
        'score USER_IN_WHITELIST' => 'score USER_IN_WELCOMELIST',
        'whitelist_from' => 'welcomelist_from',
        'blacklist_from' => 'blocklist_from',
        'whitelist_to' => 'welcomelist_to',
    ];

    public function init()
    {
        $this->rcube = rcube::get_instance();
        $this->load_config();
        $this->_load_host_config();

        // set SpamAssassin v4 mode (added v1.20)
        self::$SAv4 = $this->rcube->config->get('sauserprefs_sav4', false);

        // Host exceptions
        $hosts = $this->rcube->config->get('sauserprefs_allowed_hosts');
        if (!empty($hosts) && !in_array($_SESSION['storage_host'], (array) $hosts)) {
            return;
        }

        $this->sa_user = $this->rcube->config->get('sauserprefs_userid', "%u");

        $identity_arr = $this->rcube->user->get_identity();
        $identity = $identity_arr['email'];
        $this->sa_user = str_replace('%u', $_SESSION['username'], $this->sa_user);
        $this->sa_user = str_replace('%l', $this->rcube->user->get_username('local'), $this->sa_user);
        $this->sa_user = str_replace('%d', $this->rcube->user->get_username('domain'), $this->sa_user);
        $this->sa_user = str_replace('%i', $identity, $this->sa_user);

        $abook_sync = $this->rcube->config->get('sauserprefs_abook_sync', false);
        if ($abook_sync === true) {
            $this->addressbook_sync = [0];
        }
        elseif ($abook_sync !== false) {
            $this->addressbook_sync = !is_array($abook_sync) ? [$abook_sync] : $abook_sync;
        }

        $abook_import = $this->rcube->config->get('sauserprefs_abook_import', false);
        if ($abook_import === true) {
            $this->addressbook_import = [0];
        }
        elseif ($abook_import !== false) {
            $this->addressbook_import = !is_array($abook_import) ? [$abook_import] : $abook_import;
        }

        $this->bayes_query = $this->rcube->config->get('sauserprefs_bayes_delete_query');

        if ($this->rcube->task == 'settings') {
            $this->no_override = array_flip($this->rcube->config->get('sauserprefs_dont_override', []));

            // replace deprecated prefs
            foreach (self::$deprecated_prefs as $old => $new) {
                if (array_key_exists($old, $this->no_override) && !array_key_exists($new, $this->no_override)) {
                    $this->no_override[$new] = $this->no_override[$old];
                    unset($this->no_override[$old]);
                }
            }

            $this->add_texts('localization/');
            $this->include_stylesheet($this->local_skin_path() . '/tabstyles.css');

            $this->sections = [
                'general' => ['id' => 'general', 'class' => 'general', 'section' => $this->gettext('spamgeneralsettings')],
                'tests' => ['id' => 'tests', 'class' => 'tests', 'section' => $this->gettext('spamtests')],
                'bayes' => ['id' => 'bayes', 'class' => 'bayes', 'section' => $this->gettext('bayes')],
                'headers' => ['id' => 'headers', 'class' => 'headers', 'section' => $this->gettext('headers')],
                'report' => ['id' => 'report', 'class' => 'report', 'section' => $this->gettext('spamreportsettings')],
                'addresses' => ['id' => 'addresses', 'class' => 'addresses', 'section' => $this->gettext('spamaddressrules')],
                'scores' => ['id' => 'scores', 'class' => 'scores', 'section' => $this->gettext('testscores')],
            ];
            $this->cur_section = rcube_utils::get_input_string('_section', rcube_utils::INPUT_GPC);

            $this->add_hook('settings_actions', [$this, 'settings_tab']);
            $this->register_action('plugin.sauserprefs', [$this, 'init_html']);
            $this->register_action('plugin.sauserprefs.edit', [$this, 'init_html']);
            $this->register_action('plugin.sauserprefs.save', [$this, 'save']);
            $this->register_action('plugin.sauserprefs.welcomelist_import', [$this, 'welcomelist_import']);
            $this->register_action('plugin.sauserprefs.purge_bayes', [$this, 'purge_bayes']);

            // integration with taskwatermark plugin
            $this->add_hook('taskwatermark_show', [$this, 'taskwatermark_show']);
        }
        elseif (!empty($this->addressbook_sync)) {
            $this->add_hook('contact_create', [$this, 'contact_add']);
            $this->add_hook('contact_update', [$this, 'contact_save']);
            $this->add_hook('contact_delete', [$this, 'contact_delete']);
        }
    }

    public function settings_tab($p)
    {
        // add sauserprefs tab
        $p['actions'][] = ['action' => 'plugin.sauserprefs', 'class' => 'sauserprefs', 'label' => 'sauserprefs.sauserprefs', 'title' => 'sauserprefs.managespam', 'role' => 'button', 'aria-disabled' => 'false', 'tabindex' => '0'];

        return $p;
    }

    public function init_html()
    {
        $this->_init_storage();
        $this->_load_global_prefs();
        $this->_load_user_prefs();

        $this->rcube->output->set_pagetitle($this->gettext('sauserprefssettings'));
        $this->include_script('sauserprefs.js');
        $this->include_stylesheet($this->local_skin_path() . '/sauserprefs.css');

        if ($this->rcube->action == 'plugin.sauserprefs.edit') {
            $this->user_prefs = array_merge($this->global_prefs, $this->user_prefs);

            // use jQuery for popup window (required by core, no need to include here)
            //$this->require_plugin('jqueryui');
            $this->rcube->output->include_script('list.js');
            $this->rcube->output->add_handler('userprefs', [$this, 'gen_form']);
            $this->rcube->output->add_handler('sectionname', [$this, 'prefs_section_name']);
            $this->rcube->output->send('sauserprefs.settingsedit');
        }
        else {
            $this->rcube->output->add_handler('sasectionslist', [$this, 'section_list']);
            // backwards compatibility saprefsframe removed in 1.18
            $this->rcube->output->add_handler('saprefsframe', [$this, 'preference_frame']);
            $this->rcube->output->send('sauserprefs.sauserprefs');
        }
    }

    public function section_list($attrib)
    {
        // add id to message list table if not specified
        if (!strlen($attrib['id'])) {
            $attrib['id'] = 'rcmsectionslist';
        }

        $sections = [];

        // if template overrides default array then rebuild the array in the new order
        if (isset($attrib['sections'])) {
            $new_sections = [];
            $keys = preg_split('/[\s,;]+/', str_replace(["'", '"'], '', $attrib['sections']));
            foreach ($keys as $key) {
                $new_sections[] = $this->sections[$key];
            }
            $this->sections = $new_sections;
        }

        $data = $this->rcube->plugins->exec_hook('sauserprefs_sections_list', ['list' => $this->sections, 'cols' => ['section']]);

        foreach ($data['list'] as $id => $block) {
            if (!isset($this->no_override['{' . $id . '}'])) {
                $sections[$id] = $block;
            }
        }

        // remove the test scores section if its not needed
        if (empty($this->score_prefs)) {
            unset($sections['scores']);
        }

        // create HTML table
        $out = $this->rcube->table_output($attrib, $sections, $data['cols'], 'id');

        // set client env
        $this->rcube->output->add_gui_object('sectionslist', $attrib['id']);
        $this->rcube->output->include_script('list.js');

        return $out;
    }

    public function preference_frame($attrib)
    {
        $attrib['name'] = 'contentframe';

        return $this->rcube->output->just_parse('<roundcube:object' . html::attrib_string($attrib) . ' />');
    }

    public function gen_form($attrib)
    {
        $this->rcube->output->add_label(
            'sauserprefs.spamaddressexists', 'sauserprefs.spamenteraddress',
            'sauserprefs.spamaddresserror', 'sauserprefs.spamaddressdelete',
            'sauserprefs.spamaddressdeleteall', 'sauserprefs.enabled', 'sauserprefs.disabled',
            'sauserprefs.importingaddresses', 'sauserprefs.usedefaultconfirm', 'sauserprefs.purgebayesconfirm',
            'sauserprefs.welcomelist_from', 'sauserprefs.saupusedefault', 'sauserprefs.importaddresses',
            'sauserprefs.selectimportsource', 'import');

        // output table sorting prefs
        $sorts = $this->rcube->config->get('sauserprefs_sort', '{}');
        $sorts = is_string($sorts) ? json_decode($sorts, true) : [];
        if (!isset($sorts['#spam-langs-table'])) {
            $sorts['#spam-langs-table'] = [0, 'true'];
        }
        if (!isset($sorts['#address-rules-table'])) {
            $sorts['#address-rules-table'] = [1, 'true'];
        }
        $this->rcube->output->set_env('sauserprefs_sort', $sorts);

        // define input error class
        $this->rcube->output->set_env('sauserprefs_input_error_class', !empty($attrib['input_error_class']) ? $attrib['input_error_class'] : 'error');

        // output global prefs as default in env
        foreach ($this->global_prefs as $key => $val) {
            $this->rcube->output->set_env(str_replace(" ", "_", $key), $val);
        }

        unset($attrib['form']);

        list($form_start, $form_end) = rcmail_action::get_form_tags($attrib, 'plugin.sauserprefs.save', null,
            ['name' => '_section', 'value' => $this->cur_section]);

        $out = $form_start;

        $out .= $this->_prefs_block($this->cur_section, $attrib);

        return $out . $form_end;
    }

    public function prefs_section_name()
    {
        $data = $this->rcube->plugins->exec_hook('sauserprefs_section_name', ['section' => $this->cur_section, 'title' => $this->sections[$this->cur_section]['section']]);

        return $data['title'];
    }

    public function save()
    {
        $this->_init_storage();
        $this->_load_global_prefs();
        $this->_load_user_prefs();

        $new_prefs = [];
        $result = true;

        switch ($this->cur_section) {
            case 'general':
                if (!isset($this->no_override['required_score'])) {
                    $new_prefs['required_score'] = rcube_utils::get_input_string('_spamthres', rcube_utils::INPUT_POST) ?: $this->global_prefs['required_score'];
                }

                if (!isset($this->no_override['rewrite_header Subject'])) {
                    $new_prefs['rewrite_header Subject'] = rcube_utils::get_input_string('_spamsubject', rcube_utils::INPUT_POST);
                }

                if (!isset($this->no_override['ok_locales'])) {
                    $new_prefs['ok_locales'] = '';
                    if (is_array(rcube_utils::get_input_value('_spamlang', rcube_utils::INPUT_POST))) {
                        $input_locals = rcube_utils::get_input_value('_spamlang', rcube_utils::INPUT_POST);
                        $locales = array_intersect($input_locals, $this->sa_locales);
                        $new_prefs['ok_locales'] = implode(" ", $locales);
                    }
                }

                if (!isset($this->no_override['ok_languages'])) {
                    $new_prefs['ok_languages'] = is_array(rcube_utils::get_input_value('_spamlang', rcube_utils::INPUT_POST)) ? implode(" ", rcube_utils::get_input_value('_spamlang', rcube_utils::INPUT_POST)) : '';
                }

                break;
            case 'headers':
                if (!isset($this->no_override['fold_headers'])) {
                    $new_prefs['fold_headers'] = empty($_POST['_spamfoldheaders']) ? "0" : "1";
                }

                if (!isset($this->no_override['add_header all Level'])) {
                    $spamchar = empty($_POST['_spamlevelchar']) ? "*" : rcube_utils::get_input_string('_spamlevelchar', rcube_utils::INPUT_POST);
                    $spamchar = substr($spamchar, 0, 1); // input validation, make sure its only ever 1 char

                    if (rcube_utils::get_input_string('_spamlevelstars', rcube_utils::INPUT_POST) == "1") {
                        $new_prefs['add_header all Level'] = "_STARS(" . $spamchar . ")_";
                        $new_prefs['remove_header all'] = "0";
                    }
                    else {
                        $new_prefs['add_header all Level'] = "";
                        $new_prefs['remove_header all'] = "Level";
                    }
                }

                break;
            case 'tests':
                if (!isset($this->no_override['use_razor1'])) {
                    $new_prefs['use_razor1'] = empty($_POST['_spamuserazor1']) ? "0" : "1";
                }

                if (!isset($this->no_override['use_razor2'])) {
                    $new_prefs['use_razor2'] = empty($_POST['_spamuserazor2']) ? "0" : "1";
                }

                if (!isset($this->no_override['use_pyzor'])) {
                    $new_prefs['use_pyzor'] = empty($_POST['_spamusepyzor']) ? "0" : "1";
                }

                if (!isset($this->no_override['use_dcc'])) {
                    $new_prefs['use_dcc'] = empty($_POST['_spamusedcc']) ? "0" : "1";
                }

                if (!isset($this->no_override['skip_rbl_checks'])) {
                    $new_prefs['skip_rbl_checks'] = empty($_POST['_spamskiprblchecks']) ? "1" : "0";
                }

                break;
            case 'bayes':
                if (!isset($this->no_override['use_bayes'])) {
                    $new_prefs['use_bayes'] = empty($_POST['_spamusebayes']) ? "0" : "1";
                }

                if (!isset($this->no_override['bayes_auto_learn'])) {
                    $new_prefs['bayes_auto_learn'] = empty($_POST['_spambayesautolearn']) ? "0" : "1";
                }

                if (!isset($this->no_override['bayes_auto_learn_threshold_nonspam'])) {
                    $new_prefs['bayes_auto_learn_threshold_nonspam'] = rcube_utils::get_input_string('_bayesnonspam', rcube_utils::INPUT_POST) ?: $this->global_prefs['bayes_auto_learn_threshold_nonspam'];
                }

                if (!isset($this->no_override['bayes_auto_learn_threshold_spam'])) {
                    $new_prefs['bayes_auto_learn_threshold_spam'] = rcube_utils::get_input_string('_bayesspam', rcube_utils::INPUT_POST) ?: $this->global_prefs['bayes_auto_learn_threshold_spam'];
                }

                if (!isset($this->no_override['use_bayes_rules'])) {
                    $new_prefs['use_bayes_rules'] = empty($_POST['_spambayesrules']) ? "0" : "1";
                }

                break;
            case 'report':
                if (!isset($this->no_override['report_safe'])) {
                    $new_prefs['report_safe'] = rcube_utils::get_input_string('_spamreport', rcube_utils::INPUT_POST);
                }

                break;
            case 'addresses':
                $acts = rcube_utils::get_input_value('_address_rule_act', rcube_utils::INPUT_POST);
                $prefs = rcube_utils::get_input_value('_address_rule_field', rcube_utils::INPUT_POST);
                $vals = rcube_utils::get_input_value('_address_rule_value', rcube_utils::INPUT_POST);

                foreach ($acts as $idx => $act) {
                    switch ($prefs[$idx]) {
                        case "whitelist_from":
                        case "welcomelist_from":
                            $new_prefs['addresses'][] = ['field' => $prefs[$idx], 'value' => $vals[$idx], 'action' => $act];
                            break;
                        case "blacklist_from":
                        case "blocklist_from":
                            $new_prefs['addresses'][] = ['field' => $prefs[$idx], 'value' => $vals[$idx], 'action' => $act];
                            break;
                        case "whitelist_to":
                        case "welcomelist_to":
                            $new_prefs['addresses'][] = ['field' => $prefs[$idx], 'value' => $vals[$idx], 'action' => $act];
                            break;
                    }
                }

                if (!isset($this->no_override[$this->awl_plugin])) {
                    $new_prefs[$this->awl_plugin] = empty($_POST['_awl']) ? "0" : "1";
                }

                if (!isset($this->no_override['score USER_IN_BLOCKLIST'])) {
                    $new_prefs['score USER_IN_BLOCKLIST'] = rcube_utils::get_input_string('_score_user_blocklist', rcube_utils::INPUT_POST) ?: $this->global_prefs['score USER_IN_BLOCKLIST'];
                }

                if (!isset($this->no_override['score USER_IN_WELCOMELIST'])) {
                    $new_prefs['score USER_IN_WELCOMELIST'] = rcube_utils::get_input_string('_score_user_welcomelist', rcube_utils::INPUT_POST) ?: $this->global_prefs['score USER_IN_WELCOMELIST'];
                }

                break;
            case 'scores':
                foreach ($this->score_prefs as $test) {
                    $new_prefs['score ' . $test] = rcube_utils::get_input_string('_score_' . $test, rcube_utils::INPUT_POST) ?: $this->global_prefs['score ' . $test];
                }

                break;
        }

        // allow additional actions before prefs are saved
        $data = $this->rcube->plugins->exec_hook('sauserprefs_save', [
            'section' => $this->cur_section,
            'cur_prefs' => $this->user_prefs,
            'new_prefs' => $new_prefs,
            'global_prefs' => $this->global_prefs,
        ]);

        if (!$data['abort']) {
            // save prefs
            if ($this->_save($this->sa_user, $data['new_prefs'], $this->user_prefs, $this->global_prefs)) {
                $this->rcube->output->command('display_message', $this->gettext('sauserprefchanged'), 'confirmation');
            }
            else {
                $this->rcube->output->command('display_message', $this->gettext('sauserpreffailed'), 'error');
            }
        }
        else {
            $this->rcube->output->command('display_message', $data['message'] ?? $this->gettext('sauserpreffailed'), 'error');
        }

        // go to next step
        $this->rcube->overwrite_action('plugin.sauserprefs.edit');
        $this->_load_user_prefs();
        $this->init_html();
    }

    private function _save($user_id, $new_prefs, $cur_prefs, $global_prefs) {
        if (!self::$SAv4) {
            $names = array_flip(self::$SAv4_prefs);

            // process prefs names for different SA versions
            $translator = function($input) use ($names) {
                    $output = [];

                    foreach ($input as $key => $val) {
                        if ($key == 'addresses') {
                            $output[$key] = $val;
                            foreach ($output[$key] as &$address) {
                                if (array_key_exists($address['field'], $names)) {
                                    $address['field'] = $names[$address['field']];
                                }
                            }
                        }
                        elseif (array_key_exists($key, $names)) {
                            $output[$names[$key]] = $val;
                        }
                        else {
                            $output[$key] = $val;
                        }
                    }

                    return $output;
                };

            $new_prefs = is_array($new_prefs) ? $translator($new_prefs) : $new_prefs;
            $cur_prefs = is_array($cur_prefs) ? $translator($cur_prefs) : $cur_prefs;
            $global_prefs = is_array($global_prefs) ? $translator($global_prefs) : $global_prefs;
        }

        return $this->storage->save_prefs($user_id, $new_prefs, $cur_prefs, $global_prefs);
    }

    public function welcomelist_import()
    {
        $selected_sources = rcube_utils::get_input_value('_sources', rcube_utils::INPUT_POST);
        if (!is_array($selected_sources)) {
            return;
        }

        foreach ($this->addressbook_import as $aid) {
            if (in_array($aid, $selected_sources)) {
                $contacts = $this->rcube->get_address_book($aid);
                $contacts->set_page(1);
                $contacts->set_pagesize(99999);
                $result = $contacts->list_records(null, 0, true);

                if (empty($result) || $result->count == 0) {
                    return;
                }
                $records = $result->records;
                foreach ($records as $row_data) {
                    foreach ($this->_gen_email_arr($row_data) as $email) {
                        $this->rcube->output->command('sauserprefs_addressrule_import', $email, '', '');
                    }
                }

                $contacts->close();
            }
        }
    }

    public function purge_bayes()
    {
        $this->_init_storage();

        if (empty($this->bayes_query)) {
            $this->rcube->output->command('display_message', $this->gettext('servererror'), 'error');

            return;
        }

        if ($this->storage->purge_bayes($this->sa_user)) {
            $this->rcube->output->command('display_message', $this->gettext('done'), 'confirmation');
        }
        else {
            $this->rcube->output->command('display_message', $this->gettext('servererror'), 'error');
        }
    }

    public function contact_add($args)
    {
        if (in_array($args['source'], $this->addressbook_sync)) {
            $this->_init_storage();
            $this->_load_global_prefs();
            $this->_load_user_prefs();

            // list of existing email rules for existence check
            $existing_addresses = array_column($this->user_prefs['addresses'], 'value');

            $new_prefs = [];

            foreach ($this->_gen_email_arr($args['record']) as $email) {
                if (!in_array($email, $existing_addresses)) {
                    $new_prefs['addresses'][] = ['field' => 'welcomelist_from', 'value' => $email, 'action' => 'INSERT'];
                }
            }

            if (!empty($new_prefs)) {
                $this->_save($this->sa_user, $new_prefs, null, null);
            }
        }
    }

    public function contact_save($args)
    {
        $this->contact_delete($args);
        $this->contact_add($args);
    }

    public function contact_delete($args)
    {
        if (in_array($args['source'], $this->addressbook_sync)) {
            $this->_init_storage();
            $this->_load_global_prefs();
            $this->_load_user_prefs();

            // list of existing email rules for existence check
            $existing_addresses = array_column($this->user_prefs['addresses'], 'value');

            if (!is_array($args['id'])) {
                $args['id'] = [$args['id']];
            }

            $new_prefs = [];

            $contacts = $this->rcube->get_address_book($args['source']);
            foreach ($args['id'] as $id) {
                $emails = $this->_gen_email_arr($contacts->get_record($id, true));
                foreach ($emails as $email) {
                    if (in_array($email, $existing_addresses)) {
                        $new_prefs['addresses'][] = ['field' => 'welcomelist_from', 'value' => $email, 'action' => 'DELETE'];
                    }
                }
            }
            $contacts->close();

            if (!empty($new_prefs)) {
                $this->_save($this->sa_user, $new_prefs, null, null);
            }
        }
    }

    public function taskwatermark_show($p)
    {
        if ($p['action'] == 'plugin_sauserprefs') {
            $this->include_stylesheet($this->local_skin_path() . '/tabstyles.css');
            $p['hint'] = 'settingstip';

            return $p;
        }
    }

    private function _init_storage()
    {
        if (!$this->storage) {
            // Add include path for internal classes
            $include_path = $this->home . '/lib' . \PATH_SEPARATOR;
            $include_path .= ini_get('include_path');
            set_include_path($include_path);

            $class = $this->rcube->config->get('sauserprefs_storage', 'sql');
            $class = "rcube_sauserprefs_storage_" . $class;

            // try to instantiate class
            if (class_exists($class)) {
                $this->storage = new $class($this->rcube->config);
            }
            else {
                // no storage found, raise error
                rcube::raise_error(['code' => 604, 'type' => 'sauserprefs',
                    'line' => __LINE__, 'file' => __FILE__,
                    'message' => "Failed to find storage driver. Check sauserprefs_storage config option",
                ], true, true);
            }
        }
    }

    private function _load_global_prefs()
    {
        if (!empty($this->global_prefs)) {
            // prefs already loaded
            return;
        }

        $this->global_prefs = $this->_load_prefs($this->rcube->config->get('sauserprefs_global_userid', '\$GLOBAL'));

        $default_prefs = $this->rcube->config->get('sauserprefs_default_prefs', []);
        // replace deprecated prefs
        foreach (self::$deprecated_prefs as $old => $new) {
            if (array_key_exists($old, $default_prefs) && !array_key_exists($new, $default_prefs)) {
                $default_prefs[$new] = $default_prefs[$old];
                unset($default_prefs[$old]);
            }
        }
        $this->global_prefs = array_merge($default_prefs, $this->global_prefs);

        // extract score prefs
        foreach (array_keys($this->global_prefs) as $test) {
            if (!in_array($test, ['score USER_IN_BLOCKLIST', 'score USER_IN_WELCOMELIST']) && preg_match('/^score\s([A-Z0-9_]+)$/', $test, $matches)) {
                $this->score_prefs[] = $matches[1];
            }
        }

        // configure awl plugin
        if (array_key_exists('use_txrep', $this->global_prefs)) {
            $this->awl_plugin = 'use_txrep';
        }
    }

    private function _load_user_prefs()
    {
        $this->user_prefs = $this->_load_prefs($this->sa_user);
    }

    private function _load_prefs($user)
    {
        $prefs = $this->storage->load_prefs($user);

        // sort address rules
        if (isset($prefs['addresses']) && is_array($prefs['addresses'])) {
            usort($prefs['addresses'], [$this, 'sort_addresses']);
        }

        return $prefs;
    }

    private function _prefs_block($part, $attrib)
    {
        $blocks = [];

        switch ($part) {
            // General tests
            case 'general':
                $blocks = [
                    'main' => ['name' => rcmail::Q($this->gettext('mainoptions')), 'class' => 'generalprefstable', 'cols' => 2],
                    'langs' => ['name' => rcmail::Q($this->gettext('langoptions')), 'class' => 'langprefstable', 'cols' => 1],
                ];
                $blocks['langs']['intro'] = html::p(null, rcmail::Q($this->gettext('spamlangexp')));

                if (!isset($this->no_override['required_score'])) {
                    $field_id = 'rcmfd_spamthres';
                    $blocks['main']['options']['spamthres'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('spamthres'))),
                        'content' => $this->_score_select('_spamthres', $field_id, $this->user_prefs['required_score']),
                    ];

                    $blocks['main']['options']['spamthres_help'] = [
                        'row_attribs' => ['class' => 'sauphelp saupthres'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('spamthresexp')),
                    ];
                }

                if (!isset($this->no_override['rewrite_header Subject'])) {
                    $field_id = 'rcmfd_spamsubject';
                    $input_spamsubject = new html_inputfield(['name' => '_spamsubject', 'id' => $field_id, 'value' => $this->user_prefs['rewrite_header Subject']]);

                    $blocks['main']['options']['spamsubject'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('spamsubject'))),
                        'content' => $input_spamsubject->show(),
                    ];

                    $blocks['main']['options']['spamsubject_help'] = [
                        'row_attribs' => ['class' => 'sauphelp saupsubject'],
                        'title' => '&nbsp;',
                        'content' => rcmail::Q($this->gettext('spamsubjectblank')),
                    ];
                }

                if (!isset($this->no_override['ok_languages']) || !isset($this->no_override['ok_locales'])) {
                    $select_all = $this->rcube->output->button(['class' => 'select select-all', 'command' => 'plugin.sauserprefs.select_all_langs', 'type' => 'link', 'label' => 'all']);
                    $select_none = $this->rcube->output->button(['class' => 'select select-none', 'command' => 'plugin.sauserprefs.select_no_langs', 'type' => 'link', 'label' => 'none']);
                    $select_invert = $this->rcube->output->button(['class' => 'select select-invert', 'command' => 'plugin.sauserprefs.select_invert_langs', 'type' => 'link', 'label' => 'invert']);

                    $blocks['langs']['options']['header'] = [
                        'content_attribs' => ['id' => 'listcontrols'],
                        'content' => $this->gettext('select') . ":&nbsp;&nbsp;" . $select_all . "&nbsp;&nbsp;" . $select_invert . "&nbsp;&nbsp;" . $select_none,
                    ];

                    $lang_table = new html_table(['id' => 'spam-langs-table', 'class' => 'records-table listing sortable-table spam-langs-table fixedheader', 'cellspacing' => '0', 'cols' => 2]);
                    $lang_table->add_header('lang', $this->rcube->output->button(['command' => 'plugin.sauserprefs.table_sort', 'prop' => '#spam-langs-table', 'type' => 'link', 'label' => 'language', 'title' => 'sortby']));
                    $lang_table->add_header('tick', $this->rcube->output->button(['command' => 'plugin.sauserprefs.table_sort', 'prop' => '#spam-langs-table', 'type' => 'link', 'label' => 'sauserprefs.enabled', 'title' => 'sortby']));

                    if ($lang_config = $this->rcube->config->get('sauserprefs_langs_allowed')) {
                        $this->sa_langs = array_intersect($this->sa_langs, (array) $lang_config);
                    }
                    elseif ($lang_config = $this->rcube->config->get('sauserprefs_languages')) {
                        // backwards compatibility sauserprefs_languages removed 20180714
                        $this->sa_langs = array_intersect($this->sa_langs, array_keys((array) $lang_config));
                    }

                    $ok_locales = [];
                    $ok_languages = [];

                    if (!isset($this->no_override['ok_locales'])) {
                        if ($this->user_prefs['ok_locales'] == "all") {
                            $ok_locales = $this->sa_locales;
                        }
                        else {
                            $ok_locales = explode(" ", $this->user_prefs['ok_locales']);
                        }
                    }

                    if (!isset($this->no_override['ok_languages'])) {
                        if ($this->user_prefs['ok_languages'] == "all") {
                            $ok_languages = $this->sa_langs;
                        }
                        else {
                            $ok_languages = explode(" ", $this->user_prefs['ok_languages']);
                        }
                    }
                    else {
                        // only show langs from localess
                        $this->sa_langs = array_intersect($this->sa_langs, $this->sa_locales);
                    }

                    $locales_langs = array_merge($ok_locales, $ok_languages);
                    foreach ($this->sa_langs as $i => $lang_code) {
                        $button = '';
                        $checkbox_display = [];

                        if (isset($attrib['lang_list_buttons']) && $attrib['lang_list_buttons'] == '1') {
                            $button_type = in_array($lang_code, $locales_langs) ? 'enabled' : 'disabled';
                            $button = $this->rcube->output->button(['command' => 'plugin.sauserprefs.message_lang', 'prop' => $lang_code, 'type' => 'link', 'class' => 'lang-' . $button_type, 'id' => 'spam_lang_' . $i, 'title' => 'sauserprefs.' . $button_type, 'content' => ' ']);
                            $checkbox_display = ['style' => 'display: none;'];
                        }

                        if (!empty($attrib['lang_checkbox_class'])) {
                            $checkbox_display += ['class' => $attrib['lang_checkbox_class']];
                        }

                        $input_spamlang = new html_checkbox(['name' => '_spamlang[]', 'value' => $lang_code] + $checkbox_display);

                        $lang_table->add('lang', $this->gettext('lang_' . $lang_code));
                        $lang_table->add('tick', $button . $input_spamlang->show(in_array($lang_code, $locales_langs) ? $lang_code : ''));
                    }

                    $blocks['langs']['options']['langtable'] = [
                        'content_attribs' => ['class' => 'scroller'],
                        'content' => html::div(['id' => 'spam-langs-cont', 'class' => 'scroller'], $lang_table->show()),
                    ];
                }

                break;
            // Header settings
            case 'headers':
                $blocks = [
                    'main' => ['name' => rcmail::Q($this->gettext('mainoptions')), 'class' => 'headersprefstable', 'cols' => 2],
                ];
                $blocks['main']['intro'] = html::p(null, rcmail::Q($this->gettext('headersexp')));

                if (!isset($this->no_override['fold_headers'])) {
                    $field_id = 'rcmfd_spamfoldheaders';
                    $input_spamreport = new html_checkbox(['name' => '_spamfoldheaders', 'id' => $field_id, 'value' => '1']);

                    $blocks['main']['options']['spamfoldheaders'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('foldheaders'))),
                        'content' => $input_spamreport->show($this->user_prefs['fold_headers']) . $this->_help_button('fold_help'),
                    ];

                    $blocks['main']['options']['spamfoldheaders_help'] = [
                        'row_attribs' => ['id' => 'fold_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('foldhelp')),
                    ];
                }

                if (!isset($this->no_override['add_header all Level'])) {
                    if (!isset($this->user_prefs['remove_header all']) || $this->user_prefs['remove_header all'] != 'Level') {
                        $enabled = "1";
                        $char = $this->user_prefs['add_header all Level'];
                        $char = substr($char, 7, 1);
                    }
                    else {
                        $enabled = "0";
                        $char = "*";
                    }

                    $field_id = 'rcmfd_spamlevelstars';
                    $input_spamreport = new html_checkbox([
                        'name' => '_spamlevelstars',
                        'id' => $field_id,
                        'value' => '1',
                        'onchange' => rcmail_output::JS_OBJECT_NAME . '.sauserprefs_toggle_level_char(this)',
                    ]);

                    $blocks['main']['options']['spamlevelstars'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('spamlevelstars'))),
                        'content' => $input_spamreport->show($enabled) . $this->_help_button('level_help'),
                    ];

                    $blocks['main']['options']['spamlevelstars_help'] = [
                        'row_attribs' => ['id' => 'level_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('levelhelp')),
                    ];

                    $field_id = 'rcmfd_spamlevelchar';
                    $input_spamlevelchar = new html_inputfield([
                        'name' => '_spamlevelchar',
                        'id' => $field_id,
                        'value' => $char,
                        'maxlength' => '1',
                        'disabled' => $enabled ? 0 : 1,
                    ]);

                    $blocks['main']['options']['spamlevelchar'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('spamlevelchar'))),
                        'content' => $input_spamlevelchar->show(),
                    ];
                }

                break;
            // Test settings
            case 'tests':
                $blocks = [
                    'main' => ['name' => rcmail::Q($this->gettext('mainoptions')), 'class' => 'testsprefstable', 'cols' => 2],
                ];
                $blocks['main']['intro'] = html::p(null, rcmail::Q($this->gettext('spamtestssexp')));

                if (!isset($this->no_override['use_razor1'])) {
                    $field_id = 'rcmfd_spamuserazor1';
                    $input_spamtest = new html_checkbox(['name' => '_spamuserazor1', 'id' => $field_id, 'value' => '1']);

                    $blocks['main']['options']['spamuserazor1'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('userazor1'))),
                        'content' => $input_spamtest->show($this->user_prefs['use_razor1']) . $this->_help_button('raz1_help'),
                    ];

                    $blocks['main']['options']['spamuserazor1_help'] = [
                        'row_attribs' => ['id' => 'raz1_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('raz1help')),
                    ];
                }

                if (!isset($this->no_override['use_razor2'])) {
                    $field_id = 'rcmfd_spamuserazor2';
                    $input_spamtest = new html_checkbox(['name' => '_spamuserazor2', 'id' => $field_id, 'value' => '1']);

                    $blocks['main']['options']['spamuserazor2'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('userazor2'))),
                        'content' => $input_spamtest->show($this->user_prefs['use_razor2']) . $this->_help_button('raz2_help'),
                    ];

                    $blocks['main']['options']['spamuserazor2_help'] = [
                        'row_attribs' => ['id' => 'raz2_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('raz2help')),
                    ];
                }

                if (!isset($this->no_override['use_pyzor'])) {
                    $field_id = 'rcmfd_spamusepyzor';
                    $input_spamtest = new html_checkbox(['name' => '_spamusepyzor', 'id' => $field_id, 'value' => '1']);

                    $blocks['main']['options']['spamusepyzor'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('usepyzor'))),
                        'content' => $input_spamtest->show($this->user_prefs['use_pyzor']) . $this->_help_button('pyz_help'),
                    ];

                    $blocks['main']['options']['spamusepyzor_help'] = [
                        'row_attribs' => ['id' => 'pyz_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('pyzhelp')),
                    ];
                }

                if (!isset($this->no_override['use_dcc'])) {
                    $field_id = 'rcmfd_spamusedcc';
                    $input_spamtest = new html_checkbox(['name' => '_spamusedcc', 'id' => $field_id, 'value' => '1']);

                    $blocks['main']['options']['spamusedcc'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('usedcc'))),
                        'content' => $input_spamtest->show($this->user_prefs['use_dcc']) . $this->_help_button('dcc_help'),
                    ];

                    $blocks['main']['options']['spamusedcc_help'] = [
                        'row_attribs' => ['id' => 'dcc_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('dcchelp')),
                    ];
                }

                if (!isset($this->no_override['skip_rbl_checks'])) {
                    $field_id = 'rcmfd_spamskiprblchecks';
                    $enabled = $this->user_prefs['skip_rbl_checks'] == "1" ? "0" : "1";
                    $input_spamtest = new html_checkbox(['name' => '_spamskiprblchecks', 'id' => $field_id, 'value' => '1']);

                    $blocks['main']['options']['spamskiprblchecks'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('skiprblchecks'))),
                        'content' => $input_spamtest->show($enabled) . $this->_help_button('rbl_help'),
                    ];

                    $blocks['main']['options']['spamskiprblchecks_help'] = [
                        'row_attribs' => ['id' => 'rbl_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('rblhelp')),
                    ];
                }

                break;
            // Bayes settings
            case 'bayes':
                $blocks = [
                    'main' => ['name' => rcmail::Q($this->gettext('mainoptions')), 'class' => 'bayesprefstable', 'cols' => 2],
                    'autolearn' => ['name' => rcmail::Q($this->gettext('bayesautooptions')), 'class' => 'bayesprefstable', 'cols' => 2],
                ];

                if (!isset($this->no_override['use_bayes'])) {
                    $field_id = 'rcmfd_spamusebayes';
                    $input_spamtest = new html_checkbox([
                        'name' => '_spamusebayes',
                        'id' => $field_id,
                        'value' => '1',
                        'onchange' => rcmail_output::JS_OBJECT_NAME . '.sauserprefs_toggle_bayes(this)',
                    ]);

                    $blocks['main']['options']['spamusebayes'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('usebayes'))),
                        'content_attribs' => ['colspan' => 2],
                        'content' => $input_spamtest->show($this->user_prefs['use_bayes']) . $this->_help_button('bayes_help'),
                    ];

                    $blocks['main']['options']['spambayes_help'] = [
                        'row_attribs' => ['id' => 'bayes_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('bayeshelp')),
                    ];
                }

                if (!isset($this->no_override['use_bayes_rules'])) {
                    $field_id = 'rcmfd_spambayesrules';
                    $input_spamtest = new html_checkbox(['name' => '_spambayesrules', 'id' => $field_id, 'value' => '1', 'disabled' => $this->user_prefs['use_bayes'] ? 0 : 1]);

                    $blocks['main']['options']['spambayesrules'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('bayesrules'))),
                        'content' => $input_spamtest->show($this->user_prefs['use_bayes_rules']) . $this->_help_button('bayesrules_help'),
                    ];

                    $blocks['main']['options']['spambayesrules_help'] = [
                        'row_attribs' => ['id' => 'bayesrules_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('bayesruleshlp')),
                    ];
                }

                if (!isset($this->no_override['bayes_auto_learn'])) {
                    $field_id = 'rcmfd_spambayesautolearn';
                    $input_spamtest = new html_checkbox([
                        'name' => '_spambayesautolearn',
                        'id' => $field_id,
                        'value' => '1',
                        'onchange' => rcmail_output::JS_OBJECT_NAME . '.sauserprefs_toggle_bayes_auto(this)',
                        'disabled' => $this->user_prefs['use_bayes'] ? 0 : 1,
                    ]);

                    $blocks['main']['options']['spambayesautolearn'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('bayesautolearn'))),
                        'content' => $input_spamtest->show($this->user_prefs['bayes_auto_learn']) . $this->_help_button('bayesauto_help'),
                    ];

                    $blocks['main']['options']['spambayesautolearn_help'] = [
                        'row_attribs' => ['id' => 'bayesauto_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('bayesautohelp')),
                    ];
                }

                if (!empty($this->bayes_query)) {
                    $blocks['main']['options']['spamdelbayes'] = [
                        'content_attribs' => ['colspan' => 3, 'class' => 'bayesdelete'],
                        'content' => $this->rcube->output->button(['command' => 'plugin.sauserprefs.purge_bayes', 'class' => 'button mainaction delete', 'label' => 'sauserprefs.purgebayes', 'title' => 'sauserprefs.purgebayesexp']),
                    ];
                }

                if (!isset($this->no_override['bayes_auto_learn_threshold_nonspam'])) {
                    $field_id = 'rcmfd_bayesnonspam';
                    $args = ['disabled' => (!$this->user_prefs['bayes_auto_learn'] || !$this->user_prefs['use_bayes']) ? 1 : 0];
                    $blocks['autolearn']['options']['bayesnonspam'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('bayesnonspam'))),
                        'content' => $this->_score_select('_bayesnonspam', $field_id, $this->user_prefs['bayes_auto_learn_threshold_nonspam'], $args),
                    ];

                    $blocks['autolearn']['options']['bayesnonspam_help'] = [
                        'row_attribs' => ['class' => 'sauphelp saupbayesnonspam'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('bayesnonspamexp')),
                    ];
                }

                if (!isset($this->no_override['bayes_auto_learn_threshold_spam'])) {
                    $field_id = 'rcmfd_bayesspam';
                    $args = ['disabled' => (!$this->user_prefs['bayes_auto_learn'] || !$this->user_prefs['use_bayes']) ? 1 : 0];
                    $blocks['autolearn']['options']['bayesspam'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('bayesspam'))),
                        'content' => $this->_score_select('_bayesspam', $field_id, $this->user_prefs['bayes_auto_learn_threshold_spam'], $args),
                    ];

                    $blocks['autolearn']['options']['bayesspam_help'] = [
                        'row_attribs' => ['class' => 'sauphelp saupbayesspam'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('bayesspamexp')),
                    ];
                }

                break;
            // Report settings
            case 'report':
                $blocks = [
                    'main' => ['name' => rcmail::Q($this->gettext('mainoptions')), 'class' => 'reportprefstable', 'cols' => 2],
                ];

                if (!isset($this->no_override['report_safe'])) {
                    $field_id = 'rcmfd_spamreport';
                    $input_spamreport0 = new html_radiobutton(['name' => '_spamreport', 'id' => $field_id . '_0', 'value' => '0']);
                    $blocks['main']['options']['bayesspam0'] = [
                        'title' => html::label($field_id . '_0', rcmail::Q($this->gettext('spamreport0'))),
                        'content' => $input_spamreport0->show($this->user_prefs['report_safe']),
                    ];

                    $input_spamreport1 = new html_radiobutton(['name' => '_spamreport', 'id' => $field_id . '_1', 'value' => '1']);
                    $blocks['main']['options']['bayesspam1'] = [
                        'title' => html::label($field_id . '_1', rcmail::Q($this->gettext('spamreport1'))),
                        'content' => $input_spamreport1->show($this->user_prefs['report_safe']),
                    ];

                    $input_spamreport2 = new html_radiobutton(['name' => '_spamreport', 'id' => $field_id . '_2', 'value' => '2']);
                    $blocks['main']['options']['bayesspam2'] = [
                        'title' => html::label($field_id . '_2', rcmail::Q($this->gettext('spamreport2'))),
                        'content' => $input_spamreport2->show($this->user_prefs['report_safe']),
                    ];
                }

                break;
            // Address settings
            case 'addresses':
                $blocks = [
                    'main' => ['name' => rcmail::Q($this->gettext('mainoptions'))],
                    'advanced' => ['name' => rcmail::Q($this->gettext('advancedoptions')), 'class' => $attrib['class'] . ' addressadvancedtable', 'cols' => 2],
                ];

                $data = html::p(null, rcmail::Q($this->gettext('welcomelistexp')));

                if (!empty($this->addressbook_sync)) {
                    $data .= rcmail::Q(str_replace('%s', $this->_list_contact_sources($this->addressbook_sync), $this->gettext('autowelcomelist'))) . "<br /><br />";
                }

                $blocks['main']['intro'] = $data;

                $field_id = 'rcmfd_spamaddressrule';
                $input_spamaddressrule = new html_select(['name' => '_spamaddressrule', 'id' => $field_id]);
                $input_spamaddressrule->add($this->gettext('welcomelist_from'), 'welcomelist_from');
                $input_spamaddressrule->add($this->gettext('blocklist_from'), 'blocklist_from');
                $input_spamaddressrule->add($this->gettext('welcomelist_to'), 'welcomelist_to');

                $field_id = 'rcmfd_spamaddress';
                $input_spamaddress = new html_inputfield(['name' => '_spamaddress', 'id' => $field_id, 'title' => rcmail::Q($this->gettext('email')), 'placeholder' => rcmail::Q($this->gettext('email'))]);

                $field_id = 'rcmbtn_add_address';
                $button_addaddress = $this->rcube->output->button(['id' => $field_id, 'command' => 'plugin.sauserprefs.addressrule_add', 'type' => 'input', 'class' => 'button', 'label' => 'sauserprefs.addrule']);

                $blocks['main']['intro'] .= html::div('address-input grouped form-inline', $input_spamaddressrule->show() . $input_spamaddress->show() . $button_addaddress);

                $import = !empty($this->addressbook_import) ? $this->rcube->output->button(['class' => 'import', 'href' => '#', 'onclick' => 'return ' . rcmail_output::JS_OBJECT_NAME . '.sauserprefs_address_import_dialog();', 'type' => 'link', 'label' => 'sauserprefs.importaddresses', 'title' => 'sauserprefs.importfromaddressbook']) : '';
                $delete_all = $this->rcube->output->button(['class' => 'delete-all', 'command' => 'plugin.sauserprefs.welcomelist_delete_all', 'type' => 'link', 'label' => 'sauserprefs.deleteall', 'title' => 'sauserprefs.deletealltip']);

                $table = new html_table(['class' => $attrib['tbl_class'] . ' addressprefstable', 'cols' => 4]);
                $table->add(['colspan' => 4, 'id' => 'listcontrols'], $import . "&nbsp;&nbsp;" . $delete_all);

                $address_table = new html_table(['id' => 'address-rules-table', 'class' => 'records-table listing sortable-table address-rules-table fixedheader', 'cellspacing' => '0', 'cols' => 3]);
                $address_table->add_header('rule', $this->rcube->output->button(['command' => 'plugin.sauserprefs.table_sort', 'prop' => '#address-rules-table', 'type' => 'link', 'label' => 'sauserprefs.rule', 'title' => 'sortby']));
                $address_table->add_header('email', $this->rcube->output->button(['command' => 'plugin.sauserprefs.table_sort', 'prop' => '#address-rules-table', 'type' => 'link', 'label' => 'email', 'title' => 'sortby']));
                $address_table->add_header('control', '&nbsp;');

                if (isset($this->user_prefs['addresses'])) {
                    $this->rcube->output->set_env('address_rule_count', count($this->user_prefs['addresses']));
                    foreach ((array) $this->user_prefs['addresses'] as $address) {
                        $this->_address_row($address_table, $address['field'], $address['value'], $attrib);
                    }
                }
                else {
                    $this->rcube->output->set_env('address_rule_count', 0);
                }

                // add no address and new address rows at the end
                $norules = !empty($this->user_prefs['addresses']) ? 'display: none;': '';

                $address_table->set_row_attribs(['class' => 'noaddressrules', 'style' => $norules]);
                $address_table->add(['colspan' => '3'], rcube_utils::rep_specialchars_output($this->gettext('noaddressrules')));

                $this->_address_row($address_table, null, null, $attrib, ['class' => 'newaddressrule']);

                $table->add(['colspan' => 4, 'class' => 'scroller'], html::div(['id' => 'address-rules-cont', 'class' => 'scroller'], $address_table->show()));

                $blocks['main']['content'] = $table->show();

                if (!isset($this->no_override[$this->awl_plugin])) {
                    $field_id = 'rcmfd_awl';
                    $input_awl = new html_checkbox(['name' => '_awl', 'id' => $field_id, 'value' => '1']);
                    $blocks['advanced']['options']['awl'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('useawl'))),
                        'content' => $input_awl->show($this->user_prefs[$this->awl_plugin]) . $this->_help_button('awl_help'),
                    ];

                    $blocks['advanced']['options']['awl_help'] = [
                        'row_attribs' => ['id' => 'awl_help', 'style' => 'display: none;', 'class' => 'sauphelp'],
                        'content_attribs' => ['colspan' => 2],
                        'content' => rcmail::Q($this->gettext('useawlexp')),
                    ];
                }

                if (!isset($this->no_override['score USER_IN_BLOCKLIST'])) {
                    $field_id = 'rcmfd_score_user_blocklist';
                    $blocks['advanced']['options']['blocklist'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('score_blocklist'))),
                        'content' => $this->_score_select('_score_user_blocklist', $field_id, $this->user_prefs['score USER_IN_BLOCKLIST']),
                    ];
                }

                if (!isset($this->no_override['score USER_IN_WELCOMELIST'])) {
                    $field_id = 'rcmfd_score_user_welcomelist';
                    $blocks['advanced']['options']['welcomelist'] = [
                        'title' => html::label($field_id, rcmail::Q($this->gettext('score_welcomelist'))),
                        'content' => $this->_score_select('_score_user_welcomelist', $field_id, $this->user_prefs['score USER_IN_WELCOMELIST']),
                    ];
                }

                // import overlay
                if (!empty($this->addressbook_import)) {
                    $sources = $this->rcube->get_address_sources();
                    $sources_table = new html_table(['class' => $attrib['tbl_class'], 'cols' => 2]);
                    foreach ($this->addressbook_import as $id) {
                        if (array_key_exists($id, $sources)) {
                            $field_id = 'rcmfd_saupimport' . $id;
                            $input_source = new html_checkbox(['name' => '_source[]', 'id' => $field_id, 'value' => $id]);
                            $sources_table->add('title', html::label($field_id, rcmail::Q($sources[$id]['name'])));
                            $sources_table->add(null, $input_source->show());
                        }
                    }

                    // add overlay input box to html page
                    $this->rcube->output->add_footer(html::tag('div', [
                        'id' => 'saup_addressimport',
                        'style' => 'display: none;',
                    ], html::p(null, rcube::Q($this->gettext('importexp'))) . html::div('formcontent', $sources_table->show())));
                }

                break;
            // Test scores
            case 'scores':
                $blocks = [
                    'main' => ['name' => rcmail::Q($this->gettext('mainoptions'))],
                ];

                $score_table = new html_table(['id' => 'test-scores-table', 'class' => $attrib['tbl_class'] . ' test-scores-table', 'cols' => 2]);
                $score_table->add_header('test', rcmail::Q($this->gettext('test')));
                $score_table->add_header('score', rcmail::Q($this->gettext('score')));

                foreach ($this->score_prefs as $test) {
                    $field_id = 'rcmfd_score_' . $test;
                    $score_table->add('title', html::label($field_id, rcmail::Q($test)));
                    $score_table->add(null, $this->_score_select('_score_' . $test, $field_id, $this->user_prefs['score ' . $test]));
                }

                $blocks['main']['content'] = $score_table->show();

                break;
        }

        $data = $this->rcube->plugins->exec_hook('sauserprefs_list', ['section' => $part, 'blocks' => $blocks]);

        $out = '';
        foreach ($data['blocks'] as $class => $block) {
            $content = '';

            if (!empty($block['content'])) {
                $content = $block['content'];
            }

            if (!empty($block['options'])) {
                $table = new html_table(['class' => $attrib['class'] . ' ' . $block['class'], 'cols' => $block['cols']]);

                foreach ($block['options'] as $row) {
                    if (isset($row['row_attribs'])) {
                        $table->set_row_attribs($row['row_attribs']);
                    }

                    if (isset($row['title'])) {
                        $table->add('title', $row['title']);
                    }

                    $table->add($row['content_attribs'] ?? null, $row['content']);

                    if (isset($row['help'])) {
                        $table->add('help', $row['help']);
                    }
                }

                $content .= $table->show();
            }

            if (!empty($content)) {
                $out .= html::tag('fieldset', $class, html::tag('legend', null, $block['name']) . ($block['intro'] ?? '') . $content);
            }
        }

        return $out;
    }

    private function _address_row(&$address_table, $field, $value, $attrib, $row_attrib = [])
    {
        $hidden_action = new html_hiddenfield(['name' => '_address_rule_act[]', 'value' => '']);
        $hidden_field = new html_hiddenfield(['name' => '_address_rule_field[]', 'value' => $field]);
        $hidden_text = new html_hiddenfield(['name' => '_address_rule_value[]', 'value' => $value]);

        switch ($field) {
            case "whitelist_from":
            case "welcomelist_from":
                $fieldtxt = rcube_utils::rep_specialchars_output($this->gettext('welcomelist_from'));
                $class = 'welcomelist_from';
                break;
            case "blacklist_from":
            case "blocklist_from":
                $fieldtxt = rcube_utils::rep_specialchars_output($this->gettext('blocklist_from'));
                $class = 'blocklist_from';
                break;
            case "whitelist_to":
            case "welcomelist_to":
                $fieldtxt = rcube_utils::rep_specialchars_output($this->gettext('welcomelist_to'));
                $class = 'welcomelist_to';
                break;
            default:
                // for default/placeholder row
                $fieldtxt = '';
                $class = '';
        }

        $row_attrib = !isset($field) ? array_merge($row_attrib, ['style' => 'display: none;']) : array_merge($row_attrib, ['class' => $class]);
        $address_table->set_row_attribs($row_attrib);
        $address_table->add(['class' => 'rule'], $fieldtxt);
        $address_table->add(['class' => 'email'], $value);
        $del_button = $this->rcube->output->button(['command' => 'plugin.sauserprefs.addressrule_del', 'type' => 'link', 'class' => 'delete', 'label' => 'delete', 'content' => ' ', 'title' => 'delete']);
        $address_table->add('control', $del_button . $hidden_action->show() . $hidden_field->show() . $hidden_text->show());
    }

    private function _score_select($field_name, $field_id, $val, $args = [])
    {
        $locale_info = localeconv();

        if ($config = $this->rcube->config->get('sauserprefs_score_options')) {
            if (array_key_exists($field_name, $config)) {
                $config = $config[$field_name];
            }
            elseif ($field_name == '_score_user_blocklist' && array_key_exists('_score_user_blacklist', $config)) {
                // backwards compatibility, _score_user_blacklist removed in 1.19
                $config = $config['_score_user_blacklist'];
            }
            elseif ($field_name == '_score_user_welcomelist' && array_key_exists('_score_user_whitelist', $config)) {
                // backwards compatibility, _score_user_whitelist removed in 1.19
                $config = $config['_score_user_whitelist'];
            }
            elseif ($field_name == '_score_user_welcomelist' && array_key_exists('_score_user_allowlist', $config)) {
                // backwards compatibility, _score_user_allowlist removed in 1.20
                $config = $config['_score_user_allowlist'];
            }
            else {
                $config = $config['*'];
            }
        }
        else {
            $config = ['min' => 1, 'max' => 10, 'increment' => $this->rcube->config->get('sauserprefs_score_inc', 1)];
        }
        $decPlaces = self::decimal_places($config['increment'], $locale_info['decimal_point']);

        // calc values
        $vals = [];
        for ($i = $config['min']; $i <= $config['max']; $i += $config['increment']) {
            $vals[number_format($i, 5, '.', '')] = ['val' => number_format($i, $decPlaces, '.', ''), 'text' => number_format($i, $decPlaces, $locale_info['decimal_point'], '')];
        }
        if (isset($config['extra'])) {
            foreach ($config['extra'] as $extra) {
                $decPlaces = self::decimal_places($extra['increment'], $locale_info['decimal_point']);
                for ($i = $extra['min']; $i <= $extra['max']; $i += $extra['increment']) {
                    $vals[number_format($i, 5, '.', '')] = ['val' => number_format($i, $decPlaces, '.', ''), 'text' => number_format($i, $decPlaces, $locale_info['decimal_point'], '')];
                }
            }
        }
        // numerical order
        ksort($vals);

        $input_score = new html_select(['name' => $field_name, 'id' => $field_id] + $args);
        $input_score->add($this->gettext('defaultscore'), '');
        foreach ($vals as $opt) {
            $input_score->add($opt['text'], $opt['val']);
        }

        if (!array_key_exists(number_format($val, 5, '.', ''), $vals)) {
            $input_score->add(str_replace('%s', $val, $this->gettext('otherscore')), $val);
        }

        return $input_score->show($val);
    }

    public static function decimal_places($input, $separator = '.')
    {
        $places = 0;
        $input = (string) $input;

        if (strpos($input, $separator) !== false) {
            $places = strcspn(strrev($input), $separator);
        }

        return $places;
    }

    public static function map_pref_name($pref, $reverse = false)
    {
        if (!$reverse) {
            if (array_key_exists($pref, self::$deprecated_prefs)) {
                $pref = self::$deprecated_prefs[$pref];
            }
        }
        else {
            if (($orig_pref = array_search($pref, self::$deprecated_prefs)) != false) {
                $pref = $orig_pref;
            }
        }

        return $pref;
    }

    public static function sort_addresses($a, $b)
    {
        return strnatcasecmp($a["value"], $b["value"]);
    }

    private function _help_button($target)
    {
        $help_button = html::span(['class' => 'helpicon', 'title' => $this->gettext('moreinfo')], '');
        $help_button = html::a(['name' => '_headerhlp', 'href' => "#", 'onclick' => 'return ' . rcmail_output::JS_OBJECT_NAME . '.sauserprefs_help("' . $target . '");', 'title' => $this->gettext('help')], $help_button);

        return $help_button;
    }

    private function _gen_email_arr($contact)
    {
        $emails = [];

        if (!is_array($contact)) {
            return $emails;
        }
        foreach ($contact as $key => $value) {
            if (preg_match('/^email(:(.+))?$/i', $key, $matches)) {
                foreach ((array) $value as $subkey => $subval) {
                    if (!empty($subval)) {
                        $emails[] = $subval;
                    }
                }
            }
        }

        $emails = array_unique($emails);

        return $emails;
    }

    private function _list_contact_sources($ids)
    {
        $sources = $this->rcube->get_address_sources();
        $names = [];

        foreach ($ids as $id) {
            if (array_key_exists($id, $sources)) {
                $names[] = $sources[$id]['name'];
            }
        }

        return implode(', ', $names);
    }

    private function _load_host_config()
    {
        $configs = $this->rcube->config->get('sauserprefs_host_config');
        if (empty($configs) || !array_key_exists($_SESSION['storage_host'], (array) $configs)) {
            return;
        }

        $file = $configs[$_SESSION['storage_host']];
        $this->load_config($file);
    }
}
