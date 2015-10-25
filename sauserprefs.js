/**
 * SAUserPrefs plugin script
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2009-2014 Philip Weir
 *
 * The JavaScript code in this page is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation, either version 3 of
 * the License, or (at your option) any later version.
 *
 * @licend  The above is the entire license notice
 * for the JavaScript code in this file.
 */

rcube_webmail.prototype.sauserprefs_toggle_level_char = function(checkbox) {
	var level_char;

	if (level_char = rcube_find_object('rcmfd_spamlevelchar'))
		level_char.disabled = !checkbox.checked;
}

rcube_webmail.prototype.sauserprefs_toggle_bayes = function(checkbox) {
	var tickbox;
	var dropdown;

	if (tickbox = rcube_find_object('rcmfd_spambayesrules'))
		tickbox.disabled = !checkbox.checked;

	if (tickbox = rcube_find_object('rcmfd_spambayesautolearn'))
		tickbox.disabled = !checkbox.checked;

	if ((dropdown = rcube_find_object('rcmfd_bayesnonspam')) && (tickbox.checked || !checkbox.checked))
		dropdown.disabled = !checkbox.checked;

	if ((dropdown = rcube_find_object('rcmfd_bayesspam')) && (tickbox.checked || !checkbox.checked))
		dropdown.disabled = !checkbox.checked;
}

rcube_webmail.prototype.sauserprefs_toggle_bayes_auto = function(checkbox) {
	var dropdown;

	if (dropdown = rcube_find_object('rcmfd_bayesnonspam'))
		dropdown.disabled = !checkbox.checked;

	if (dropdown = rcube_find_object('rcmfd_bayesspam'))
		dropdown.disabled = !checkbox.checked;
}

rcube_webmail.prototype.sauserprefs_addressrule_import = function(address) {
	parent.rcmail.set_busy(false, null, rcmail.env.sauserprefs_whitelist);

	var adrTable = rcube_find_object('address-rules-table').tBodies[0];

	var actions = document.getElementsByName('_address_rule_act[]');
	var prefs = document.getElementsByName('_address_rule_field[]');
	var addresses = document.getElementsByName('_address_rule_value[]');

	for (var i = 1; i < addresses.length; i++) {
		if (addresses[i].value == address && actions[i].value != "DELETE") {
			return false;
		}
	}

	var newNode = adrTable.rows[0].cloneNode(true);
	adrTable.rows[1].style.display = 'none';
	adrTable.appendChild(newNode);

	newNode.style.display = "";
	newNode.cells[0].className = "whitelist_from";
	newNode.cells[0].innerHTML = rcmail.get_label('whitelist_from','sauserprefs');
	newNode.cells[1].innerHTML = address;
	actions[newNode.rowIndex - 2].value = "INSERT";
	prefs[newNode.rowIndex - 2].value = "whitelist_from";
	addresses[newNode.rowIndex - 2].value = address;

	rcmail.env.address_rule_count++;
	rcmail.sauserprefs_table_sort('#spam-langs-table');
}

rcube_webmail.prototype.sauserprefs_help = function(sel) {
	var help = rcube_find_object(sel);
	help.style.display = (help.style.display == 'none' ? '' : 'none');
	return false;
}

rcube_webmail.prototype.sauserprefs_table_sort = function(id, idx, asc) {
	if (idx == null) {
		idx = rcmail.env.sauserprefs_sort[id][0];
		asc = rcmail.env.sauserprefs_sort[id][1] == "true";
	}

	var table = $(id);
	var rows = table.find('tbody tr:visible').toArray().sort(
		function(a, b) {
			var result;

			if (id == '#spam-langs-table' && $(a).children('td').eq(idx).hasClass('tick') && $(b).children('td').eq(idx).hasClass('tick')) {
				a = $(a).children('td').eq(idx).children('a:first').hasClass('enabled');
				b = $(b).children('td').eq(idx).children('a:first').hasClass('enabled');

				result = asc ? b - a : a - b;
			}
			else {
				a = $(a).children('td').eq(idx).html();
				b = $(b).children('td').eq(idx).html();

				result = asc ? a.localeCompare(b) : b.localeCompare(a);
			}

			return result;
		}
	);

	table.children('tbody').children('tr:visible').remove();
	for (var i = 0; i < rows.length; i++) {
		table.children('tbody').append(rows[i]);
	}
}

