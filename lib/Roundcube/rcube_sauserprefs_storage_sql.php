<?php

/**
 * SAUserPrefs storage class
 *
 * Class to handle the SQL work for SAUserPrefs
 *
 * @author Philip Weir
 *
 * Copyright (C) Philip Weir
 *
 * This program is a Roundcube (https://roundcube.net) plugin.
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
class rcube_sauserprefs_storage_sql extends rcube_sauserprefs_storage
{
    private $db;
    private $db_dsnw;
    private $db_dsnr;
    private $db_persistent;
    private $table_name;
    private $username_field;
    private $preference_field;
    private $value_field;
    private $bayes_delete_query;

    /**
     * Object constructor
     *
     * @param mixed $config Roundcube config object
     */
    public function __construct($config)
    {
        $this->db_dsnw = $config->get('sauserprefs_db_dsnw');
        $this->db_dsnr = $config->get('sauserprefs_db_dsnr');
        $this->db_persistent = $config->get('sauserprefs_db_persistent');
        $this->table_name = $config->get('sauserprefs_sql_table_name');
        $this->username_field = $config->get('sauserprefs_sql_username_field');
        $this->preference_field = $config->get('sauserprefs_sql_preference_field');
        $this->value_field = $config->get('sauserprefs_sql_value_field');
        $this->bayes_delete_query = $config->get('sauserprefs_bayes_delete_query');
    }

    /**
     * Retrieve all SpamAssassin preferences
     *
     * @param string $user sauserprefs_global_userid
     *
     * @return array Array of preferences in format [$pref_name => $pref_value, ...]
     */
    public function load_prefs($user)
    {
        $this->_db_connect('r');
        $prefs = [];

        $sql_result = $this->db->query(
            "SELECT `{$this->preference_field}`, `{$this->value_field}` FROM `{$this->table_name}` WHERE `{$this->username_field}` = ?;",
            $user);

        while ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
            $pref_name = $sql_arr[$this->preference_field];
            $pref_name = sauserprefs::map_pref_name($pref_name);
            $pref_value = $sql_arr[$this->value_field];

            if ($pref_name == 'whitelist_from' || $pref_name == 'blacklist_from' || $pref_name == 'whitelist_to' ||
                $pref_name == 'welcomelist_from' || $pref_name == 'blocklist_from' || $pref_name == 'welcomelist_to') {
                $prefs['addresses'][] = ['field' => $pref_name, 'value' => $pref_value];
            }
            else {
                $prefs[$pref_name] = $pref_value;
            }

            // update deprecated prefs in db
            if ($sql_arr[$this->preference_field] != $pref_name && (sauserprefs::$SAv4 || (!sauserprefs::$SAv4 && !array_key_exists($sql_arr[$this->preference_field], sauserprefs::$SAv4_prefs)))) {
                $this->_db_connect('w');

                $this->db->query(
                    "UPDATE `{$this->table_name}` SET `{$this->preference_field}` = ? WHERE `{$this->username_field}` = ? AND `{$this->preference_field}` = ?;",
                    sauserprefs::map_pref_name($pref_name),
                    $user,
                    $sql_arr[$this->preference_field]);
            }
        }

        return $prefs;
    }

    /**
     * Save new SpamAssassin preferences
     *
     * @param string $user_id      sauserprefs_userid
     * @param array  $new_prefs    Array of new preferences to be saved
     * @param array  $cur_prefs    Array of current preferences for comparison with $new_prefs (user_prefs + global_prefs)
     * @param array  $global_prefs Array of global preferences for comparison with $new_prefs
     *
     * @return bool True on success, False on error
     */
    public function save_prefs($user_id, $new_prefs, $cur_prefs, $global_prefs)
    {
        $result = true;

        // process prefs
        $actions = [];
        foreach ($new_prefs as $preference => $value) {
            if ($preference == 'addresses') {
                foreach ($value as $address) {
                    if (!empty($address['action']) && !empty($address['value'])) {
                        $actions[$address['action']][] = $address;
                    }
                }
            }
            elseif (array_key_exists($preference, $cur_prefs)) {
                if ($value == "" || $value == $global_prefs[$preference]) {
                    $actions['DELETE'][] = ['field' => $preference, 'value' => null];
                }
                elseif ($value != $cur_prefs[$preference]) {
                    $actions['UPDATE'][] = ['field' => $preference, 'value' => $value];
                }
            }
            elseif ($value != $global_prefs[$preference]) {
                $actions['INSERT'][] = ['field' => $preference, 'value' => $value];
            }
        }

        if (!empty($actions)) {
            $this->_db_connect('w');
            $result = false;
            foreach ($actions as $type => $prefs) {
                foreach ($prefs as $pref) {
                    if ($type == 'INSERT') {
                        $this->db->query(
                            "INSERT INTO `{$this->table_name}` (`{$this->username_field}`, `{$this->preference_field}`, `{$this->value_field}`) VALUES (?, ?, ?);",
                            $user_id,
                            $pref['field'],
                            $pref['value']);

                        $result = $this->db->affected_rows();

                        if (!$result) {
                            rcube::write_log('errors', 'sauserprefs error: cannot insert "' . $pref['field'] . '" = "' . $pref['value'] . '" for ' . $user_id);
                            break;
                        }
                    }
                    elseif ($type == 'UPDATE') {
                        $this->db->query(
                            "UPDATE `{$this->table_name}` SET `{$this->value_field}` = ? WHERE `{$this->username_field}` = ? AND `{$this->preference_field}` = ?;",
                            $pref['value'],
                            $user_id,
                            $pref['field']);

                        $result = $this->db->affected_rows();

                        if (!$result) {
                            rcube::write_log('errors', 'sauserprefs error: cannot update "' . $pref['field'] . '" = "' . $pref['value'] . '" for ' . $user_id);
                            break;
                        }
                    }
                    elseif ($type == 'DELETE') {
                        $sql = "DELETE FROM `{$this->table_name}` WHERE `{$this->username_field}` = ? AND `{$this->preference_field}` = ?";
                        $vals = [$user_id, $pref['field']];
                        $msg = '"' . $pref['field'] . '"';

                        if (!empty($pref['value'])) {
                            $sql .= " AND `{$this->value_field}` = ?";
                            $vals[] = $pref['value'];
                            $msg .= ' = "' . $pref['value'] . '"';
                        }

                        $this->db->query($sql, $vals);
                        $result = $this->db->affected_rows();

                        if (!$result) {
                            rcube::write_log('errors', 'sauserprefs error: cannot delete ' . $msg . ' for ' . $user_id);
                            break;
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Purge learnt Bayes data
     *
     * @param string $user_id sauserprefs_userid
     */
    public function purge_bayes($user_id)
    {
        $result = false;
        $this->_db_connect('w');
        $queries = !is_array($this->bayes_delete_query) ? [$this->bayes_delete_query] : $this->bayes_delete_query;

        foreach ($queries as $sql) {
            $sql = str_replace('%u', $this->db->quote($user_id, 'text'), $sql);
            $this->db->query($sql);

            if ($this->db->is_error()) {
                break;
            }
        }

        if (!$this->db->is_error()) {
            $result = true;
        }

        return $result;
    }

    /**
     * Connect to appropriate database depending on the operation
     *
     * @param string $mode Connection mode (r|w)
     */
    private function _db_connect($mode)
    {
        if (!$this->db) {
            $this->db = rcube_db::factory($this->db_dsnw, $this->db_dsnr, $this->db_persistent);
        }

        $this->db->set_debug((bool) rcube::get_instance()->config->get('sql_debug'));
        $this->db->db_connect($mode);

        // check DB connections and exit on failure
        if ($err_str = $this->db->is_error()) {
            rcube::raise_error(['code' => 603, 'type' => 'db', 'message' => $err_str], false, true);
        }
    }
}
