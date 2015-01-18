<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
* List all parents for a given course
*
* @package    local
* @subpackage parents
* @copyright  Maxime Pelletier <maxime.pelletier@educsa.org>
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die();

//if ($ADMIN->fulltree) {
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_parents', get_string('pluginname', 'local_parents'));
    $settings->add(new admin_setting_heading('local_parents_settings', '', get_string('pluginname_desc', 'local_parents')));

    //--- Link name
    $settings->add(new admin_setting_configtext('local_parents/link_name', get_string('link_name', 'local_parents'), get_string('link_name_desc', 'local_parents'), 'Parents'));

    //--- Role to display
    //$options = get_default_enrol_roles(context_system::instance());
    //$options = role_fix_names(get_all_roles());
    // @TODO Find a way to retrieves only user context roles.
	$options = array();
    $roles = get_all_roles();
	$parent = 0;
	foreach ($roles as $role) {
		
		// Looking for a parent role...
		if (stripos( $role->name, 'parent') !== false) {
            $options[$role->id] = $role->name;
			$parent = $role->id;
		}
	}
        //$parent = get_archetype_roles('parent');
        //$parent = reset($parent);
        $settings->add(new admin_setting_configselect('local_parents/parent_role', get_string('parent_role', 'local_parents'), get_string('parent_role_desc', 'local_parents'), $parent, $options));

 $ADMIN->add('localplugins', $settings);

}