function sauserprefs_check_email(input) {
	if (input && window.RegExp) {
		// check for *.example.com
		var qtext = '[^\\x0d\\x22\\x5c\\x80-\\xff]',
			dtext = '[^\\x0d\\x5b-\\x5d\\x80-\\xff]',
			atom = '[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+',
			quoted_pair = '\\x5c[\\x00-\\x7f]',
			quoted_string = '\\x22('+qtext+'|'+quoted_pair+')*\\x22',
			ipv4 = '\\[(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])(\.(25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])){3}\\]',
			ipv6 = '\\[IPv6:[0-9a-f:.]+\\]',
			ip_addr = '(' + ipv4 + ')|(' + ipv6 + ')',
			// Use simplified domain matching, because we need to allow Unicode characters here
			// So, e-mail address should be validated also on server side after idn_to_ascii() use
			//domain_literal = '\\x5b('+dtext+'|'+quoted_pair+')*\\x5d',
			//sub_domain = '('+atom+'|'+domain_literal+')',
			// allow punycode/unicode top-level domain
			domain = '(('+ip_addr+')|(([^@\\x2e]+\\x2e)+([^\\x00-\\x40\\x5b-\\x60\\x7b-\\x7f]{2,}|xn--[a-z0-9]{2,})))',
			// ICANN e-mail test (http://idn.icann.org/E-mail_test)
			icann_domains = [
				'\\u0645\\u062b\\u0627\\u0644\\x2e\\u0625\\u062e\\u062a\\u0628\\u0627\\u0631',
				'\\u4f8b\\u5b50\\x2e\\u6d4b\\u8bd5',
				'\\u4f8b\\u5b50\\x2e\\u6e2c\\u8a66',
				'\\u03c0\\u03b1\\u03c1\\u03ac\\u03b4\\u03b5\\u03b9\\u03b3\\u03bc\\u03b1\\x2e\\u03b4\\u03bf\\u03ba\\u03b9\\u03bc\\u03ae',
				'\\u0909\\u0926\\u093e\\u0939\\u0930\\u0923\\x2e\\u092a\\u0930\\u0940\\u0915\\u094d\\u0937\\u093e',
				'\\u4f8b\\u3048\\x2e\\u30c6\\u30b9\\u30c8',
				'\\uc2e4\\ub840\\x2e\\ud14c\\uc2a4\\ud2b8',
				'\\u0645\\u062b\\u0627\\u0644\\x2e\\u0622\\u0632\\u0645\\u0627\\u06cc\\u0634\u06cc',
				'\\u043f\\u0440\\u0438\\u043c\\u0435\\u0440\\x2e\\u0438\\u0441\\u043f\\u044b\\u0442\\u0430\\u043d\\u0438\\u0435',
				'\\u0b89\\u0ba4\\u0bbe\\u0bb0\\u0ba3\\u0bae\\u0bcd\\x2e\\u0baa\\u0bb0\\u0bbf\\u0b9f\\u0bcd\\u0b9a\\u0bc8',
				'\\u05d1\\u05f2\\u05b7\\u05e9\\u05e4\\u05bc\\u05d9\\u05dc\\x2e\\u05d8\\u05e2\\u05e1\\u05d8'
			],
			icann_addr = '\\x2a\\x2e('+icann_domains.join('|')+')',
			addr_spec = '((\\x2a\\x2e'+domain+')|('+icann_addr+'))',
			reg1 = new RegExp('^'+addr_spec+'$', 'i');

		if (reg1.test(input)) {
			return true;
		}
	}

	return rcube_check_email(input, false);
}

