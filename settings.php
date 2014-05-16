<?php
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
	$options = array();
        $roles = get_all_roles();
	$parent = 0;
	foreach ($roles as $role) {
		$options[$role->id] = $role->name;
		// Looking for a parent role...
		if (stripos( $role->name, 'parent') !== false) {
			$parent = $role->id;
		}
	}
        //$parent = get_archetype_roles('parent');
        //$parent = reset($parent);
        $settings->add(new admin_setting_configselect('local_parents/parent_role', get_string('parent_role', 'local_parents'), get_string('parent_role_desc', 'local_parents'), $parent, $options));

 $ADMIN->add('localplugins', $settings);

}

