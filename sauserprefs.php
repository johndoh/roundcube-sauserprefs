<?php

/**
 * SAUserPrefs
 *
 * Plugin to allow the user to manage their SpamAssassin settings using an SQL database
 *
 * @author Philip Weir
 *
 * Copyright (C) 2009-2015 Philip Weir
 *
 * This program is a Roundcube (http://www.roundcube.net) plugin.
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
 * along with Roundcube. If not, see http://www.gnu.org/licenses/.
 */
class sauserprefs extends rcube_plugin
{
	public $task = 'mail|addressbook|settings';
	public $allowed_prefs = array('sauserprefs_sort');
	private $storage;
	private $sections = array();
	private $cur_section;
	private $global_prefs;
	private $user_prefs;
	private $addressbook_import;
	private $addressbook_sync;
	private $sa_locales = array('en', 'ja', 'ko', 'ru', 'th', 'zh');
	private $sa_user;
	private $bayes_query;
	static $deprecated_prefs = array('required_hits' => 'required_score'); // old => new

	function init()
	{
		$rcmail = rcube::get_instance();
		$this->load_config();
		$this->sa_user = $rcmail->config->get('sauserprefs_userid', "%u");

		$identity_arr = $rcmail->user->get_identity();
		$identity = $identity_arr['email'];
		$this->sa_user = str_replace('%u', $_SESSION['username'], $this->sa_user);
		$this->sa_user = str_replace('%l', $rcmail->user->get_username('local'), $this->sa_user);
		$this->sa_user = str_replace('%d', $rcmail->user->get_username('domain'), $this->sa_user);
		$this->sa_user = str_replace('%i', $identity, $this->sa_user);

		// backwards compatibility sauserprefs_whitelist_abook_id and sauserprefs_whitelist_sync removed 20150117
		if ($rcmail->config->get('sauserprefs_whitelist_sync', false)) {
			$this->addressbook_sync = array($rcmail->config->get('sauserprefs_whitelist_abook_id'), 0);
			$this->addressbook_import = array($rcmail->config->get('sauserprefs_whitelist_abook_id'), 0);
		}

		$abook_sync = $rcmail->config->get('sauserprefs_abook_sync');
		if ($abook_sync === true) {
			$this->addressbook_sync = array(0);
		}
		elseif ($abook_sync !== false) {
			$this->addressbook_sync = !is_array($abook_sync) ? array($abook_sync) : $abook_sync;
		}

		$abook_import = $rcmail->config->get('sauserprefs_abook_import');
		if ($abook_import === true) {
			$this->addressbook_import = array(0);
		}
		elseif ($abook_import !== false) {
			$this->addressbook_import = !is_array($abook_import) ? array($abook_import) : $abook_import;
		}

		$this->bayes_query = $rcmail->config->get('sauserprefs_bayes_delete_query');
		// backwards compatibility sauserprefs_bayes_delete removed 20150117
		if ($rcmail->config->get('sauserprefs_bayes_delete') === false)
			$this->bayes_query = null;

		if ($rcmail->task == 'settings') {
			$this->add_texts('localization/');
			$this->include_stylesheet($this->local_skin_path() . '/tabstyles.css');

			$this->sections = array(
				'general' => array('id' => 'general', 'section' => $this->gettext('spamgeneralsettings')),
				'tests' => array('id' => 'tests', 'section' => $this->gettext('spamtests')),
				'bayes' => array('id' => 'bayes', 'section' => $this->gettext('bayes')),
				'headers' => array('id' => 'headers', 'section' => $this->gettext('headers')),
				'report' => array('id' => 'report', 'section' => $this->gettext('spamreportsettings')),
				'addresses' => array('id' => 'addresses', 'section' => $this->gettext('spamaddressrules')),
			);
			$this->cur_section = rcube_utils::get_input_value('_section', rcube_utils::INPUT_GPC);

			$this->add_hook('settings_actions', array($this, 'settings_tab'));
			$this->register_action('plugin.sauserprefs', array($this, 'init_html'));
			$this->register_action('plugin.sauserprefs.edit', array($this, 'init_html'));
			$this->register_action('plugin.sauserprefs.save', array($this, 'save'));
			$this->register_action('plugin.sauserprefs.whitelist_import', array($this, 'whitelist_import'));
			$this->register_action('plugin.sauserprefs.purge_bayes', array($this, 'purge_bayes'));

			if (strpos($rcmail->action, 'plugin.sauserprefs') === 0) {
				$this->include_script('sauserprefs.js');
			}
		}
		elseif (count($this->addressbook_sync) > 0) {
			$this->add_hook('contact_create', array($this, 'contact_add'));
			$this->add_hook('contact_update', array($this, 'contact_save'));
			$this->add_hook('contact_delete', array($this, 'contact_delete'));
		}
	}

	function settings_tab($p)
	{
		// add sauserprefs tab
		$p['actions'][] = array('action' => 'plugin.sauserprefs', 'class' => 'sauserprefs', 'label' => 'sauserprefs.sauserprefs', 'title' => 'sauserprefs.managespam', 'role' => 'button', 'aria-disabled' => 'false', 'tabindex' => '0');
		return $p;
	}

	function init_html()
	{
		$this->_init_storage();
		$this->_load_global_prefs();
		$this->_load_user_prefs();

		$this->api->output->set_pagetitle($this->gettext('sauserprefssettings'));

		if (rcube::get_instance()->action == 'plugin.sauserprefs.edit') {
			$this->api->output->include_script('list.js');
			$this->user_prefs = array_merge($this->global_prefs, $this->user_prefs);
			$this->api->output->add_handler('userprefs', array($this, 'gen_form'));
			$this->api->output->add_handler('sectionname', array($this, 'prefs_section_name'));
			$this->api->output->send('sauserprefs.settingsedit');
		}
		else {
			$this->api->output->add_handler('sasectionslist', array($this, 'section_list'));
			$this->api->output->add_handler('saprefsframe', array($this, 'preference_frame'));
			$this->api->output->send('sauserprefs.sauserprefs');
		}
	}

