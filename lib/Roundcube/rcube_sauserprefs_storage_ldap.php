<?php

/**
 * SAUserPrefs storage class
 *
 * Class to handle the LDAP work for SAUserPrefs
 *
 * @author Adrian Gruntkowski
 */
class rcube_sauserprefs_storage_ldap
{
    private $scope_to_function = array(
        'sub' => 'ldap_search',
        'onelevel' => 'ldap_list',
        'base' => 'ldap_find'
    );
    
    private $preference_offsets = array(
        'rewrite_header' => 2,
        'add_header' => 3
    );

    private $uri;
    private $bind_dn;
    private $password;
    private $base_dn;
    private $scope;
    private $attribute;
    private $filter_expr;
    private $db;
    
	function __construct($db_config, $sa_user) 
	{
        $this->uri = $db_config['uri'];
        $this->use_tls = $db_config['use_tls'];
        $this->protocol_version = $db_config['protocol_version'];
        $this->bind_dn = $db_config['bind_dn'];
        $this->password = $db_config['password'];
        $this->base_dn = $db_config['base_dn'];
        $this->scope = $db_config['scope'];
        $this->sa_user = $sa_user;
        $this->attribute = $db_config['attribute'];
        $this->filter_expr = $db_config['filter_expr'];
	}
    
    private function _flatten_preferences($prefs)
    {
        $flat_prefs = array();
        
        foreach ($prefs as $preference => $value) {
            if ($preference == 'addresses') {
                foreach ($value as $address) {
                    $flat_prefs[] = array($address['field'], $address['value']);
                }
            } else {
                $flat_prefs[] = array($preference, $value);
            }
        }

        return $flat_prefs;
    }
    
    private function _write_preferences($dn, $prefs)
    {
        $rows = array();
        foreach ($prefs as $pref) {
            // avoid adding empty preferences
            if (count($pref) == 2 && strlen($pref[0]) != 0) {
                list($preference, $value) = $pref;
                $rows[] = sauserprefs::map_pref_name($preference).' '.$value;
            }
        }

        $entry = array();
        $entry[$this->attribute] = $rows;

        $result = ldap_modify($this->db, $dn, $entry);

        rcube::write_log('errors', 'sauserprefs error: cannot write preferences for ' . $this->sa_user);
        
        return $result === true;
    }
    
    private function _extract_preferences($dn, $entry)
    {
        $deprecated_present = false;
        $prefs = array();
        foreach ($entry as $value) {
            list($pref_name, $deprecated, $pref_value) = $this->_extract_preference($value);
            $deprecated_present = $deprecated_present || $deprecated;
            $prefs[] = array($pref_name, $pref_value);
        }
        
        if ($deprecated_present) {
            $this->_write_preferences($dn, $prefs);
        }
        
        return $prefs;
    }

    private function _extract_preference($value)
    {
        list($orig_pref_name, $pref_value) = preg_split('/\s+/', $value, 2);
        
        // taking into account exceptions in preference names
        if (!empty($this->preference_offsets[$orig_pref_name])) {
            $limit = $this->preference_offsets[$orig_pref_name] + 1;
            $parts = preg_split('/\s+/', $value, $limit);
            $pref_value = array_pop($parts);
            $orig_pref_name = implode(' ', $parts);
        }

        $pref_name = sauserprefs::map_pref_name($orig_pref_name, true);
        $deprecated = $orig_pref_name != $pref_name;
        return array($pref_name, $deprecated, $pref_value);
    }
    
    private function _ldap_search($user)
    {
        $search_function = $this->scope_to_function[$this->scope];
        
        $result = $search_function(
            $this->db, 
            $this->base_dn, 
            preg_replace('/__USERNAME__/', $user, $this->filter_expr), 
            array('dn', $this->attribute)
        );

        $dn = null;
        $attributes = array();

        if ($result !== false) {
            $entries = ldap_get_entries($this->db, $result);
            $dn = $entries[0]['dn'];
            $attributes = $entries[0][$this->attribute];
            // remove meta data from output
            unset($attributes['count']);
        } 

        return array($dn, $attributes);
    }

