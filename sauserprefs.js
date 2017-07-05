/**
 * SAUserPrefs plugin script
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) 2009-2017 Philip Weir
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
    if ($(checkbox).is(':checked')) {
        $('#rcmfd_spamlevelchar').removeAttr('disabled');
    }
    else {
        $('#rcmfd_spamlevelchar').attr('disabled', 'disabled');
    }
}

rcube_webmail.prototype.sauserprefs_toggle_bayes = function(checkbox) {
    if ($(checkbox).is(':checked')) {
        $('#rcmfd_spambayesrules,#rcmfd_spambayesautolearn').removeAttr('disabled');

        if ($('#rcmfd_spambayesautolearn').is(':checked')) {
            $('#rcmfd_bayesnonspam,#rcmfd_bayesspam').removeAttr('disabled');
        }
    }
    else {
        $('#rcmfd_spambayesrules,#rcmfd_spambayesautolearn,#rcmfd_bayesnonspam,#rcmfd_bayesspam').attr('disabled', 'disabled');
    }
}

rcube_webmail.prototype.sauserprefs_toggle_bayes_auto = function(checkbox) {
    if ($(checkbox).is(':checked')) {
        $('#rcmfd_bayesnonspam,#rcmfd_bayesspam').removeAttr('disabled');
    }
    else {
        $('#rcmfd_bayesnonspam,#rcmfd_bayesspam').attr('disabled', 'disabled');
    }
}

rcube_webmail.prototype.sauserprefs_update_lang = function(chkbox, tickobj, enable) {
    if (enable) {
        chkbox.attr('checked', 'checked');
        tickobj.attr('title', rcmail.get_label('enabled', 'sauserprefs')).removeClass('disabled').addClass('enabled');
    }
    else {
        chkbox.removeAttr('checked');
        tickobj.attr('title', rcmail.get_label('disabled', 'sauserprefs')).removeClass('enabled').addClass('disabled');
    }
}

rcube_webmail.prototype.sauserprefs_addressrule_insert_row = function(p) {
    var error = false;
    $.each($('input[name="_address_rule_value[]"]'), function(idx) {
        if ($(this).val() == p.address && $('input[name="_address_rule_act[]"]').eq(idx).val() != "DELETE") {
            error = true;
            return false;
        }
    });
    if (error)
        return false;

    var adrTable = $('#address-rules-table tbody');
    var new_row = $(adrTable).children('tr.newaddressrule').clone().removeClass('newaddressrule').show();
    new_row.children('td').eq(0).addClass(p.type).text(p.desc);
    new_row.children('td').eq(1).text(p.address);
    new_row.find('input[name="_address_rule_act[]"]').val('INSERT');
    new_row.find('input[name="_address_rule_field[]"]').val(p.type);
    new_row.find('input[name="_address_rule_value[]"]').val(p.address);
    $(new_row).appendTo('#address-rules-table tbody');

    $(adrTable).children('tr.noaddressrules').hide();

    rcmail.env.address_rule_count++;
    rcmail.sauserprefs_table_sort('#address-rules-table');

    return true;
}

rcube_webmail.prototype.sauserprefs_addressrule_delete_row = function(obj) {
    var actField = $(obj).closest('td').find('input[name="_address_rule_act[]"]');

    if (actField.val() == "INSERT") {
        $(obj).closest('tr').remove();
    }
    else {
        actField.val('DELETE');
        $(obj).closest('tr').hide().appendTo('#address-rules-table tbody');
    }

    rcmail.env.address_rule_count--;

    if ($('#address-rules-table tbody').children('tr:visible').length == 0)
        $('#address-rules-table tbody').children('tr.noaddressrules').show();
}

rcube_webmail.prototype.sauserprefs_addressrule_import = function(address) {
    parent.rcmail.set_busy(false, null, rcmail.env.sauserprefs_whitelist);
    rcmail.sauserprefs_addressrule_insert_row({'type': 'whitelist_from', 'desc': rcmail.get_label('whitelist_from','sauserprefs'), 'address': address});
}

rcube_webmail.prototype.sauserprefs_help = function(sel) {
    $('#' + sel).toggle();
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

    // move hidden rows to the bottom of the table
    table.children('tbody').children('tr:hidden').appendTo(table.children('tbody'));
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
        // set table sorting classes
        rcmail.env.sauserprefs_table_sort_asc = 'sortedASC sorted-asc'; // sortedASC class depreciated in v1.18
        rcmail.env.sauserprefs_table_sort_desc =  'sortedDESC sorted-desc'; // sortedDESC class depreciated in v1.18

        $.each(['#spam-langs-table', '#address-rules-table'], function(idx, id) {
            if ($(id).length == 1) {
                // add classes for sorting
                var sorting_defaults = rcmail.env.sauserprefs_sort[id];
                $(id).find('thead th').eq(sorting_defaults[0]).addClass(sorting_defaults[1] == "true" ? rcmail.env.sauserprefs_table_sort_asc : rcmail.env.sauserprefs_table_sort_desc);

                var temp_table = new rcube_list_widget($(id)[0], {});
                temp_table.init();

                // sort table according to user prefs
                rcmail.sauserprefs_table_sort(id);
            }

        });

        rcmail.addEventListener('init', function(evt) {
            if (rcmail.env.action == 'plugin.sauserprefs.edit') {
                rcmail.register_command('plugin.sauserprefs.select_all_langs', function() {
                    $.each($('input[name="_spamlang[]"]'), function(idx) {
                        rcmail.sauserprefs_update_lang($(this), $('[id^=spam_lang_]').eq(idx), true);
                    });

                    return false;
                }, true);

                rcmail.register_command('plugin.sauserprefs.select_invert_langs', function() {
                    $.each($('input[name="_spamlang[]"]'), function(idx) {
                        rcmail.sauserprefs_update_lang($(this), $('[id^=spam_lang_]').eq(idx), !$(this).is(':checked'));
                    });

                    return false;
                }, true);

                rcmail.register_command('plugin.sauserprefs.select_no_langs', function() {
                    $.each($('input[name="_spamlang[]"]'), function(idx) {
                        rcmail.sauserprefs_update_lang($(this), $('[id^=spam_lang_]').eq(idx), false);
                    });

                    return false;
                }, true);

                rcmail.register_command('plugin.sauserprefs.message_lang', function(lang_code, obj) {
                    var langtick = $(obj).closest('tr').find('input');
                    rcmail.sauserprefs_update_lang($(langtick), $(obj), !$(langtick).is(':checked'));
                    return false;
                }, true);

                rcmail.register_command('plugin.sauserprefs.addressrule_del', function(props, obj) {
                    if (!confirm(rcmail.get_label('spamaddressdelete','sauserprefs')))
                        return false;

                    rcmail.sauserprefs_addressrule_delete_row(obj);

                    return false;
                }, true);

                rcmail.register_command('plugin.sauserprefs.addressrule_add', function() {
                    if ($('#rcmfd_spamaddress').val().replace(/^\s+|\s+$/g, '') == '') {
                        alert(rcmail.get_label('spamenteraddress','sauserprefs'));
                        $('#rcmfd_spamaddress').focus();
                        return false;
                    }
                    else if (!sauserprefs_check_email($('#rcmfd_spamaddress').val().replace(/^\s+/, '').replace(/[\s,;]+$/, ''))) {
                        alert(rcmail.get_label('spamaddresserror','sauserprefs'));
                        $('#rcmfd_spamaddress').focus();
                        return false;
                    }
                    else {
                        if (!rcmail.sauserprefs_addressrule_insert_row({'type': $('#rcmfd_spamaddressrule').val(), 'desc': $('#rcmfd_spamaddressrule option:selected').text(), 'address': $('#rcmfd_spamaddress').val()})) {
                            alert(rcmail.get_label('spamaddressexists','sauserprefs'));
                            $('#rcmfd_spamaddress').focus();
                            return false;
                        }
                        else {
                            $('#rcmfd_spamaddress').val('');
                        }
                    }
                }, true);

                rcmail.register_command('plugin.sauserprefs.whitelist_delete_all', function(props, obj) {
                    if (!confirm(rcmail.get_label('spamaddressdeleteall','sauserprefs')))
                        return false;

                    $.each($('#address-rules-table tbody tr:visible'), function() { rcmail.sauserprefs_addressrule_delete_row(this) });

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
                        $('#rcmfd_spamthres').val(''); // Score
                        $('#rcmfd_spamsubject').val(rcmail.env.rewrite_header_Subject); // Subject tag

                        // Languages
                        var dlangs = " " + rcmail.env.ok_languages + " ";
                        $.each($('input[name="_spamlang[]"]'), function(idx) {
                            $(this).removeAttr('checked');
                            $('[id^=spam_lang_]').eq(idx).attr('title', rcmail.get_label('disabled', 'sauserprefs')).removeClass('enabled').addClass('disabled');

                            if (dlangs.indexOf(" " + $(this).val() + " ") > -1 || rcmail.env.ok_languages == "all") {
                                $(this).attr('checked', 'checked');
                                $('[id^=spam_lang_]').eq(idx).attr('title', rcmail.get_label('enabled', 'sauserprefs')).removeClass('disabled').addClass('enabled');
                            }
                        });

                        // Defaults for checkboxes
                        var checkboxes = {
                            // Tests
                            'rcmfd_spamuserazor1': rcmail.env.use_razor1 == '1',
                            'rcmfd_spamuserazor2': rcmail.env.use_razor2 == '1',
                            'rcmfd_spamusepyzor': rcmail.env.use_pyzor == '1',
                            'rcmfd_spamusedcc': rcmail.env.use_dcc == '1',
                            'rcmfd_spamskiprblchecks': rcmail.env.skip_rbl_checks == '0',
                            // Bayes
                            'rcmfd_spamusebayes': rcmail.env.use_bayes == '1',
                            'rcmfd_spambayesautolearn': rcmail.env.bayes_auto_learn == '1',
                            'rcmfd_spambayesrules': rcmail.env.use_bayes_rules == '1',
                            // Headers
                            'rcmfd_spamfoldheaders': rcmail.env.fold_headers == '1',
                            'rcmfd_spamlevelstars': rcmail.env.add_header_all_Level != '',
                            // Report
                            'rcmfd_spamreport_0': rcmail.env.report_safe == '0',
                            'rcmfd_spamreport_1': rcmail.env.report_safe == '1',
                            'rcmfd_spamreport_2': rcmail.env.report_safe == '2',
                        };
                        $.each(checkboxes, function(id, checked) { $('#' + id).prop('checked', checked); });

                        $('#rcmfd_bayesnonspam,#rcmfd_bayesspam').val(''); // Bayes non spam/spam score
                        $('#rcmfd_spamlevelchar').val(rcmail.env.add_header_all_Level.substr(7, 1)); // Spam level char

                        // Delete whitelist
                        $.each($('#address-rules-table tbody tr:visible'), function() { rcmail.sauserprefs_addressrule_delete_row(this) });

                        // Toggle dependant fields
                        rcmail.sauserprefs_toggle_level_char($('#rcmfd_spamlevelstars'));
                        rcmail.sauserprefs_toggle_bayes($('#rcmfd_spamusebayes'));
                        rcmail.sauserprefs_toggle_bayes_auto($('#rcmfd_spambayesautolearn'));
                    }
                }, true);

                rcmail.register_command('plugin.sauserprefs.table_sort', function(props, obj) {
                    var id = props;
                    var idx = $(obj).parent('th').index();
                    var asc = !$(obj).parent('th').hasClass(rcmail.env.sauserprefs_table_sort_asc);

                    rcmail.sauserprefs_table_sort(id, idx, asc);

                    $(obj).parents('thead:first').find('th').removeClass(rcmail.env.sauserprefs_table_sort_asc).removeClass(rcmail.env.sauserprefs_table_sort_desc);
                    $(obj).parent('th').addClass(asc ? rcmail.env.sauserprefs_table_sort_asc : rcmail.env.sauserprefs_table_sort_desc);

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