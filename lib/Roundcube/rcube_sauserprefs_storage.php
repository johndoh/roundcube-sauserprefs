<?php

/**
 * SAUserPrefs base storage class
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
abstract class rcube_sauserprefs_storage
{
    protected $config;

    /**
     * Object constructor
     *
     * @param mixed $config Roundcube config object
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Retrieve all SpamAssassin preferences
     *
     * @param string $user sauserprefs_global_userid
     *
     * @return array Array of preferences in format [$pref_name => $pref_value, ...]
     */
    abstract public function load_prefs($user);

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
    abstract public function save_prefs($user_id, $new_prefs, $cur_prefs, $global_prefs);

    /**
     * Purge learnt Bayes data
     *
     * @param string $user_id sauserprefs_userid
     */
    abstract public function purge_bayes($user_id);
}