	function load_prefs($user)
	{
		$this->_db_connect();
		$prefs = array();

        list($dn, $attributes) = $this->_ldap_search($user);

        $flat_prefs = array();
        if ($dn !== null) {
            $flat_prefs = $this->_extract_preferences($dn, $attributes);
        }

        $prefs = array();
        foreach ($flat_prefs as $pref_entry) {
            list($pref_name, $pref_value) = $pref_entry;
            
			if ($pref_name == 'whitelist_from' || $pref_name == 'blacklist_from' || $pref_name == 'whitelist_to') {
				$prefs['addresses'][] = array('field' => $pref_name, 'value' => $pref_value);
			} else {
				$prefs[$pref_name] = $pref_value;
            }
        }
        
        return $prefs;
	}
    
	function save_prefs($new_prefs, $cur_prefs, $global_prefs)
	{
		$this->_db_connect();
        
        $effective_prefs = array_merge($global_prefs, $cur_prefs);
        
        foreach ($new_prefs as $preference => $value) {
			if ($preference == 'addresses') {
                if (empty($effective_prefs['addresses'])) {
                    $effective_prefs['addresses'] = array();
                }

				foreach ($value as $address) {
                    $field = $address['field'];
                    $value = $address['value'];
                    $current_address = array('field' => $field, 'value' => $value);
                    if ($address['action'] == 'INSERT') {
                        $effective_prefs['addresses'][] = $current_address;
                    } elseif ($address['action'] == 'DELETE') {
                        $idx_to_remove = array_search($current_address, $effective_prefs['addresses']);
                        unset($effective_prefs['addresses'][$idx_to_remove]);
                    }
                }
            } elseif (array_key_exists($preference, $cur_prefs) && ($value == "" || $value == $global_prefs[$preference])) {
                // removing existing value
                unset($effective_prefs[$preference]);
            } elseif (array_key_exists($preference, $cur_prefs) && $value != $cur_prefs[$preference]) {
                // updating existing value
                $effective_prefs[$preference] = $value;
            } elseif (!array_key_exists($preference, $cur_prefs) && $value != $global_prefs[$preference]) {
                // inserting new value
                $effective_prefs[$preference] = $value;
            }
        }
        
        $flat_prefs = $this->_flatten_preferences($effective_prefs);

        list($dn, $_) = $this->_ldap_search($this->sa_user);
        $result = $this->_write_preferences($dn, $flat_prefs);
        return $result;
	}

	function whitelist_add($emails)
	{
		$this->_db_connect();
        
        $field = 'whitelist_from';
        
        list($dn, $attributes) = $this->_ldap_search($this->sa_user);

        if ($dn !== null) {
            $flat_prefs = $this->_extract_preferences($dn, $attributes);

            foreach ($emails as $email) {
                $new_value = array($field, $email);

                // add only if entry doesn't already exist
                if (array_search($new_value, $flat_prefs) === false) {
                    $flat_prefs[] = $new_value;
                }
            }
            
            $this->_write_preferences($dn, $flat_prefs);
        }
	}

	function whitelist_delete($emails)
	{
		$this->_db_connect();

        $field = 'whitelist_from';
        
        list($dn, $attributes) = $this->_ldap_search($this->sa_user);

        if ($dn !== null) {
            $flat_prefs = $this->_extract_preferences($dn, $attributes);

            foreach ($emails as $email) {
                $value_to_remove = array($field, $email);
                $idx_to_remove = array_search($value_to_remove, $flat_prefs);

                if ($idx_to_remove !== false) {
                    unset($flat_prefs[$idx_to_remove]);
                }
            }
            
            $this->_write_preferences($dn, $flat_prefs);
        }
	}

	function purge_bayes()
	{
        // not implemented for LDAP
        return false;
	}

	private function _db_connect()
	{
        if ($this->db) {
            return;
        }

        $ldap_connection = ldap_connect($this->uri);
        
        ldap_set_option($ldap_connection, LDAP_OPT_PROTOCOL_VERSION, $this->protocol_version);
        
        $ldap_bind = false;
        if ($ldap_connection) {
            if (!$this->use_tls || ($this->use_tls && ldap_start_tls($ldap_connection))) {
                $ldap_bind = ldap_bind($ldap_connection, $this->bind_dn, $this->password);
            }
        }
        
        if (!$ldap_connection || !$ldap_bind) {
            if ($ldap_connection) {
                ldap_unbind($ldap_connection);
            }
            rcube::raise_error(array(
                'code' => 100, 'type' => 'ldap',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Could not connect to LDAP server"
            ),
            false, true);
        }
        
        $this->db = $ldap_connection;
	}
}

?>