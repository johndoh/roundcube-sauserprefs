/**
 * SAUserPrefs plugin script
 *
 * @licstart  The following is the entire license notice for the
 * JavaScript code in this file.
 *
 * Copyright (C) Philip Weir
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
        chkbox.prop('checked', true);
        tickobj.attr('title', this.get_label('enabled', 'sauserprefs')).removeClass('lang-disabled').addClass('lang-enabled');
    }
    else {
        chkbox.prop('checked', false);
        tickobj.attr('title', this.get_label('disabled', 'sauserprefs')).removeClass('lang-enabled').addClass('lang-disabled');
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
    var new_row = $(adrTable).children('tr.newaddressrule').clone();
    new_row.removeClass('newaddressrule').addClass(p.type);
    new_row.children('td').eq(0).text(p.desc);
    new_row.children('td').eq(1).text(p.address);
    new_row.find('input[name="_address_rule_act[]"]').val('INSERT');
    new_row.find('input[name="_address_rule_field[]"]').val(p.type);
    new_row.find('input[name="_address_rule_value[]"]').val(p.address);
    $(new_row).show().appendTo('#address-rules-table tbody');

    $(adrTable).children('tr.noaddressrules').hide();

    this.env.address_rule_count++;
    this.sauserprefs_table_sort('#address-rules-table');

    return true;
}

rcube_webmail.prototype.sauserprefs_addressrule_delete_row = function(obj) {
    var actField = $(obj).find('input[name="_address_rule_act[]"]');

    // skip empty rows
    if (!actField.parent().is(':visible'))
        return;

    if (actField.val() == "INSERT") {
        $(obj).closest('tr').remove();
    }
    else {
        actField.val('DELETE');
        $(obj).closest('tr').hide().appendTo('#address-rules-table tbody');
    }

    this.env.address_rule_count--;

    if ($('#address-rules-table tbody').children('tr').filter(':visible').length == 0)
        $('#address-rules-table tbody').children('tr.noaddressrules').show();
}

rcube_webmail.prototype.sauserprefs_addressrule_import = function(address) {
    parent.rcmail.set_busy(false, null, this.env.sauserprefs_welcomelist);
    this.sauserprefs_addressrule_insert_row({'type': 'welcomelist_from', 'desc': this.get_label('welcomelist_from','sauserprefs'), 'address': address});
}

rcube_webmail.prototype.sauserprefs_help = function(sel) {
    $('#' + sel).toggle();
    return false;
}

rcube_webmail.prototype.sauserprefs_table_sort = function(id, idx, asc) {
    if (idx == null) {
        idx = this.env.sauserprefs_sort[id][0];
        asc = this.env.sauserprefs_sort[id][1] == "true";
    }

    var table = $(id);
    var rows = table.find('tbody tr').filter(':visible').toArray().sort(
        function(a, b) {
            var result;

            if (id == '#spam-langs-table' && $(a).children('td').eq(idx).hasClass('tick') && $(b).children('td').eq(idx).hasClass('tick')) {
                if ($(a).children('td').eq(idx).find('a').length > 0) {
                    a = $(a).children('td').eq(idx).find('a').first().hasClass('lang-enabled');
                    b = $(b).children('td').eq(idx).find('a').first().hasClass('lang-enabled');
                }
                else {
                    a = $(a).children('td').eq(idx).find('input').first().is(':checked');
                    b = $(b).children('td').eq(idx).find('input').first().is(':checked');
                }

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

    table.children('tbody').children('tr').filter(':visible').remove();
    for (var i = 0; i < rows.length; i++) {
        table.children('tbody').append(rows[i]);
    }

    // move hidden rows to the bottom of the table
    table.children('tbody').children('tr').filter(':hidden').appendTo(table.children('tbody'));
}

rcube_webmail.prototype.sauserprefs_address_import_dialog = function() {
    var dialog = $('#saup_addressimport').clone();
    this.simple_dialog(dialog.show(), this.gettext('sauserprefs.importaddresses'), function() {
        if (dialog.find('input:checked').length > 0) {
            var sources = dialog.find('input:checked').map(function(){ return $(this).val(); }).get();
            rcmail.command('plugin.sauserprefs.import_welcomelist', sources);
            return true;
        }
        else {
            rcmail.display_message(rcmail.get_label('selectimportsource','sauserprefs'), 'warning');
            return false;
        }
    }, { button: 'import' });
}

function sauserprefs_check_email(input) {
    // special handeling for *.example.com (avoid *.text@email.com)
    if (!input.match(/@/)) {
        input = input.replace(/^\*\./, '*@');
    }
    return rcube_check_email(input, false);
}

$(document).ready(function() {
    if (window.rcmail) {
        // set table sorting classes
        rcmail.env.sauserprefs_table_sort_asc = 'sortedASC'; // sortedASC class used in core
        rcmail.env.sauserprefs_table_sort_desc = 'sortedDESC'; // sortedDESC class used in core

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

        rcmail.addEventListener('init', function() {
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
                    rcmail.confirm_dialog(rcmail.get_label('spamaddressdelete','sauserprefs'), 'delete', function(e, ref) {
                            ref.sauserprefs_addressrule_delete_row($(obj).parent());
                        });
                    return false;
                }, true);

                rcmail.register_command('plugin.sauserprefs.addressrule_add', function() {
                    var address_input = $('#rcmfd_spamaddress').val().trim();
                    // remove soem invalid characters from the end of the input
                    address_input = address_input.replace(/[\s\t.,;]+$/, '');
                    if (address_input == '') {
                        rcmail.display_message(rcmail.get_label('spamenteraddress','sauserprefs'), 'warning');
                        $('#rcmfd_spamaddress').addClass(rcmail.env.sauserprefs_input_error_class);
                        $('#rcmfd_spamaddress').focus();
                        return false;
                    }
                    else if (!sauserprefs_check_email(address_input)) {
                        rcmail.display_message(rcmail.get_label('spamaddresserror','sauserprefs'), 'warning');
                        $('#rcmfd_spamaddress').addClass(rcmail.env.sauserprefs_input_error_class);
                        $('#rcmfd_spamaddress').focus();
                        return false;
                    }
                    else {
                        if (!rcmail.sauserprefs_addressrule_insert_row({'type': $('#rcmfd_spamaddressrule').val(), 'desc': $('#rcmfd_spamaddressrule option:selected').text(), 'address': address_input})) {
                            rcmail.display_message(rcmail.get_label('spamaddressexists','sauserprefs'), 'warning');
                            $('#rcmfd_spamaddress').addClass(rcmail.env.sauserprefs_input_error_class);
                            $('#rcmfd_spamaddress').focus();
                            return false;
                        }
                        else {
                            $('#rcmfd_spamaddress').removeClass(rcmail.env.sauserprefs_input_error_class);
                            $('#rcmfd_spamaddress').val('');
                        }
                    }
                }, true);

                rcmail.register_command('plugin.sauserprefs.welcomelist_delete_all', function() {
                    rcmail.confirm_dialog(rcmail.get_label('spamaddressdeleteall','sauserprefs'), 'delete', function(e, ref) {
                            $.each($('#address-rules-table tbody tr'), function() { ref.sauserprefs_addressrule_delete_row(this); });
                        });
                    return false;
                }, true);

                rcmail.register_command('plugin.sauserprefs.import_welcomelist', function(props) {
                    rcmail.env.sauserprefs_welcomelist = rcmail.set_busy(true, 'sauserprefs.importingaddresses');
                    rcmail.http_request('plugin.sauserprefs.welcomelist_import', { _sources: props }, rcmail.env.sauserprefs_welcomelist, 'POST');
                    return false;
                }, true);

                rcmail.register_command('plugin.sauserprefs.purge_bayes', function() {
                    rcmail.confirm_dialog(rcmail.get_label('purgebayesconfirm','sauserprefs'), 'delete', function(e, ref) {
                            var lock = ref.set_busy(true, 'sauserprefs.purgingbayes');
                            ref.http_request('plugin.sauserprefs.purge_bayes', '', lock);
                        });
                    return false;
                }, true);

                rcmail.register_command('plugin.sauserprefs.save', function() { rcmail.gui_objects.editform.submit(); }, true);

                rcmail.register_command('plugin.sauserprefs.default', function() {
                    var reset_func = function(e, ref) {
                        $('#rcmfd_spamthres').val(''); // Score
                        $('#rcmfd_spamsubject').val(ref.env.rewrite_header_Subject); // Subject tag

                        // Languages
                        var dlangs = " " + ref.env.ok_languages + " ";
                        $.each($('input[name="_spamlang[]"]'), function(idx) {
                            $(this).prop('checked', false);
                            $('[id^=spam_lang_]').eq(idx).attr('title', ref.get_label('disabled', 'sauserprefs')).removeClass('lang-enabled').addClass('lang-disabled');

                            if (dlangs.indexOf(" " + $(this).val() + " ") > -1 || ref.env.ok_languages == "all") {
                                $(this).prop('checked', true);
                                $('[id^=spam_lang_]').eq(idx).attr('title', ref.get_label('enabled', 'sauserprefs')).removeClass('lang-disabled').addClass('lang-enabled');
                            }
                        });

                        // Defaults for checkboxes
                        var checkboxes = {
                            // Tests
                            'rcmfd_spamuserazor1': ref.env.use_razor1 == '1',
                            'rcmfd_spamuserazor2': ref.env.use_razor2 == '1',
                            'rcmfd_spamusepyzor': ref.env.use_pyzor == '1',
                            'rcmfd_spamusedcc': ref.env.use_dcc == '1',
                            'rcmfd_spamskiprblchecks': ref.env.skip_rbl_checks == '0',
                            // Bayes
                            'rcmfd_spamusebayes': ref.env.use_bayes == '1',
                            'rcmfd_spambayesautolearn': ref.env.bayes_auto_learn == '1',
                            'rcmfd_spambayesrules': ref.env.use_bayes_rules == '1',
                            // Headers
                            'rcmfd_spamfoldheaders': ref.env.fold_headers == '1',
                            'rcmfd_spamlevelstars': ref.env.add_header_all_Level != '',
                            // Report
                            'rcmfd_spamreport_0': ref.env.report_safe == '0',
                            'rcmfd_spamreport_1': ref.env.report_safe == '1',
                            'rcmfd_spamreport_2': ref.env.report_safe == '2',
                        };
                        $.each(checkboxes, function(id, checked) { $('#' + id).prop('checked', checked); });

                        $('#rcmfd_bayesnonspam,#rcmfd_bayesspam').val(''); // Bayes non spam/spam score
                        $('#rcmfd_spamlevelchar').val(ref.env.add_header_all_Level.substr(7, 1)); // Spam level char

                        // Delete welcomelist
                        $.each($('#address-rules-table tbody tr'), function() { ref.sauserprefs_addressrule_delete_row(this) });

                        // Toggle dependant fields
                        ref.sauserprefs_toggle_level_char($('#rcmfd_spamlevelstars'));
                        ref.sauserprefs_toggle_bayes($('#rcmfd_spamusebayes'));
                        ref.sauserprefs_toggle_bayes_auto($('#rcmfd_spambayesautolearn'));
                    }

                    rcmail.confirm_dialog(rcmail.get_label('usedefaultconfirm','sauserprefs'), 'sauserprefs.saupusedefault', reset_func, { button_class: 'delete saupusedefault' });
                }, true);

                rcmail.register_command('plugin.sauserprefs.table_sort', function(props, obj) {
                    var id = props;
                    var idx = $(obj).parent('th').index();
                    var asc = !$(obj).parent('th').hasClass(rcmail.env.sauserprefs_table_sort_asc);

                    rcmail.sauserprefs_table_sort(id, idx, asc);

                    $(obj).parents('thead').first().find('th').removeClass(rcmail.env.sauserprefs_table_sort_asc).removeClass(rcmail.env.sauserprefs_table_sort_desc);
                    $(obj).parent('th').addClass(asc ? rcmail.env.sauserprefs_table_sort_asc : rcmail.env.sauserprefs_table_sort_desc);

                    rcmail.env.sauserprefs_sort[id] = [idx, asc.toString()];
                    rcmail.save_pref({name: 'sauserprefs_sort', value: JSON.stringify(rcmail.env.sauserprefs_sort), env: true});

                    return false;
                }, true);

                rcmail.enable_command('plugin.sauserprefs.save','plugin.sauserprefs.default', true);
            }
        });

        if (rcmail.env.action == 'plugin.sauserprefs') {
            rcmail.section_select = function(list) {
                var win, id = list.get_single_selection();

                if (id && (win = this.get_frame_window(this.env.contentframe))) {
                    this.location_href({_action: 'plugin.sauserprefs.edit', _section: id, _framed: 1}, win, true);
                }
            }
        }
    }
});