<?php

/**
 * SAUserPrefs
 *
 * Plugin to allow the user to manage their SpamAssassin settings using an SQL database
 *
 * @version @package_version@
 * @author Philip Weir
 */
class sauserprefs extends rcube_plugin
{
	public $task = 'mail|addressbook|settings';
	private $storage;
	private $sections = array();
	private $cur_section;
	private $global_prefs;
	private $user_prefs;
	private $addressbook = '0';
	private $sa_locales = array('en', 'ja', 'ko', 'ru', 'th', 'zh');
	private $sa_user;
	static $deprecated_prefs = array('required_hits' => 'required_score');

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

		// init storage
		include('include/rcube_sauserprefs_storage.php');
		$this->storage = new rcube_sauserprefs_storage($rcmail->config->get('sauserprefs_db_dsnw'), $rcmail->config->get('sauserprefs_db_dsnr'), $rcmail->config->get('sauserprefs_db_persistent'),
							$this->sa_user, $rcmail->config->get('sauserprefs_sql_table_name'), $rcmail->config->get('sauserprefs_sql_username_field'), $rcmail->config->get('sauserprefs_sql_preference_field'),
							$rcmail->config->get('sauserprefs_sql_value_field'), $rcmail->config->get('sauserprefs_bayes_delete_query'));

		if ($rcmail->config->get('sauserprefs_whitelist_abook_id', false))
			$this->addressbook = $rcmail->config->get('sauserprefs_whitelist_abook_id');