	function section_list($attrib)
	{
		$rcmail = rcube::get_instance();
		$no_override = array_flip(rcube::get_instance()->config->get('sauserprefs_dont_override'));

		// add id to message list table if not specified
		if (!strlen($attrib['id']))
			$attrib['id'] = 'rcmsectionslist';

		$sections = array();

		// if template overrides default array then rebuild the array in the new order
		if (isset($attrib['sections'])) {
			$new_sections = array();
			$keys = preg_split('/[\s,;]+/', str_replace(array("'", '"'), '', $attrib['sections']));
			foreach ($keys as $key) {
				$new_sections[] = $this->sections[$key];
			}
			$this->sections = $new_sections;
		}

		$data = rcube::get_instance()->plugins->exec_hook('sauserprefs_sections_list', array('list' => $this->sections, 'cols' => array('section')));

		foreach ($data['list'] as $id => $block) {
			if (!isset($no_override['{' . $id . '}']))
				$sections[$id] = $block;
		}

		// create XHTML table
		$out = $rcmail->table_output($attrib, $sections, $data['cols'], 'id');

		// set client env
		$this->api->output->add_gui_object('sectionslist', $attrib['id']);
		$this->api->output->include_script('list.js');

		return $out;
	}

	function preference_frame($attrib)
	{
		if (!$attrib['id'])
			$attrib['id'] = 'rcmprefsframe';

		return $this->api->output->frame($attrib, true);
	}

	function gen_form($attrib)
	{
		$this->api->output->add_label(
			'sauserprefs.spamaddressexists', 'sauserprefs.spamenteraddress',
			'sauserprefs.spamaddresserror', 'sauserprefs.spamaddressdelete',
			'sauserprefs.spamaddressdeleteall', 'sauserprefs.enabled', 'sauserprefs.disabled',
			'sauserprefs.importingaddresses', 'sauserprefs.usedefaultconfirm', 'sauserprefs.purgebayesconfirm',
			'sauserprefs.whitelist_from');

		// output table sorting prefs
		$sorts = rcube::get_instance()->config->get('sauserprefs_sort', array());
		if (!array_key_exists('#spam-langs-table', $sorts)) $sorts['#spam-langs-table'] = array(0, 'true');
		if (!array_key_exists('#address-rules-table', $sorts)) $sorts['#address-rules-table'] = array(1, 'true');
		$this->api->output->set_env('sauserprefs_sort', $sorts);

		// output global prefs as default in env
		foreach ($this->global_prefs as $key => $val)
			$this->api->output->set_env(str_replace(" ", "_", $key), $val);

		unset($attrib['form']);

		list($form_start, $form_end) = get_form_tags($attrib, 'plugin.sauserprefs.save', null,
			array('name' => '_section', 'value' => $this->cur_section));

		$out = $form_start;

		$out .= $this->_prefs_block($this->cur_section, $attrib);

		return $out . $form_end;
	}

	function prefs_section_name()
	{
		$data = rcube::get_instance()->plugins->exec_hook('sauserprefs_section_name', array('section' => $this->cur_section, 'title' => $this->sections[$this->cur_section]['section']));
		return $data['title'];
	}

