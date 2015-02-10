<?php

/**
 * SAUserPrefs storage class
 *
 * Class to handle the SQL work for SAUserPrefs
 *
 * @author Philip Weir
 *
 * Copyright (C) 2012-2014 Philip Weir
 *
 * This program is a Roundcube (http://www.roundcube.net) plugin.
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
class rcube_sauserprefs_storage_sql
{
	private $db;
	private $db_dsnw;
	private $db_dsnr;
	private $db_persistent;
	private $sa_user;
	private $table_name;
	private $username_field;
	private $preference_field;
	private $value_field;
	private $bayes_delete_query;

	function __construct($db_dsnw, $db_dsnr, $db_persistent, $table_name, $username_field, $preference_field, $value_field, $bayes_delete_query, $sa_user)
	{
		$this->db_dsnw = $db_dsnw;
		$this->db_dsnr = $db_dsnr;
		$this->db_persistent = $db_persistent;
		$this->sa_user = $sa_user;
		$this->table_name = $table_name;
		$this->username_field = $username_field;
		$this->preference_field = $preference_field;
		$this->value_field = $value_field;
		$this->bayes_delete_query = $bayes_delete_query;
	}

	function load_prefs($user)
	{
		$this->_db_connect('r');
		$prefs = array();

		$sql_result = $this->db->query(
			"SELECT `{$this->preference_field}`, `{$this->value_field}` FROM `{$this->table_name}` WHERE `{$this->username_field}` = ?;",
			$user);

		while ($sql_result && ($sql_arr = $this->db->fetch_assoc($sql_result))) {
			$pref_name = $sql_arr[$this->preference_field];
			$pref_name = sauserprefs::map_pref_name($pref_name);
			$pref_value = $sql_arr[$this->value_field];

			if ($pref_name == 'whitelist_from' || $pref_name == 'blacklist_from' || $pref_name == 'whitelist_to')
				$prefs['addresses'][] = array('field' => $pref_name, 'value' => $pref_value);
			else
				$prefs[$pref_name] = $pref_value;

			// update deprecated prefs in db
			if ($sql_arr[$this->preference_field] != $pref_name) {
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

	function save_prefs($new_prefs, $cur_prefs, $global_prefs)
	{
		$this->_db_connect('w');
		$result = true;

		// save prefs
		foreach ($new_prefs as $preference => $value) {
			if ($preference == 'addresses') {
				foreach ($value as $address) {
					if ($address['action'] == "DELETE") {
						$result = false;

						$this->db->query(
							"DELETE FROM `{$this->table_name}` WHERE `{$this->username_field}` = ? AND `{$this->preference_field}` = ? AND `{$this->value_field}` = ?;",
							$this->sa_user,
							$address['field'],
							$address['value']);

						$result = $this->db->affected_rows();

						if (!$result) {
							rcube::write_log('errors', 'sauserprefs error: cannot delete "' . $prefs[$idx] . '" = "' .  $vals[$idx] . '" for ' . $this->sa_user);
							break;
						}
					}
					elseif ($address['action'] == "INSERT") {
						$result = false;

						$this->db->query(
							"INSERT INTO `{$this->table_name}` (`{$this->username_field}`, `{$this->preference_field}`, `{$this->value_field}`) VALUES (?, ?, ?);",
							$this->sa_user,
							$address['field'],
							$address['value']);

						$result = $this->db->affected_rows();

						if (!$result) {
							rcube::write_log('errors', 'sauserprefs error: cannot insert "' . $prefs[$idx] . '" = "' .  $vals[$idx] . '" for ' . $this->sa_user);
							break;
						}
					}
				}
			}
			elseif (array_key_exists($preference, $cur_prefs) && ($value == "" || $value == $global_prefs[$preference])) {
				$result = false;

				$this->db->query(
					"DELETE FROM `{$this->table_name}` WHERE `{$this->username_field}` = ? AND `{$this->preference_field}` = ?;",
					$this->sa_user,
					$preference);

				$result = $this->db->affected_rows();

				if (!$result) {
					rcube::write_log('errors', 'sauserprefs error: cannot delete "' . $preference . '" for "' . $this->sa_user);
					break;
				}
			}
			elseif (array_key_exists($preference, $cur_prefs) && $value != $cur_prefs[$preference]) {
				$result = false;

				$this->db->query(
					"UPDATE `{$this->table_name}` SET `{$this->value_field}` = ? WHERE `{$this->username_field}` = ? AND `{$this->preference_field}` = ?;",
					$value,
					$this->sa_user,
					$preference);

				$result = $this->db->affected_rows();

				if (!$result) {
					rcube::write_log('errors', 'sauserprefs error: cannot update "' . $preference . '" = "' .  $value . '" for ' . $this->sa_user);
					break;
				}
			}
			elseif (!array_key_exists($preference, $cur_prefs) && $value != $global_prefs[$preference]) {
				$result = false;

				$this->db->query(
					"INSERT INTO `{$this->table_name}` (`{$this->username_field}`, `{$this->preference_field}`, `{$this->value_field}`) VALUES (?, ?, ?);",
					$this->sa_user,
					$preference,
					$value);

				$result = $this->db->affected_rows();

				if (!$result) {
					rcube::write_log('errors', 'sauserprefs error: cannot insert "' . $preference . '" = "' .  $value . '" for ' . $this->sa_user);
					break;
				}
			}
		}

		return $result;
	}

	function whitelist_add($emails)
	{
		$this->_db_connect('w');

		foreach ($emails as $email) {
			// check address is not already whitelisted
			$sql_result = $this->db->query(
							"SELECT `{$this->value_field}` FROM `{$this->table_name}` WHERE `{$this->username_field}` = ? AND `{$this->preference_field}` = ? AND `{$this->value_field}` = ?;",
							$this->sa_user,
							sauserprefs::map_pref_name('whitelist_from'),
							$email);

			if (!$this->db->fetch_array($sql_result))
				$this->db->query(
					"INSERT INTO `{$this->table_name}` (`{$this->username_field}`, `{$this->preference_field}`, `{$this->value_field}`) VALUES (?, ?, ?);",
					$this->sa_user,
					sauserprefs::map_pref_name('whitelist_from'),
					$email);
		}
	}

	function whitelist_delete($emails)
	{
		$this->_db_connect('w');

		foreach ($emails as $email) {
			$this->db->query(
				"DELETE FROM `{$this->table_name}` WHERE `{$this->username_field}` = ? AND `{$this->preference_field}` = ? AND `{$this->value_field}` = ?;",
				$this->sa_user,
				sauserprefs::map_pref_name('whitelist_from'),
				$email);
		}
	}

	function purge_bayes()
	{
		$this->_db_connect('w');
		$queries = !is_array($this->bayes_delete_query) ? array($this->bayes_delete_query) : $this->bayes_delete_query;

		foreach ($queries as $sql) {
			$sql = str_replace('%u', $this->db->quote($this->sa_user, 'text'), $sql);
			$this->db->query($sql);

			if ($this->db->is_error())
				break;
		}

		if (!$this->db->is_error())
			return true;
		else
			return false;
	}

	private function _db_connect($mode)
	{
		if (!$this->db)
			$this->db = rcube_db::factory($this->db_dsnw, $this->db_dsnr, $this->db_persistent);

		$this->db->set_debug((bool)rcube::get_instance()->config->get('sql_debug'));
		$this->db->db_connect($mode);

		// check DB connections and exit on failure
		if ($err_str = $this->db->is_error()) {
			rcube::raise_error(array(
				'code' => 603,
				'type' => 'db',
				'message' => $err_str), false, true);
		}
	}
}

?>