$(document).ready(function() {
	if (window.rcmail) {
		if (document.getElementById('spam-langs-table')) {
			// add classes for sorting
			$('#spam-langs-table thead th').eq(rcmail.env.sauserprefs_sort['#spam-langs-table'][0]).addClass(rcmail.env.sauserprefs_sort['#spam-langs-table'][1] == "true" ? 'sortedASC' : 'sortedDESC');

			var spam_langs_table = new rcube_list_widget(document.getElementById('spam-langs-table'), {});
			spam_langs_table.init();

			// sort table according to user prefs
			rcmail.sauserprefs_table_sort('#spam-langs-table');
		}

		if (document.getElementById('address-rules-table')) {
			// add classes for sorting
			$('#address-rules-table thead th').eq(rcmail.env.sauserprefs_sort['#address-rules-table'][0]).addClass(rcmail.env.sauserprefs_sort['#address-rules-table'][1] == "true" ? 'sortedASC' : 'sortedDESC');

			var address_rules_table = new rcube_list_widget(document.getElementById('address-rules-table'), {});
			address_rules_table.init();

			// sort table according to user prefs
			rcmail.sauserprefs_table_sort('#address-rules-table');
		}

		rcmail.addEventListener('init', function(evt) {
			if (rcmail.env.action == 'plugin.sauserprefs.edit') {
				rcmail.register_command('plugin.sauserprefs.select_all_langs', function() {
					var langlist = document.getElementsByName('_spamlang[]');
					var obj;

					for (var i = 0; i < langlist.length; i++) {
						langlist[i].checked = true;
						obj = rcube_find_object('spam_lang_'+ i);
						obj.title = rcmail.get_label('enabled','sauserprefs');
						obj.className = 'enabled';
					}

					return false;
				}, true);

				rcmail.register_command('plugin.sauserprefs.select_invert_langs', function() {
					var langlist = document.getElementsByName('_spamlang[]');
					var obj;

					for (var i = 0; i < langlist.length; i++) {
						if (langlist[i].checked) {
							langlist[i].checked = false;
							obj = rcube_find_object('spam_lang_'+ i);
							obj.title = rcmail.get_label('disabled','sauserprefs');
							obj.className = 'disabled';
						}
						else {
							langlist[i].checked = true;
							obj = rcube_find_object('spam_lang_'+ i);
							obj.title = rcmail.get_label('enabled','sauserprefs');
							obj.className = 'enabled';
						}
					}

					return false;
				}, true);

				rcmail.register_command('plugin.sauserprefs.select_no_langs', function() {
					var langlist = document.getElementsByName('_spamlang[]');
					var obj;

					for (var i = 0; i < langlist.length; i++) {
						langlist[i].checked = false;
						obj = rcube_find_object('spam_lang_'+ i);
						obj.title = rcmail.get_label('disabled','sauserprefs');
						obj.className = 'disabled';
					}

					return false;
				}, true);

				rcmail.register_command('plugin.sauserprefs.message_lang', function(lang_code, obj) {
					var langlist = document.getElementsByName('_spamlang[]');
					var i = obj.parentNode.parentNode.rowIndex - 1;

					if (langlist[i].checked) {
						langlist[i].checked = false;
						obj.title = rcmail.get_label('disabled','sauserprefs');
						obj.className = 'disabled';
					}
					else {
						langlist[i].checked = true;
						obj.title = rcmail.get_label('enabled','sauserprefs');
						obj.className = 'enabled';
					}

					return false;
				}, true);

				rcmail.register_command('plugin.sauserprefs.addressrule_del', function(props, obj) {
					var adrTable = rcube_find_object('address-rules-table').tBodies[0];
					var rowidx = obj.parentNode.parentNode.rowIndex - 1;
					var fieldidx = rowidx - 1;

					if (!confirm(rcmail.get_label('spamaddressdelete','sauserprefs')))
						return false;

					if (document.getElementsByName('_address_rule_act[]')[fieldidx].value == "INSERT") {
						adrTable.deleteRow(rowidx);
					}
					else {
						adrTable.rows[rowidx].style.display = 'none';
						document.getElementsByName('_address_rule_act[]')[fieldidx].value = "DELETE";
					}

					rcmail.env.address_rule_count--;
					if (rcmail.env.address_rule_count < 1)
						adrTable.rows[1].style.display = '';

					return false;
				}, true);

				rcmail.register_command('plugin.sauserprefs.addressrule_add', function() {
					var adrTable = rcube_find_object('address-rules-table').tBodies[0];
					var input_spamaddressrule = rcube_find_object('_spamaddressrule');
					var selrule = input_spamaddressrule.selectedIndex;
					var input_spamaddress = rcube_find_object('_spamaddress');

					if (input_spamaddress.value.replace(/^\s+|\s+$/g, '') == '') {
						alert(rcmail.get_label('spamenteraddress','sauserprefs'));
						input_spamaddress.focus();
						return false;
					}
					else if (!sauserprefs_check_email(input_spamaddress.value.replace(/^\s+/, '').replace(/[\s,;]+$/, ''))) {
						alert(rcmail.get_label('spamaddresserror','sauserprefs'));
						input_spamaddress.focus();
						return false;
					}
					else {
						var actions = document.getElementsByName('_address_rule_act[]');
						var prefs = document.getElementsByName('_address_rule_field[]');
						var addresses = document.getElementsByName('_address_rule_value[]');

						for (var i = 1; i < addresses.length; i++) {
							if (addresses[i].value == input_spamaddress.value && actions[i].value != "DELETE") {
								alert(rcmail.get_label('spamaddressexists','sauserprefs'));
								input_spamaddress.focus();
								return false;
							}
						}

						var newNode = adrTable.rows[0].cloneNode(true);
						adrTable.rows[1].style.display = 'none';
						adrTable.appendChild(newNode);

						newNode.style.display = "";
						newNode.cells[0].className = input_spamaddressrule.options[selrule].value;
						newNode.cells[0].innerHTML = input_spamaddressrule.options[selrule].text;
						newNode.cells[1].innerHTML = input_spamaddress.value;
						actions[newNode.rowIndex - 2].value = "INSERT";
						prefs[newNode.rowIndex - 2].value = input_spamaddressrule.options[selrule].value;
						addresses[newNode.rowIndex - 2].value = input_spamaddress.value;

						input_spamaddressrule.selectedIndex = 0;
						input_spamaddress.value = '';

						rcmail.env.address_rule_count++;
						rcmail.sauserprefs_table_sort('#address-rules-table');
					}
				}, true);

				rcmail.register_command('plugin.sauserprefs.whitelist_delete_all', function(props, obj) {
					var adrTable = rcube_find_object('address-rules-table').tBodies[0];

					if (!confirm(rcmail.get_label('spamaddressdeleteall','sauserprefs')))
						return false;

					for (var i = adrTable.rows.length - 1; i > 1; i--) {
						if (document.getElementsByName('_address_rule_act[]')[i-1].value == "INSERT") {
							adrTable.deleteRow(i);
							rcmail.env.address_rule_count--;
						}
						else if (document.getElementsByName('_address_rule_act[]')[i-1].value != "DELETE") {
							adrTable.rows[i].style.display = 'none';
							document.getElementsByName('_address_rule_act[]')[i-1].value = "DELETE";
							rcmail.env.address_rule_count--;
						}
					}

					adrTable.rows[1].style.display = '';
					return false;
				}, true);

				rcmail.register_command('plugin.sauserprefs.import_whitelist', function(props, obj) {
					rcmail.env.sauserprefs_whitelist = rcmail.set_busy(true, 'sauserprefs.importingaddresses');
					rcmail.http_request('plugin.sauserprefs.whitelist_import', '', rcmail.env.sauserprefs_whitelist);
					return false;
				}, true);

				rcmail.register_command('plugin.sauserprefs.purge_bayes', function(props, obj) {
					if (confirm(rcmail.get_label('purgebayesconfirm','sauserprefs'))) {
						var lock = rcmail.set_busy(true, 'sauserprefs.purgingbayes');
						rcmail.http_request('plugin.sauserprefs.purge_bayes', '', lock);
					}

					return false;
				}, true);

				rcmail.register_command('plugin.sauserprefs.save', function() { rcmail.gui_objects.editform.submit(); }, true);

				rcmail.register_command('plugin.sauserprefs.default', function() {
					if (confirm(rcmail.get_label('usedefaultconfirm','sauserprefs'))) {
						// Score
						if (rcube_find_object('rcmfd_spamthres'))
							rcube_find_object('rcmfd_spamthres').selectedIndex = 0;

						// Subject tag
						if (rcube_find_object('rcmfd_spamsubject'))
							rcube_find_object('rcmfd_spamsubject').value = rcmail.env.rewrite_header_Subject

						// Languages
						var langlist = document.getElementsByName('_spamlang[]');
						var obj;
						var dlangs = " " + rcmail.env.ok_languages + " ";

						for (var i = 0; i < langlist.length; i++) {
							langlist[i].checked = false;
							obj = rcube_find_object('spam_lang_' + i);
							obj.title = rcmail.get_label('disabled','sauserprefs');
							obj.className = 'disabled';

							if (dlangs.indexOf(" " + langlist[i].value + " ") > -1 || rcmail.env.ok_languages == "all") {
								langlist[i].checked = true;
								obj = rcube_find_object('spam_lang_' + i);
								obj.title = rcmail.get_label('enabled','sauserprefs');
								obj.className = 'enabled';
							}
						}

						// Tests
						if (rcube_find_object('rcmfd_spamuserazor1')) {
							if (rcmail.env.use_razor1 == '1')
								rcube_find_object('rcmfd_spamuserazor1').checked = true;
							else
								rcube_find_object('rcmfd_spamuserazor1').checked = false;
						}

						if (rcube_find_object('rcmfd_spamuserazor2')) {
							if (rcmail.env.use_razor2 == '1')
								rcube_find_object('rcmfd_spamuserazor2').checked = true;
							else
								rcube_find_object('rcmfd_spamuserazor2').checked = false;
						}

						if (rcube_find_object('rcmfd_spamusepyzor')) {
							if (rcmail.env.use_pyzor == '1')
								rcube_find_object('rcmfd_spamusepyzor').checked = true;
							else
								rcube_find_object('rcmfd_spamusepyzor').checked = false;
						}

						if (rcube_find_object('rcmfd_spamusedcc')) {
							if (rcmail.env.use_dcc == '1')
								rcube_find_object('rcmfd_spamusedcc').checked = true;
							else
								rcube_find_object('rcmfd_spamusedcc').checked = false;
						}

						if (rcube_find_object('rcmfd_spamskiprblchecks')) {
							if (rcmail.env.skip_rbl_checks == '0')
								rcube_find_object('rcmfd_spamskiprblchecks').checked = true;
							else
								rcube_find_object('rcmfd_spamskiprblchecks').checked = false;
						}

						// Bayes
						if (rcube_find_object('rcmfd_spamusebayes')) {
							if (rcmail.env.use_bayes == '1')
								rcube_find_object('rcmfd_spamusebayes').checked = true;
							else
								rcube_find_object('rcmfd_spamusebayes').checked = false;
						}

						if (rcube_find_object('rcmfd_spambayesautolearn')) {
							if (rcmail.env.bayes_auto_learn == '1')
								rcube_find_object('rcmfd_spambayesautolearn').checked = true;
							else
								rcube_find_object('rcmfd_spambayesautolearn').checked = false;
						}

						if (rcube_find_object('rcmfd_bayesnonspam'))
							rcube_find_object('rcmfd_bayesnonspam').selectedIndex = 0;

						if (rcube_find_object('rcmfd_bayesspam'))
							rcube_find_object('rcmfd_bayesspam').selectedIndex = 0;

						if (rcube_find_object('rcmfd_spambayesrules')) {
							if (rcmail.env.use_bayes_rules == '1')
								rcube_find_object('rcmfd_spambayesrules').checked = true;
							else
								rcube_find_object('rcmfd_spambayesrules').checked = false;
						}

						// Headers
						if (rcube_find_object('rcmfd_spamfoldheaders')) {
							if (rcmail.env.fold_headers == '1')
								rcube_find_object('rcmfd_spamfoldheaders').checked = true;
							else
								rcube_find_object('rcmfd_spamfoldheaders').checked = false;
						}

						if (rcube_find_object('rcmfd_spamlevelstars')) {
							if (rcmail.env.add_header_all_Level != '') {
								rcube_find_object('rcmfd_spamlevelstars').checked = true;
								rcube_find_object('rcmfd_spamlevelchar').value = rcmail.env.add_header_all_Level.substr(7, 1);
							}
							else {
								rcube_find_object('rcmfd_spamlevelstars').checked = false;
								rcube_find_object('rcmfd_spamlevelchar').value = "*";
							}
						}

						// Report
						if (rcube_find_object('rcmfd_spamreport_0')) {
							if (rcmail.env.report_safe == '0')
								rcube_find_object('rcmfd_spamreport_0').checked = true;
							else
								rcube_find_object('rcmfd_spamreport_0').checked = false;
						}

						if (rcube_find_object('rcmfd_spamreport_1')) {
							if (rcmail.env.report_safe == '1')
								rcube_find_object('rcmfd_spamreport_1').checked = true;
							else
								rcube_find_object('rcmfd_spamreport_1').checked = false;
						}

						if (rcube_find_object('rcmfd_spamreport_2')) {
							if (rcmail.env.report_safe == '2')
								rcube_find_object('rcmfd_spamreport_2').checked = true;
							else
								rcube_find_object('rcmfd_spamreport_2').checked = false;
						}

						// Delete whitelist
						if (rcube_find_object('address-rules-table')) {
							var adrTable = rcube_find_object('address-rules-table').tBodies[0];
							for (var i = adrTable.rows.length - 1; i > 1; i--) {
								if (document.getElementsByName('_address_rule_act[]')[i-1].value == "INSERT") {
									adrTable.deleteRow(i);
									rcmail.env.address_rule_count--;
								}
								else if (document.getElementsByName('_address_rule_act[]')[i-1].value != "DELETE") {
									adrTable.rows[i].style.display = 'none';
									document.getElementsByName('_address_rule_act[]')[i-1].value = "DELETE";
									rcmail.env.address_rule_count--;
								}
							}
							adrTable.rows[1].style.display = '';
						}
					}
				}, true);

				rcmail.register_command('plugin.sauserprefs.table_sort', function(props, obj) {
					var id = props;
					var idx = $(obj).parent('th').index();
					var asc = !$(obj).parent('th').hasClass('sortedASC');

					rcmail.sauserprefs_table_sort(id, idx, asc);

					$(obj).parents('thead:first').find('th').removeClass('sortedASC').removeClass('sortedDESC');
					if (asc) {
						$(obj).parent('th').addClass('sortedASC');
						$(obj).parent('th').removeClass('sortedDESC');
					}
					else {
						$(obj).parent('th').removeClass('sortedASC');
						$(obj).parent('th').addClass('sortedDESC');
					}

					rcmail.env.sauserprefs_sort[id] = [idx, asc];
					rcmail.save_pref({name: 'sauserprefs_sort', value: rcmail.env.sauserprefs_sort, env: true});

					return false;
				}, true);

				rcmail.enable_command('plugin.sauserprefs.save','plugin.sauserprefs.default', true);
			}
		});

		if (rcmail.env.action == 'plugin.sauserprefs') {
			rcmail.section_select = function(list) {
				var id = list.get_single_selection()

				if (id) {
					var add_url = '';
					var target = window;
					this.set_busy(true);

					if (this.env.contentframe && window.frames && window.frames[this.env.contentframe]) {
						add_url = '&_framed=1';
						target = window.frames[this.env.contentframe];
					}

					target.location.href = this.env.comm_path + '&_action=plugin.sauserprefs.edit&_section=' + id + add_url;
				}

				return true;
			}
		}
	}
});