	function save()
	{
		$rcmail = rcube::get_instance();
		$this->_init_storage();
		$this->_load_global_prefs();
		$this->_load_user_prefs();

		$no_override = array_flip($rcmail->config->get('sauserprefs_dont_override'));
		$new_prefs = array();
		$result = true;

		switch ($this->cur_section)
		{
			case 'general':
				if (!isset($no_override['required_score']))
					$new_prefs['required_score'] = rcube_utils::get_input_value('_spamthres', rcube_utils::INPUT_POST) ?: $this->global_prefs['required_score'];

				if (!isset($no_override['rewrite_header Subject']))
					$new_prefs['rewrite_header Subject'] = rcube_utils::get_input_value('_spamsubject', rcube_utils::INPUT_POST);

				if (!isset($no_override['ok_locales'])) {
					$new_prefs['ok_locales'] = '';
					if (is_array(rcube_utils::get_input_value('_spamlang', rcube_utils::INPUT_POST))) {
						$locales = array_intersect(rcube_utils::get_input_value('_spamlang', rcube_utils::INPUT_POST), $this->sa_locales);
						$new_prefs['ok_locales'] = implode(" ", $locales);
					}
				}

				if (!isset($no_override['ok_languages']))
					$new_prefs['ok_languages'] = is_array(rcube_utils::get_input_value('_spamlang', rcube_utils::INPUT_POST)) ? implode(" ", rcube_utils::get_input_value('_spamlang', rcube_utils::INPUT_POST)) : '';

				break;

			case 'headers':
				if (!isset($no_override['fold_headers']))
					$new_prefs['fold_headers'] = empty($_POST['_spamfoldheaders']) ? "0" : "1";

				if (!isset($no_override['add_header all Level'])) {
					$spamchar = empty($_POST['_spamlevelchar']) ? "*" : rcube_utils::get_input_value('_spamlevelchar', rcube_utils::INPUT_POST);

					if (rcube_utils::get_input_value('_spamlevelstars', rcube_utils::INPUT_POST) == "1") {
						$new_prefs['add_header all Level'] = "_STARS(". $spamchar .")_";
						$new_prefs['remove_header all'] = "0";
					}
					else {
						$new_prefs['add_header all Level'] = "";
						$new_prefs['remove_header all'] = "Level";
					}
				}

				break;

			case 'tests':
				if (!isset($no_override['use_razor1']))
					$new_prefs['use_razor1'] = empty($_POST['_spamuserazor1']) ? "0" : "1";

				if (!isset($no_override['use_razor2']))
					$new_prefs['use_razor2'] = empty($_POST['_spamuserazor2']) ? "0" : "1";

				if (!isset($no_override['use_pyzor']))
					$new_prefs['use_pyzor'] = empty($_POST['_spamusepyzor']) ? "0" : "1";

				if (!isset($no_override['use_dcc']))
					$new_prefs['use_dcc'] = empty($_POST['_spamusedcc']) ? "0" : "1";

				if (!isset($no_override['skip_rbl_checks']))
					$new_prefs['skip_rbl_checks'] = empty($_POST['_spamskiprblchecks']) ? "1" : "0";

				break;

			case 'bayes':
				if (!isset($no_override['use_bayes']))
					$new_prefs['use_bayes'] = empty($_POST['_spamusebayes']) ? "0" : "1";

				if (!isset($no_override['bayes_auto_learn']))
					$new_prefs['bayes_auto_learn'] = empty($_POST['_spambayesautolearn']) ? "0" : "1";

				if (!isset($no_override['bayes_auto_learn_threshold_nonspam']))
					$new_prefs['bayes_auto_learn_threshold_nonspam'] = rcube_utils::get_input_value('_bayesnonspam', rcube_utils::INPUT_POST) ?: $this->global_prefs['bayes_auto_learn_threshold_nonspam'];

				if (!isset($no_override['bayes_auto_learn_threshold_spam']))
					$new_prefs['bayes_auto_learn_threshold_spam'] = rcube_utils::get_input_value('_bayesspam', rcube_utils::INPUT_POST) ?: $this->global_prefs['bayes_auto_learn_threshold_spam'];

				if (!isset($no_override['use_bayes_rules']))
					$new_prefs['use_bayes_rules'] = empty($_POST['_spambayesrules']) ? "0" : "1";

				break;

			case 'report':
				if (!isset($no_override['report_safe']))
					$new_prefs['report_safe'] = rcube_utils::get_input_value('_spamreport', rcube_utils::INPUT_POST);

				break;

			case 'addresses':
				$acts = rcube_utils::get_input_value('_address_rule_act', rcube_utils::INPUT_POST);
				$prefs = rcube_utils::get_input_value('_address_rule_field', rcube_utils::INPUT_POST);
				$vals = rcube_utils::get_input_value('_address_rule_value', rcube_utils::INPUT_POST);

				foreach ($acts as $idx => $act)
					$new_prefs['addresses'][] = array('field' => $prefs[$idx], 'value' => $vals[$idx], 'action' => $act);

				break;
		}

		// allow additional actions before prefs are saved
		$data = $rcmail->plugins->exec_hook('sauserprefs_save', array(
			'section' => $this->cur_section, 'cur_prefs' => $this->user_prefs, 'new_prefs' => $new_prefs, 'global_prefs' => $this->global_prefs));

		if (!$data['abort']) {
			// save prefs
			if ($this->storage->save_prefs($this->sa_user, $data['new_prefs'], $this->user_prefs, $this->global_prefs))
				$this->api->output->command('display_message', $this->gettext('sauserprefchanged'), 'confirmation');
			else
				$this->api->output->command('display_message', $this->gettext('sauserpreffailed'), 'error');
		}
		else {
				$this->api->output->command('display_message', $data['message'] ? $data['message'] : $this->gettext('sauserpreffailed'), 'error');
		}

		// go to next step
		$rcmail->overwrite_action('plugin.sauserprefs.edit');
		$this->_load_user_prefs();
		$this->init_html();
	}

	function whitelist_import()
	{
		foreach ($this->addressbook_import as $aid) {
			$contacts = rcube::get_instance()->get_address_book($aid);
			$contacts->set_page(1);
			$contacts->set_pagesize(99999);
			$result = $contacts->list_records(null, 0, true);

			if (empty($result) || $result->count == 0)
				return;

			$records = $result->records;
			foreach ($records as $row_data) {
				foreach ($this->_gen_email_arr($row_data) as $email)
					$this->api->output->command('sauserprefs_addressrule_import', $email, '', '');
			}

			$contacts->close();
		}
	}

	function purge_bayes()
	{
		$rcmail = rcube::get_instance();
		$this->_init_storage();

		if (empty($this->bayes_query)) {
			$this->api->output->command('display_message', $this->gettext('servererror'), 'error');
			return;
		}

		if ($this->storage->purge_bayes($this->sa_user))
			$this->api->output->command('display_message', $this->gettext('done'), 'confirmation');
		else
			$this->api->output->command('display_message', $this->gettext('servererror'), 'error');
	}

	function contact_add($args)
	{
		if (in_array($args['source'], $this->addressbook_sync)) {
			$rcmail = rcube::get_instance();
			$this->_init_storage();

			$emails = $this->_gen_email_arr($args['record']);
			$this->storage->whitelist_add($this->sa_user, $emails);
		}
	}

	function contact_save($args)
	{
		$this->contact_delete($args);
		$this->contact_add($args);
	}

	function contact_delete($args)
	{
		if (in_array($args['source'], $this->addressbook_sync)) {
			$rcmail = rcube::get_instance();
			$this->_init_storage();

			if (!is_array($args['id']))
				$args['id'] = array($args['id']);

			$contacts = $rcmail->get_address_book($args['source']);
			foreach ($args['id'] as $id) {
				$emails = $this->_gen_email_arr($contacts->get_record($id, true));
				$this->storage->whitelist_delete($this->sa_user, $emails);
			}

			$contacts->close();
		}
	}

	private function _init_storage()
	{
		if (!$this->storage) {
			$rcmail = rcube::get_instance();

			// Add include path for internal classes
			$include_path = $this->home . '/lib' . PATH_SEPARATOR;
			$include_path .= ini_get('include_path');
			set_include_path($include_path);

			$class = $rcmail->config->get('sauserprefs_storage', 'sql');
			$class = "rcube_sauserprefs_storage_" . $class;

			// try to instantiate class
			if(class_exists($class)) {
				$this->storage = new $class($rcmail->config);
			}
			else {
				// no storage found, raise error
				rcube::raise_error(array('code' => 604, 'type' => 'sauserprefs',
					'line' => __LINE__, 'file' => __FILE__,
					'message' => "Failed to find storage driver. Check sauserprefs_storage config option"),
					true, true);
			}
		}
	}

	private function _load_global_prefs()
	{
		$rcmail = rcube::get_instance();
		$this->global_prefs = $this->_load_prefs($rcmail->config->get('sauserprefs_global_userid'));
		$this->global_prefs = array_merge($rcmail->config->get('sauserprefs_default_prefs'), $this->global_prefs);
	}