		if ($rcmail->task == 'settings') {
			$this->add_texts('localization/', array('sauserprefs', 'managespam'));
			$this->include_stylesheet($this->local_skin_path() . '/tabstyles.css');

			$this->sections = array(
				'general' => array('id' => 'general', 'section' => $this->gettext('spamgeneralsettings')),
				'tests' => array('id' => 'tests', 'section' => $this->gettext('spamtests')),
				'bayes' => array('id' => 'bayes', 'section' => $this->gettext('bayes')),
				'headers' => array('id' => 'headers', 'section' => $this->gettext('headers')),
				'report' => array('id' => 'report','section' => $this->gettext('spamreportsettings')),
				'addresses' => array('id' => 'addresses', 'section' => $this->gettext('spamaddressrules')),
			);
			$this->cur_section = rcube_utils::get_input_value('_section', rcube_utils::INPUT_GPC);

			$this->register_action('plugin.sauserprefs', array($this, 'init_html'));
			$this->register_action('plugin.sauserprefs.edit', array($this, 'init_html'));
			$this->register_action('plugin.sauserprefs.save', array($this, 'save'));
			$this->register_action('plugin.sauserprefs.whitelist_import', array($this, 'whitelist_import'));
			$this->register_action('plugin.sauserprefs.purge_bayes', array($this, 'purge_bayes'));
			$this->include_script('sauserprefs.js');
		}
		elseif ($rcmail->config->get('sauserprefs_whitelist_sync')) {
			$this->add_hook('contact_create', array($this, 'contact_add'));
			$this->add_hook('contact_update', array($this, 'contact_save'));
			$this->add_hook('contact_delete', array($this, 'contact_delete'));
		}
	}

	function init_html()
	{
		$this->_load_global_prefs();
		$this->_load_user_prefs();

		$this->api->output->set_pagetitle($this->gettext('sauserprefssettings'));

		if (rcube::get_instance()->action == 'plugin.sauserprefs.edit') {
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
		$no_override = array_flip(rcube::get_instance()->config->get('sauserprefs_dont_override'));

		// add id to message list table if not specified
		if (!strlen($attrib['id']))
			$attrib['id'] = 'rcmsectionslist';

		$sections = array();
		$blocks = $attrib['sections'] ? preg_split('/[\s,;]+/', strip_quotes($attrib['sections'])) : array_keys($this->sections);
		foreach ($blocks as $block) {
			if (!isset($no_override['{' . $block . '}']))
				$sections[$block] = $this->sections[$block];
		}

		// create XHTML table
		$out = rcube::get_instance()->table_output($attrib, $sections, array('section'), 'id');

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

		// output global prefs as default in env
		foreach($this->global_prefs as $key => $val)
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
		return $this->sections[$this->cur_section]['section'];
	}

	function save()
	{
		$rcmail = rcube::get_instance();
		$this->_load_global_prefs();
		$this->_load_user_prefs();

		$no_override = array_flip($rcmail->config->get('sauserprefs_dont_override'));
		$new_prefs = array();
		$result = true;

		switch ($this->cur_section)
		{
			case 'general':
				if (!isset($no_override['required_hits']))
					$new_prefs['required_hits'] = rcube_utils::get_input_value('_spamthres', rcube_utils::INPUT_POST);

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

				if (!isset($no_override['bayes_auto_learn_threshold_nonspam']) && !empty($_POST['_bayesnonspam']))
					$new_prefs['bayes_auto_learn_threshold_nonspam'] = rcube_utils::get_input_value('_bayesnonspam', rcube_utils::INPUT_POST);

				if (!isset($no_override['bayes_auto_learn_threshold_spam']) && !empty($_POST['_bayesspam']))
					$new_prefs['bayes_auto_learn_threshold_spam'] = rcube_utils::get_input_value('_bayesspam', rcube_utils::INPUT_POST);

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
			if ($this->storage->save_prefs($data['new_prefs'], $this->user_prefs, $this->global_prefs))
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
		$contacts = rcube::get_instance()->get_address_book($this->addressbook);
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

	function purge_bayes()
	{
		$rcmail = rcube::get_instance();

		if (!$rcmail->config->get('sauserprefs_bayes_delete', false)) {
			$this->api->output->command('display_message', $this->gettext('servererror'), 'error');
			return;
		}

		if ($this->storage->purge_bayes())
			$this->api->output->command('display_message', $this->gettext('done'), 'confirmation');
		else
			$this->api->output->command('display_message', $this->gettext('servererror'), 'error');
	}

	function contact_add($args)
	{
		$rcmail = rcube::get_instance();

		// only works with specified address book
		if ($args['source'] != $this->addressbook && $args['source'] != null)
			return;

		$emails = $this->_gen_email_arr($args['record']);
		$this->storage->whitelist_add($emails);
	}

	function contact_save($args)
	{
		$this->contact_delete($args);
		$this->contact_add($args);
	}

	function contact_delete($args)
	{
		$rcmail = rcube::get_instance();

		// only works with specified address book
		if ($args['source'] != $this->addressbook && $args['source'] != null)
			return;

		if (!is_array($args['id']))
			$args['id'] = array($args['id']);

		$contacts = $rcmail->get_address_book($this->addressbook);
		foreach ($args['id'] as $id) {
			$emails = $this->_gen_email_arr($contacts->get_record($id, true));
			$this->storage->whitelist_delete($emails);
		}

		$contacts->close();
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
		$prefs['addresses'] = $this->_subval_sort($prefs['addresses'], 'value');

		return $prefs;
	}

	private function _prefs_block($part, $attrib)
	{
		$rcmail = rcube::get_instance();
		$no_override = array_flip($rcmail->config->get('sauserprefs_dont_override'));
		$locale_info = localeconv();

		switch ($part)
		{
			// General tests
			case 'general':
				$out = '';
				$data = '';

				$table = new html_table(array('class' => 'generalprefstable', 'cols' => 2));

				if (!isset($no_override['required_hits'])) {
					$field_id = 'rcmfd_spamthres';
					$input_spamthres = new html_select(array('name' => '_spamthres', 'id' => $field_id));
					$input_spamthres->add($this->gettext('defaultscore'), '');

					$decPlaces = 0;
					if ($rcmail->config->get('sauserprefs_score_inc') - (int)$rcmail->config->get('sauserprefs_score_inc') > 0)
						$decPlaces = strlen($rcmail->config->get('sauserprefs_score_inc') - (int)$rcmail->config->get('sauserprefs_score_inc')) - 2;

					$score_found = false;
					for ($i = 1; $i <= 10; $i = $i + $rcmail->config->get('sauserprefs_score_inc')) {
						$input_spamthres->add(number_format($i, $decPlaces, $locale_info['decimal_point'], ''), number_format($i, $decPlaces, '.', ''));

						if (!$score_found && $this->user_prefs['required_hits'] && (float)$this->user_prefs['required_hits'] == (float)$i)
							$score_found = true;
					}

					if (!$score_found && $this->user_prefs['required_hits'])
						$input_spamthres->add(str_replace('%s', $this->user_prefs['required_hits'], $this->gettext('otherscore')), (float)$this->user_prefs['required_hits']);

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('spamthres'))));
					$table->add(null, $input_spamthres->show(number_format($this->user_prefs['required_hits'], $decPlaces, '.', '')));
					$table->add(array('colspan' => 2), rcmail::Q($this->gettext('spamthresexp')));
				}

				if (!isset($no_override['rewrite_header Subject'])) {
					$field_id = 'rcmfd_spamsubject';
					$input_spamsubject = new html_inputfield(array('name' => '_spamsubject', 'id' => $field_id, 'value' => $this->user_prefs['rewrite_header Subject'], 'style' => 'width:200px;'));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('spamsubject'))));
					$table->add(null, $input_spamsubject->show());

					$table->add('title', "&nbsp;");
					$table->add(null, rcmail::Q($this->gettext('spamsubjectblank')));
				}

				if ($table->size() > 0)
					$out .= html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('mainoptions'))) . $table->show());

				if (!isset($no_override['ok_languages']) || !isset($no_override['ok_locales'])) {
					$data = html::p(null, rcmail::Q($this->gettext('spamlangexp')));

					$table = new html_table(array('class' => 'langprefstable', 'cols' => 1));

					$select_all = $this->api->output->button(array('command' => 'plugin.sauserprefs.select_all_langs', 'type' => 'link', 'label' => 'all'));
					$select_none = $this->api->output->button(array('command' => 'plugin.sauserprefs.select_no_langs', 'type' => 'link', 'label' => 'none'));
					$select_invert = $this->api->output->button(array('command' => 'plugin.sauserprefs.select_invert_langs', 'type' => 'link', 'label' => 'invert'));

					$table->add(array('id' => 'listcontrols'), $this->gettext('select') .":&nbsp;&nbsp;". $select_all ."&nbsp;&nbsp;". $select_invert ."&nbsp;&nbsp;". $select_none);

					$lang_table = new html_table(array('id' => 'spam-langs-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 2));
					$lang_table->add_header(array('colspan' => 2), $this->gettext('language'));

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

					$table->add('scroller', html::div(array('id' => 'spam-langs-cont'), $lang_table->show()));

					$out .= html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('langoptions'))) . $data . $table->show());
				}

				break;

			// Header settings
			case 'headers':
				$data = html::p(null, rcmail::Q($this->gettext('headersexp')));

				$table = new html_table(array('class' => 'headersprefstable', 'cols' => 3));

				if (!isset($no_override['fold_headers'])) {
					$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("fold_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamfoldheaders';
					$input_spamreport = new html_checkbox(array('name' => '_spamfoldheaders', 'id' => $field_id, 'value' => '1'));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('foldheaders'))));
					$table->add(null, $input_spamreport->show($this->user_prefs['fold_headers']));
					$table->add('help', $help_button);
					$table->set_row_attribs(array('id' => 'fold_help', 'style' => 'display: none;'));
					$table->add(array('colspan' => '3'), rcmail::Q($this->gettext('foldhelp')));
				}

				if (!isset($no_override['add_header all Level'])) {
					$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
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

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('spamlevelstars'))));
					$table->add(null, $input_spamreport->show($enabled));
					$table->add('help', $help_button);
					$table->set_row_attribs(array('id' => 'level_help', 'style' => 'display: none;'));
					$table->add(array('colspan' => '3'), rcmail::Q($this->gettext('levelhelp')));

					$field_id = 'rcmfd_spamlevelchar';
					$input_spamsubject = new html_inputfield(array('name' => '_spamlevelchar', 'id' => $field_id, 'value' => $char,
						'style' => 'width:20px;', 'disabled' => $enabled?0:1));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('spamlevelchar'))));
					$table->add(null, $input_spamsubject->show());
					$table->add('help', '&nbsp;');
				}

				$out = html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('mainoptions'))) . $data . $table->show());
				break;

			// Test settings
			case 'tests':
				$data = html::p(null, rcmail::Q($this->gettext('spamtestssexp')));

				$table = new html_table(array('class' => 'testsprefstable', 'cols' => 3));

				if (!isset($no_override['use_razor1'])) {
					$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("raz1_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamuserazor1';
					$input_spamtest = new html_checkbox(array('name' => '_spamuserazor1', 'id' => $field_id, 'value' => '1'));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('userazor1'))));
					$table->add(null, $input_spamtest->show($this->user_prefs['use_razor1']));
					$table->add('help', $help_button);
					$table->set_row_attribs(array('id' => 'raz1_help', 'style' => 'display: none;'));
					$table->add(array('colspan' => '3'), rcmail::Q($this->gettext('raz1help')));
				}

				if (!isset($no_override['use_razor2'])) {
					$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("raz2_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamuserazor2';
					$input_spamtest = new html_checkbox(array('name' => '_spamuserazor2', 'id' => $field_id, 'value' => '1'));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('userazor2'))));
					$table->add(null, $input_spamtest->show($this->user_prefs['use_razor2']));
					$table->add('help', $help_button);
					$table->set_row_attribs(array('id' => 'raz2_help', 'style' => 'display: none;'));
					$table->add(array('colspan' => '3'), rcmail::Q($this->gettext('raz2help')));
				}

				if (!isset($no_override['use_pyzor'])) {
					$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("pyz_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamusepyzor';
					$input_spamtest = new html_checkbox(array('name' => '_spamusepyzor', 'id' => $field_id, 'value' => '1'));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('usepyzor'))));
					$table->add(null, $input_spamtest->show($this->user_prefs['use_pyzor']));
					$table->add('help', $help_button);
					$table->set_row_attribs(array('id' => 'pyz_help', 'style' => 'display: none;'));
					$table->add(array('colspan' => '3'), rcmail::Q($this->gettext('pyzhelp')));
				}

				if (!isset($no_override['use_dcc'])) {
					$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("dcc_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamusedcc';
					$input_spamtest = new html_checkbox(array('name' => '_spamusedcc', 'id' => $field_id, 'value' => '1'));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('usedcc'))));
					$table->add(null, $input_spamtest->show($this->user_prefs['use_dcc']));
					$table->add('help', $help_button);
					$table->set_row_attribs(array('id' => 'dcc_help', 'style' => 'display: none;'));
					$table->add(array('colspan' => '3'), rcmail::Q($this->gettext('dcchelp')));
				}

				if (!isset($no_override['skip_rbl_checks'])) {
					$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("rbl_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamskiprblchecks';
					$enabled = $this->user_prefs['skip_rbl_checks'] == "1" ? "0" : "1";
					$input_spamtest = new html_checkbox(array('name' => '_spamskiprblchecks', 'id' => $field_id, 'value' => '1'));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('skiprblchecks'))));
					$table->add(null, $input_spamtest->show($enabled));
					$table->add('help', $help_button);
					$table->set_row_attribs(array('id' => 'rbl_help', 'style' => 'display: none;'));
					$table->add(array('colspan' => '3'), rcmail::Q($this->gettext('rblhelp')));
				}

				$out = html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('mainoptions'))) . $data . $table->show());
				break;

			// Bayes settings
			case 'bayes':
				$data = html::p(null, rcmail::Q($this->gettext('bayeshelp')));

				$table = new html_table(array('class' => 'bayesprefstable', 'cols' => 3));

				if (!isset($no_override['use_bayes'])) {
					$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("bayes_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spamusebayes';
					$input_spamtest = new html_checkbox(array('name' => '_spamusebayes', 'id' => $field_id, 'value' => '1',
						'onchange' => rcmail_output::JS_OBJECT_NAME . '.sauserprefs_toggle_bayes(this)'));

					if ($rcmail->config->get('sauserprefs_bayes_delete', false))
						$delete_link =  "&nbsp;&nbsp;&nbsp;" . html::span(array('id' => 'listcontrols'), $this->api->output->button(array('command' => 'plugin.sauserprefs.purge_bayes', 'type' => 'link', 'label' => 'sauserprefs.purgebayes', 'title' => 'sauserprefs.purgebayesexp')));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('usebayes'))));
					$table->add(null, $input_spamtest->show($this->user_prefs['use_bayes']) . $delete_link);
					$table->add('help', '&nbsp;');
					$table->set_row_attribs(array('id' => 'bayes_help', 'style' => 'display: none;'));
					$table->add(array('colspan' => '3'), rcmail::Q($this->gettext('bayeshelp')));
				}

				if (!isset($no_override['use_bayes_rules'])) {
					$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("bayesrules_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spambayesrules';
					$input_spamtest = new html_checkbox(array('name' => '_spambayesrules', 'id' => $field_id, 'value' => '1', 'disabled' => $this->user_prefs['use_bayes']?0:1));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('bayesrules'))));
					$table->add(null, $input_spamtest->show($this->user_prefs['use_bayes_rules']));
					$table->add('help', $help_button);
					$table->set_row_attribs(array('id' => 'bayesrules_help', 'style' => 'display: none;'));
					$table->add(array('colspan' => '3'), rcmail::Q($this->gettext('bayesruleshlp')));
				}

				if (!isset($no_override['bayes_auto_learn'])) {
					$help_button = html::img(array('class' => $imgclass, 'src' => $attrib['helpicon'], 'alt' => $this->gettext('sieveruleheaders'), 'border' => 0, 'style' => 'margin-left: 4px;'));
					$help_button = html::a(array('name' => '_headerhlp', 'href' => "#", 'onclick' => 'return '. rcmail_output::JS_OBJECT_NAME .'.sauserprefs_help("bayesauto_help");', 'title' => $this->gettext('help')), $help_button);

					$field_id = 'rcmfd_spambayesautolearn';
					$input_spamtest = new html_checkbox(array('name' => '_spambayesautolearn', 'id' => $field_id, 'value' => '1',
						'onchange' => rcmail_output::JS_OBJECT_NAME . '.sauserprefs_toggle_bayes_auto(this)', 'disabled' => $this->user_prefs['use_bayes']?0:1));

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('bayesautolearn'))));
					$table->add(null, $input_spamtest->show($this->user_prefs['bayes_auto_learn']));
					$table->add('help', $help_button);
					$table->set_row_attribs(array('id' => 'bayesauto_help', 'style' => 'display: none;'));
					$table->add(array('colspan' => '3'), rcmail::Q($this->gettext('bayesautohelp')));
				}

				if ($table->size() > 0)
					$out = html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('mainoptions'))) . $table->show());

				$table = new html_table(array('class' => 'bayesprefstable', 'cols' => 2));

				$data = "";
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

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('bayesnonspam'))));
					$table->add(null, $input_bayesnthres->show(number_format($this->user_prefs['bayes_auto_learn_threshold_nonspam'], $decPlaces, '.', '')));
					$table->add(array('colspan' => '2'), rcmail::Q($this->gettext('bayesnonspamexp')));
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

					if (!$score_found && $this->user_prefs['required_hits'])
						$input_bayesthres->add(str_replace('%s', $this->user_prefs['bayes_auto_learn_threshold_spam'], $this->gettext('otherscore')), (float)$this->user_prefs['bayes_auto_learn_threshold_spam']);

					$table->add('title', html::label($field_id, rcmail::Q($this->gettext('bayesspam'))));
					$table->add(null, $input_bayesthres->show(number_format($this->user_prefs['bayes_auto_learn_threshold_spam'], $decPlaces, '.', '')));
					$table->add(array('colspan' => '2'), rcmail::Q($this->gettext('bayesspamexp')));
				}

				if ($table->size() > 0)
					$out .= html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('bayesautooptions'))) . $table->show());

				break;

			// Report settings
			case 'report':
				$data = html::p(null, rcmail::Q($this->gettext('spamreport')));

				$table = new html_table(array('class' => 'reportprefstable', 'cols' => 2));

				if (!isset($no_override['report_safe'])) {
					$field_id = 'rcmfd_spamreport';
					$input_spamreport0 = new html_radiobutton(array('name' => '_spamreport', 'id' => $field_id.'_0', 'value' => '0'));
					$table->add('title', html::label($field_id.'_0', rcmail::Q($this->gettext('spamreport0'))));
					$table->add(null, $input_spamreport0->show($this->user_prefs['report_safe']));

					$input_spamreport1 = new html_radiobutton(array('name' => '_spamreport', 'id' => $field_id.'_1', 'value' => '1'));
					$table->add('title', html::label($field_id.'_1', rcmail::Q($this->gettext('spamreport1'))));
					$table->add(null, $input_spamreport1->show($this->user_prefs['report_safe']));
					$data .= $input_spamreport1->show($this->user_prefs['report_safe']) ."&nbsp;". html::label($field_id .'_1', rcmail::Q($this->gettext('spamreport1'))) . "<br />";

					$input_spamreport2 = new html_radiobutton(array('name' => '_spamreport', 'id' => $field_id.'_2', 'value' => '2'));
					$table->add('title', html::label($field_id.'_2', rcmail::Q($this->gettext('spamreport2'))));
					$table->add(null, $input_spamreport2->show($this->user_prefs['report_safe']));
				}

				$out = html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('mainoptions'))) . $table->show());
				break;

			// Address settings
			case 'addresses':
				$data = html::p(null, rcmail::Q($this->gettext('whitelistexp')));

				if ($rcmail->config->get('sauserprefs_whitelist_sync'))
					$data .= rcmail::Q($this->gettext('autowhitelist')) . "<br /><br />";

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

				$import = $this->api->output->button(array('command' => 'plugin.sauserprefs.import_whitelist', 'type' => 'link', 'label' => 'import', 'title' => 'sauserprefs.importfromaddressbook'));
				$delete_all = $this->api->output->button(array('command' => 'plugin.sauserprefs.whitelist_delete_all', 'type' => 'link', 'label' => 'sauserprefs.deleteall'));

				$table->add(array('colspan' => 4, 'id' => 'listcontrols'), $import ."&nbsp;&nbsp;". $delete_all);

				$address_table = new html_table(array('id' => 'address-rules-table', 'class' => 'records-table', 'cellspacing' => '0', 'cols' => 3));
				$address_table->add_header('rule', $this->gettext('rule'));
				$address_table->add_header('email', $this->gettext('email'));
				$address_table->add_header('control', '&nbsp;');

				$this->_address_row($address_table, null, null, $attrib);

				if (sizeof($this->user_prefs['addresses']) > 0)
					$norules = 'display: none;';

				$address_table->set_row_attribs(array('style' => $norules));
				$address_table->add(array('colspan' => '3'), rcube_utils::rep_specialchars_output($this->gettext('noaddressrules')));

				$this->api->output->set_env('address_rule_count', sizeof($this->user_prefs['addresses']));
				foreach ($this->user_prefs['addresses'] as $address)
					$this->_address_row($address_table, $address['field'], $address['value'], $attrib);

				$table->add(array('colspan' => 4, 'class' => 'scroller'), html::div(array('id' => 'address-rules-cont'), $address_table->show()));

				if ($table->size())
					$out = html::tag('fieldset', null, html::tag('legend', null, rcmail::Q($this->gettext('mainoptions'))) . $data . $table->show());

				break;

			default:
				$out = '';
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
		$del_button = $this->api->output->button(array('command' => 'plugin.sauserprefs.addressrule_del', 'type' => 'link', 'class' => 'delete', 'label' => 'delete', 'content' => ' '));
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

	private function _subval_sort($a, $subkey)
	{
		if (sizeof($a) == 0)
			return array();

		foreach ($a as $k => $v)
			$b[$k] = strtolower($v[$subkey]);

		asort($b);

		foreach ($b as $k => $v)
			$c[] = $a[$k];

		return $c;
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