	private function _load_user_prefs()
	{
		$this->user_prefs = $this->_load_prefs($this->sa_user);
	}

	private function _load_prefs($user)
	{
		$rcmail = rcube::get_instance();
		$prefs = $this->storage->load_prefs($user);

		// sort address rules
		if (is_array($prefs['addresses']))
			usort($prefs['addresses'], array($this, 'sort_addresses'));

		return $prefs;
	}

	private function _prefs_block($part, $attrib)
	{
		$rcmail = rcube::get_instance();
		$no_override = array_flip($rcmail->config->get('sauserprefs_dont_override'));
		$locale_info = localeconv();
		$blocks = array();

		switch ($part)
		{
			// General tests
			case 'general':
				$blocks = array(
					'main' => array('name' => rcmail::Q($this->gettext('mainoptions')), 'class' => 'generalprefstable', 'cols' => 2),
					'langs' => array('name' => rcmail::Q($this->gettext('langoptions')), 'class' => 'langprefstable', 'cols' => 1)
				);
				$blocks['langs']['intro'] = html::p(null, rcmail::Q($this->gettext('spamlangexp')));

				if (!isset($no_override['required_score'])) {
					$field_id = 'rcmfd_spamthres';

					$input_spamthres = new html_select(array('name' => '_spamthres', 'id' => $field_id));
					$input_spamthres->add($this->gettext('defaultscore'), '');

					$decPlaces = 0;
					if ($rcmail->config->get('sauserprefs_score_inc') - (int)$rcmail->config->get('sauserprefs_score_inc') > 0)
						$decPlaces = strlen($rcmail->config->get('sauserprefs_score_inc') - (int)$rcmail->config->get('sauserprefs_score_inc')) - 2;

					$score_found = false;
					for ($i = 1; $i <= 10; $i = $i + $rcmail->config->get('sauserprefs_score_inc')) {
						$input_spamthres->add(number_format($i, $decPlaces, $locale_info['decimal_point'], ''), number_format($i, $decPlaces, '.', ''));

						if (!$score_found && $this->user_prefs['required_score'] && (float)$this->user_prefs['required_score'] == (float)$i)
							$score_found = true;
					}

					if (!$score_found && $this->user_prefs['required_score'])
						$input_spamthres->add(str_replace('%s', $this->user_prefs['required_score'], $this->gettext('otherscore')), (float)$this->user_prefs['required_score']);

					$blocks['main']['options']['spamthres'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('spamthres'))),
						'content' => $input_spamthres->show(number_format($this->user_prefs['required_score'], $decPlaces, '.', ''))
					);

					$blocks['main']['options']['spamthres_help'] = array(
						'content_attribs' => array('colspan' => 2),
						'content' => rcmail::Q($this->gettext('spamthresexp'))
					);
				}

				if (!isset($no_override['rewrite_header Subject'])) {
					$field_id = 'rcmfd_spamsubject';
					$input_spamsubject = new html_inputfield(array('name' => '_spamsubject', 'id' => $field_id, 'value' => $this->user_prefs['rewrite_header Subject'], 'style' => 'width:200px;'));

					$blocks['main']['options']['spamsubject'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('spamsubject'))),
						'content' => $input_spamsubject->show()
					);

					$blocks['main']['options']['spamsubject_help'] = array(
						'title' => '&nbsp;',
						'content' => rcmail::Q($this->gettext('spamsubjectblank'))
					);
				}

				if (!isset($no_override['ok_languages']) || !isset($no_override['ok_locales'])) {
					$select_all = $this->api->output->button(array('command' => 'plugin.sauserprefs.select_all_langs', 'type' => 'link', 'label' => 'all'));
					$select_none = $this->api->output->button(array('command' => 'plugin.sauserprefs.select_no_langs', 'type' => 'link', 'label' => 'none'));
					$select_invert = $this->api->output->button(array('command' => 'plugin.sauserprefs.select_invert_langs', 'type' => 'link', 'label' => 'invert'));

					$blocks['langs']['options']['header'] = array(
						'content_attribs' => array('id' => 'listcontrols'),
						'content' => $this->gettext('select') .":&nbsp;&nbsp;". $select_all ."&nbsp;&nbsp;". $select_invert ."&nbsp;&nbsp;". $select_none
					);

					$lang_table = new html_table(array('id' => 'spam-langs-table', 'class' => 'records-table spam-langs-table fixedheader', 'cellspacing' => '0', 'cols' => 2));
					$lang_table->add_header('lang', $this->api->output->button(array('command' => 'plugin.sauserprefs.table_sort', 'prop' => '#spam-langs-table', 'type' => 'link', 'label' => 'language', 'title' => 'sortby')));
					$lang_table->add_header('tick', $this->api->output->button(array('command' => 'plugin.sauserprefs.table_sort', 'prop' => '#spam-langs-table', 'type' => 'link', 'label' => 'sauserprefs.enabled', 'title' => 'sortby')));

					if (!isset($no_override['ok_locales'])) {
						if ($this->user_prefs['ok_locales'] == "all")
							$ok_locales = $this->sa_locales;
						else
							$ok_locales = explode(" ", $this->user_prefs['ok_locales']);
					}
					else {
						$ok_locales = array();
					}

					if (!isset($no_override['ok_languages'])) {
						if ($this->user_prefs['ok_languages'] == "all")
							$ok_languages = array_keys($rcmail->config->get('sauserprefs_languages'));
						else
							$ok_languages = explode(" ", $this->user_prefs['ok_languages']);
					}
					else {
						$tmp_array = $rcmail->config->get('sauserprefs_languages');
						$rcmail->config->set('sauserprefs_languages', array_intersect_key($tmp_array, array_flip($this->sa_locales)));
						$ok_languages = array();
					}

					$i = 0;
					$locales_langs = array_merge($ok_locales, $ok_languages);
					foreach ($rcmail->config->get('sauserprefs_languages') as $lang_code => $name) {
						if (in_array($lang_code, $locales_langs))
							$button = $this->api->output->button(array('command' => 'plugin.sauserprefs.message_lang', 'prop' => $lang_code, 'type' => 'link', 'class' => 'enabled', 'id' => 'spam_lang_' . $i, 'title' => 'sauserprefs.enabled', 'content' => ' '));
						else
							$button = $this->api->output->button(array('command' => 'plugin.sauserprefs.message_lang', 'prop' => $lang_code, 'type' => 'link', 'class' => 'disabled', 'id' => 'spam_lang_' . $i, 'title' => 'sauserprefs.disabled', 'content' => ' '));

						$input_spamlang = new html_checkbox(array('style' => 'display: none;', 'name' => '_spamlang[]', 'value' => $lang_code));

						$lang_table->add('lang', $name);
						$lang_table->add('tick', $button . $input_spamlang->show(in_array($lang_code, $locales_langs) ? $lang_code : ''));

						$i++;
					}

					$blocks['langs']['options']['langtable'] = array(
						'content_attribs' => array('class' => 'scroller'),
						'content' => html::div(array('id' => 'spam-langs-cont'), $lang_table->show())
					);
				}

				break;

			// Header settings
			case 'headers':
				$blocks = array(
					'main' => array('name' => rcmail::Q($this->gettext('mainoptions')), 'class' => 'headersprefstable', 'cols' => 3)
				);
				$blocks['main']['intro'] = html::p(null, rcmail::Q($this->gettext('headersexp')));

				if (!isset($no_override['fold_headers'])) {
					$help_button = html::span(array('class' => 'helpicon', 'title' => $this->gettext('moreinfo')));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("fold_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamfoldheaders';
					$input_spamreport = new html_checkbox(array('name' => '_spamfoldheaders', 'id' => $field_id, 'value' => '1'));

					$blocks['main']['options']['spamfoldheaders'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('foldheaders'))),
						'content' => $input_spamreport->show($this->user_prefs['fold_headers']),
						'help' => $help_button
					);

					$blocks['main']['options']['spamfoldheaders_help'] = array(
						'row_attribs' => array('id' => 'fold_help', 'style' => 'display: none;'),
						'content_attribs' => array('colspan' => 3),
						'content' => rcmail::Q($this->gettext('foldhelp'))
					);
				}

				if (!isset($no_override['add_header all Level'])) {
					$help_button = html::span(array('class' => 'helpicon', 'title' => $this->gettext('moreinfo')));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("level_help");', 'title' => $this->gettext('help')), $help_button);

					if ($this->user_prefs['remove_header all'] != 'Level') {
						$enabled = "1";
						$char = $this->user_prefs['add_header all Level'];
						$char = substr($char, 7, 1);
					}
					else {
						$enabled = "0";
						$char = "*";
					}

					$field_id = 'rcmfd_spamlevelstars';
					$input_spamreport = new html_checkbox(array('name' => '_spamlevelstars', 'id' => $field_id, 'value' => '1',
						'onchange' => rcmail_output::JS_OBJECT_NAME . '.sauserprefs_toggle_level_char(this)'));

					$blocks['main']['options']['spamlevelstars'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('spamlevelstars'))),
						'content' => $input_spamreport->show($enabled),
						'help' => $help_button
					);

					$blocks['main']['options']['spamlevelstars_help'] = array(
						'row_attribs' => array('id' => 'level_help', 'style' => 'display: none;'),
						'content_attribs' => array('colspan' => 3),
						'content' => rcmail::Q($this->gettext('levelhelp'))
					);

					$field_id = 'rcmfd_spamlevelchar';
					$input_spamsubject = new html_inputfield(array('name' => '_spamlevelchar', 'id' => $field_id, 'value' => $char,
						'style' => 'width:20px;', 'disabled' => $enabled?0:1));

					$blocks['main']['options']['spamlevelchar'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('spamlevelchar'))),
						'content' => $input_spamsubject->show(),
						'help' => '&nbsp;'
					);
				}

				break;

			// Test settings
			case 'tests':
				$blocks = array(
					'main' => array('name' => rcmail::Q($this->gettext('mainoptions')), 'class' => 'testsprefstable', 'cols' => 3)
				);
				$blocks['main']['intro'] = html::p(null, rcmail::Q($this->gettext('spamtestssexp')));

				if (!isset($no_override['use_razor1'])) {
					$help_button = html::span(array('class' => 'helpicon', 'title' => $this->gettext('moreinfo')));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("raz1_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamuserazor1';
					$input_spamtest = new html_checkbox(array('name' => '_spamuserazor1', 'id' => $field_id, 'value' => '1'));

					$blocks['main']['options']['spamuserazor1'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('userazor1'))),
						'content' => $input_spamtest->show($this->user_prefs['use_razor1']),
						'help' => $help_button
					);

					$blocks['main']['options']['spamuserazor1_help'] = array(
						'row_attribs' => array('id' => 'raz1_help', 'style' => 'display: none;'),
						'content_attribs' => array('colspan' => 3),
						'content' => rcmail::Q($this->gettext('raz1help'))
					);
				}

				if (!isset($no_override['use_razor2'])) {
					$help_button = html::span(array('class' => 'helpicon', 'title' => $this->gettext('moreinfo')));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("raz2_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamuserazor2';
					$input_spamtest = new html_checkbox(array('name' => '_spamuserazor2', 'id' => $field_id, 'value' => '1'));

					$blocks['main']['options']['spamuserazor2'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('userazor2'))),
						'content' => $input_spamtest->show($this->user_prefs['use_razor2']),
						'help' => $help_button
					);

					$blocks['main']['options']['spamuserazor2_help'] = array(
						'row_attribs' => array('id' => 'raz2_help', 'style' => 'display: none;'),
						'content_attribs' => array('colspan' => 3),
						'content' => rcmail::Q($this->gettext('raz2help'))
					);
				}

				if (!isset($no_override['use_pyzor'])) {
					$help_button = html::span(array('class' => 'helpicon', 'title' => $this->gettext('moreinfo')));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("pyz_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamusepyzor';
					$input_spamtest = new html_checkbox(array('name' => '_spamusepyzor', 'id' => $field_id, 'value' => '1'));

					$blocks['main']['options']['spamusepyzor'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('usepyzor'))),
						'content' => $input_spamtest->show($this->user_prefs['use_pyzor']),
						'help' => $help_button
					);

					$blocks['main']['options']['spamusepyzor_help'] = array(
						'row_attribs' => array('id' => 'pyz_help', 'style' => 'display: none;'),
						'content_attribs' => array('colspan' => 3),
						'content' => rcmail::Q($this->gettext('pyzhelp'))
					);
				}

				if (!isset($no_override['use_dcc'])) {
					$help_button = html::span(array('class' => 'helpicon', 'title' => $this->gettext('moreinfo')));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("dcc_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamusedcc';
					$input_spamtest = new html_checkbox(array('name' => '_spamusedcc', 'id' => $field_id, 'value' => '1'));

					$blocks['main']['options']['spamusedcc'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('usedcc'))),
						'content' => $input_spamtest->show($this->user_prefs['use_dcc']),
						'help' => $help_button
					);

					$blocks['main']['options']['spamusedcc_help'] = array(
						'row_attribs' => array('id' => 'dcc_help', 'style' => 'display: none;'),
						'content_attribs' => array('colspan' => 3),
						'content' => rcmail::Q($this->gettext('dcchelp'))
					);
				}

				if (!isset($no_override['skip_rbl_checks'])) {
					$help_button = html::span(array('class' => 'helpicon', 'title' => $this->gettext('moreinfo')));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("rbl_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamskiprblchecks';
					$enabled = $this->user_prefs['skip_rbl_checks'] == "1" ? "0" : "1";
					$input_spamtest = new html_checkbox(array('name' => '_spamskiprblchecks', 'id' => $field_id, 'value' => '1'));

					$blocks['main']['options']['spamskiprblchecks'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('skiprblchecks'))),
						'content' => $input_spamtest->show($enabled),
						'help' => $help_button
					);

					$blocks['main']['options']['spamskiprblchecks_help'] = array(
						'row_attribs' => array('id' => 'rbl_help', 'style' => 'display: none;'),
						'content_attribs' => array('colspan' => 3),
						'content' => rcmail::Q($this->gettext('rblhelp'))
					);
				}

				break;

			// Bayes settings
			case 'bayes':
				$blocks = array(
					'main' => array('name' => rcmail::Q($this->gettext('mainoptions')), 'class' => 'bayesprefstable', 'cols' => 3),
					'autolearn' => array('name' => rcmail::Q($this->gettext('bayesautooptions')), 'class' => 'bayesprefstable', 'cols' => 2)
				);

				if (!isset($no_override['use_bayes'])) {
					$help_button = html::span(array('class' => 'helpicon', 'title' => $this->gettext('moreinfo')));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("bayes_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamusebayes';
					$input_spamtest = new html_checkbox(array('name' => '_spamusebayes', 'id' => $field_id, 'value' => '1',
						'onchange' => rcmail_output::JS_OBJECT_NAME . '.sauserprefs_toggle_bayes(this)'));

					if (!empty($this->bayes_query))
						$delete_link = "&nbsp;&nbsp;&nbsp;" . html::span(array('id' => 'listcontrols'), $this->api->output->button(array('command' => 'plugin.sauserprefs.purge_bayes', 'type' => 'link', 'label' => 'sauserprefs.purgebayes', 'title' => 'sauserprefs.purgebayesexp')));

					$blocks['main']['options']['spamusebayes'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('usebayes'))),
						'content_attribs' => array('colspan' => 2),
						'content' => $input_spamtest->show($this->user_prefs['use_bayes']) . $delete_link,
					);
				}

				if (!isset($no_override['use_bayes_rules'])) {
					$help_button = html::span(array('class' => 'helpicon', 'title' => $this->gettext('moreinfo')));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("bayesrules_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spambayesrules';
					$input_spamtest = new html_checkbox(array('name' => '_spambayesrules', 'id' => $field_id, 'value' => '1', 'disabled' => $this->user_prefs['use_bayes']?0:1));

					$blocks['main']['options']['spambayesrules'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('bayesrules'))),
						'content' => $input_spamtest->show($this->user_prefs['use_bayes_rules']),
						'help' => $help_button
					);

					$blocks['main']['options']['spambayesrules_help'] = array(
						'row_attribs' => array('id' => 'bayesrules_help', 'style' => 'display: none;'),
						'content_attribs' => array('colspan' => 3),
						'content' => rcmail::Q($this->gettext('bayesruleshlp'))
					);
				}

				if (!isset($no_override['bayes_auto_learn'])) {
					$help_button = html::span(array('class' => 'helpicon', 'title' => $this->gettext('moreinfo')));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("bayesauto_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spambayesautolearn';
					$input_spamtest = new html_checkbox(array('name' => '_spambayesautolearn', 'id' => $field_id, 'value' => '1',
						'onchange' => rcmail_output::JS_OBJECT_NAME . '.sauserprefs_toggle_bayes_auto(this)', 'disabled' => $this->user_prefs['use_bayes']?0:1));

					$blocks['main']['options']['spambayesautolearn'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('bayesautolearn'))),
						'content' => $input_spamtest->show($this->user_prefs['bayes_auto_learn']),
						'help' => $help_button
					);

					$blocks['main']['options']['spambayesautolearn_help'] = array(
						'row_attribs' => array('id' => 'bayesauto_help', 'style' => 'display: none;'),
						'content_attribs' => array('colspan' => 3),
						'content' => rcmail::Q($this->gettext('bayesautohelp'))
					);
				}

				if (!isset($no_override['bayes_auto_learn_threshold_nonspam'])) {
					$field_id = 'rcmfd_bayesnonspam';
					$input_bayesnthres = new html_select(array('name' => '_bayesnonspam', 'id' => $field_id, 'disabled' => (!$this->user_prefs['bayes_auto_learn'] || !$this->user_prefs['use_bayes'])?1:0));
					$input_bayesnthres->add($this->gettext('defaultscore'), '');

					$decPlaces = 1;
					//if ($rcmail->config->get('sauserprefs_score_inc') - (int)$rcmail->config->get('sauserprefs_score_inc') > 0)
					//	$decPlaces = strlen($rcmail->config->get('sauserprefs_score_inc') - (int)$rcmail->config->get('sauserprefs_score_inc')) - 2;

					$score_found = false;
					for ($i = -1; $i <= 1; $i = $i + 0.1) {
						$input_bayesnthres->add(number_format($i, $decPlaces, $locale_info['decimal_point'], ''), number_format($i, $decPlaces, '.', ''));

						if (!$score_found && $this->user_prefs['bayes_auto_learn_threshold_nonspam'] && (float)$this->user_prefs['bayes_auto_learn_threshold_nonspam'] == (float)$i)
							$score_found = true;
					}

					if (!$score_found && $this->user_prefs['bayes_auto_learn_threshold_nonspam'])
						$input_bayesnthres->add(str_replace('%s', $this->user_prefs['bayes_auto_learn_threshold_nonspam'], $this->gettext('otherscore')), (float)$this->user_prefs['bayes_auto_learn_threshold_nonspam']);

					$blocks['autolearn']['options']['bayesnonspam'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('bayesnonspam'))),
						'content' => $input_bayesnthres->show(number_format($this->user_prefs['bayes_auto_learn_threshold_nonspam'], $decPlaces, '.', ''))
					);

					$blocks['autolearn']['options']['bayesnonspam_help'] = array(
						'content_attribs' => array('colspan' => 2),
						'content' => rcmail::Q($this->gettext('bayesnonspamexp'))
					);
				}

				if (!isset($no_override['bayes_auto_learn_threshold_spam'])) {
					$field_id = 'rcmfd_bayesspam';
					$input_bayesthres = new html_select(array('name' => '_bayesspam', 'id' => $field_id, 'disabled' => (!$this->user_prefs['bayes_auto_learn'] || !$this->user_prefs['use_bayes'])?1:0));
					$input_bayesthres->add($this->gettext('defaultscore'), '');

					$decPlaces = 0;
					if ($rcmail->config->get('sauserprefs_score_inc') - (int)$rcmail->config->get('sauserprefs_score_inc') > 0)
						$decPlaces = strlen($rcmail->config->get('sauserprefs_score_inc') - (int)$rcmail->config->get('sauserprefs_score_inc')) - 2;

					$score_found = false;
					for ($i = 1; $i <= 20; $i = $i + $rcmail->config->get('sauserprefs_score_inc')) {
						$input_bayesthres->add(number_format($i, $decPlaces, $locale_info['decimal_point'], ''), number_format($i, $decPlaces, '.', ''));

						if (!$score_found && $this->user_prefs['bayes_auto_learn_threshold_spam'] && (float)$this->user_prefs['bayes_auto_learn_threshold_spam'] == (float)$i)
							$score_found = true;
					}

					if (!$score_found && $this->user_prefs['required_score'])
						$input_bayesthres->add(str_replace('%s', $this->user_prefs['bayes_auto_learn_threshold_spam'], $this->gettext('otherscore')), (float)$this->user_prefs['bayes_auto_learn_threshold_spam']);

					$blocks['autolearn']['options']['bayesspam'] = array(
						'title' => html::label($field_id, rcmail::Q($this->gettext('bayesspam'))),
						'content' => $input_bayesthres->show(number_format($this->user_prefs['bayes_auto_learn_threshold_spam'], $decPlaces, '.', ''))
					);

					$blocks['autolearn']['options']['bayesspam_help'] = array(
						'content_attribs' => array('colspan' => 2),
						'content' => rcmail::Q($this->gettext('bayesspamexp'))
					);
				}

				break;

			// Report settings
			case 'report':
				$blocks = array(
					'main' => array('name' => rcmail::Q($this->gettext('mainoptions')), 'class' => 'reportprefstable', 'cols' => 2)
				);

				if (!isset($no_override['report_safe'])) {
					$field_id = 'rcmfd_spamreport';
					$input_spamreport0 = new html_radiobutton(array('name' => '_spamreport', 'id' => $field_id.'_0', 'value' => '0'));
					$blocks['main']['options']['bayesspam0'] = array(
						'title' => html::label($field_id.'_0', rcmail::Q($this->gettext('spamreport0'))),
						'content' => $input_spamreport0->show($this->user_prefs['report_safe'])
					);

					$input_spamreport1 = new html_radiobutton(array('name' => '_spamreport', 'id' => $field_id.'_1', 'value' => '1'));
					$blocks['main']['options']['bayesspam1'] = array(
						'title' => html::label($field_id.'_1', rcmail::Q($this->gettext('spamreport1'))),
						'content' => $input_spamreport1->show($this->user_prefs['report_safe'])
					);

					$input_spamreport2 = new html_radiobutton(array('name' => '_spamreport', 'id' => $field_id.'_2', 'value' => '2'));
						$blocks['main']['options']['bayesspam2'] = array(
					'title' => html::label($field_id.'_2', rcmail::Q($this->gettext('spamreport2'))),
						'content' => $input_spamreport2->show($this->user_prefs['report_safe'])
					);
				}

				break;

			// Address settings
			case 'addresses':
				$blocks = array(
					'main' => array('name' => rcmail::Q($this->gettext('mainoptions')))
				);

				$data = html::p(null, rcmail::Q($this->gettext('whitelistexp')));

				if (count($this->addressbook_sync) > 0)
					$data .= rcmail::Q($this->gettext('autowhitelist')) . "<br /><br />";

				$blocks['main']['intro'] = $data;

				$table = new html_table(array('class' => 'addressprefstable', 'cols' => 4));

				$field_id = 'rcmfd_spamaddressrule';
				$input_spamaddressrule = new html_select(array('name' => '_spamaddressrule', 'id' => $field_id));
				$input_spamaddressrule->add($this->gettext('whitelist_from'),'whitelist_from');
				$input_spamaddressrule->add($this->gettext('blacklist_from'), 'blacklist_from');
				$input_spamaddressrule->add($this->gettext('whitelist_to'), 'whitelist_to');

				$field_id = 'rcmfd_spamaddress';
				$input_spamaddress = new html_inputfield(array('name' => '_spamaddress', 'id' => $field_id, 'style' => 'width:200px;'));

				$field_id = 'rcmbtn_add_address';
				$button_addaddress = $this->api->output->button(array('command' => 'plugin.sauserprefs.addressrule_add', 'type' => 'input', 'class' => 'button', 'label' => 'sauserprefs.addrule'));

				$table->add('ruletype', $input_spamaddressrule->show());
				$table->add('address', $input_spamaddress->show());
				$table->add('action', $button_addaddress);
				$table->add(null, "&nbsp;");

				$import = count($this->addressbook_import) > 0 ? $this->api->output->button(array('command' => 'plugin.sauserprefs.import_whitelist', 'type' => 'link', 'label' => 'import', 'title' => 'sauserprefs.importfromaddressbook')) : '';
				$delete_all = $this->api->output->button(array('command' => 'plugin.sauserprefs.whitelist_delete_all', 'type' => 'link', 'label' => 'sauserprefs.deleteall'));

				$table->add(array('colspan' => 4, 'id' => 'listcontrols'), $import ."&nbsp;&nbsp;". $delete_all);

				$address_table = new html_table(array('id' => 'address-rules-table', 'class' => 'records-table address-rules-table fixedheader', 'cellspacing' => '0', 'cols' => 3));
				$address_table->add_header('rule', $this->api->output->button(array('command' => 'plugin.sauserprefs.table_sort', 'prop' => '#address-rules-table', 'type' => 'link', 'label' => 'sauserprefs.rule', 'title' => 'sortby')));
				$address_table->add_header('email', $this->api->output->button(array('command' => 'plugin.sauserprefs.table_sort', 'prop' => '#address-rules-table', 'type' => 'link', 'label' => 'email', 'title' => 'sortby')));
				$address_table->add_header('control', '&nbsp;');

				$this->_address_row($address_table, null, null, $attrib);

				if (count($this->user_prefs['addresses']) > 0)
					$norules = 'display: none;';

				$address_table->set_row_attribs(array('style' => $norules));
				$address_table->add(array('colspan' => '3'), rcube_utils::rep_specialchars_output($this->gettext('noaddressrules')));

				$this->api->output->set_env('address_rule_count', count($this->user_prefs['addresses']));
				foreach ((array)$this->user_prefs['addresses'] as $address)
					$this->_address_row($address_table, $address['field'], $address['value'], $attrib);

				$table->add(array('colspan' => 4, 'class' => 'scroller'), html::div(array('id' => 'address-rules-cont'), $address_table->show()));

				$blocks['main']['content'] = $table->show();

				break;
		}

		$data = $rcmail->plugins->exec_hook('sauserprefs_list', array('section' => $part, 'blocks' => $blocks));

		$out = '';
		foreach ($data['blocks'] as $block) {
			if (isset($block['content']) || count($block['options']) > 0 ) {
				$content = $block['content'];

				if (count($block['options']) > 0) {
					$table = new html_table(array('class' => $block['class'], 'cols' => $block['cols']));

					foreach ($block['options'] as $row) {
						if (isset($row['row_attribs']))
							$table->set_row_attribs($row['row_attribs']);

						if (isset($row['title']))
							$table->add('title', $row['title']);

						$table->add($row['content_attribs'], $row['content']);

						if (isset($row['help']))
							$table->add('help', $row['help']);
					}

					$content .= $table->show();
				}

				$out .= html::tag('fieldset', null, html::tag('legend', null, $block['name']) . $block['intro'] . $content);
			}
		}

		return $out;
	}

	private function _address_row($address_table, $field, $value, $attrib)
	{
		if (!isset($field))
			$address_table->set_row_attribs(array('style' => 'display: none;'));

		$hidden_action = new html_hiddenfield(array('name' => '_address_rule_act[]', 'value' => ''));
		$hidden_field = new html_hiddenfield(array('name' => '_address_rule_field[]', 'value' => $field));
		$hidden_text = new html_hiddenfield(array('name' => '_address_rule_value[]', 'value' => $value));

		switch ($field)
		{
			case "whitelist_from":
				$fieldtxt = rcube_utils::rep_specialchars_output($this->gettext('whitelist_from'));
				break;
			case "blacklist_from":
				$fieldtxt = rcube_utils::rep_specialchars_output($this->gettext('blacklist_from'));
				break;
			case "whitelist_to":
				$fieldtxt = rcube_utils::rep_specialchars_output($this->gettext('whitelist_to'));
				break;
		}

		$address_table->add(array('class' => $field), $fieldtxt);
		$address_table->add(array('class' => 'email'), $value);
		$del_button = $this->api->output->button(array('command' => 'plugin.sauserprefs.addressrule_del', 'type' => 'link', 'class' => 'delete', 'label' => 'delete', 'content' => ' ', 'title' => 'delete'));
		$address_table->add('control', $del_button . $hidden_action->show() . $hidden_field->show() . $hidden_text->show());

		return $address_table;
	}

	static function map_pref_name($pref, $reverse = false)
	{
		if (!$reverse) {
			if (array_key_exists($pref, self::$deprecated_prefs))
				$pref = self::$deprecated_prefs[$pref];
		}
		else {
			if (($orig_pref = array_search($pref, self::$deprecated_prefs)) != FALSE)
				$pref = $orig_pref;
		}

		return $pref;
	}

	static function sort_addresses($a, $b)
	{
		return strnatcasecmp($a["value"], $b["value"]);
	}

	private function _gen_email_arr($contact)
	{
		$emails = array();

		if (!is_array($contact))
			return $emails;

		foreach ($contact as $key => $value) {
			if (preg_match('/^email(:(.+))?$/i', $key, $matches)) {
				foreach ((array)$value as $subkey => $subval) {
					if ($matches[2])
						$emails[$matches[2] . $subkey] = $subval;
					else
						$emails['email' . $subkey] = $subval;
				}
			}
		}

		return $emails;
	}
}

?>