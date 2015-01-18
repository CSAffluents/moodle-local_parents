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
 * The local parents lib file.
 *
 * @package   local_parents
 * @copyright 2015 Gilles-Philippe Leblanc <contact@gpleblanc.com> and Maxime Pelletier <maxime.pelletier@educsa.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function local_parents_extends_navigation($root) {
    global $COURSE, $PAGE, $SITE;

    if (!get_config('local_parents', 'version')) {
        return;
    }

    // Not on the home page.
    if ($COURSE->id != $SITE->id) {
	// Verify that user can view this link.
        if (has_capability('moodle/course:manageactivities', $PAGE->context)) {
	    // Retrieve course node, then create the node to add and finally, insert the new node in second place.
	    $coursenode = $root->find($COURSE->id,null);
        $parents = $coursenode->create(get_config('local_parents','link_name'),
                new moodle_url('/local/parents/parents.php', array('filtertype' => 'course', 'id' => $COURSE->id)),
                navigation_node::TYPE_CONTAINER);
	    $array=$coursenode->get_children_key_list();
	    $coursenode->add_node($parents,$array[1]);
        }
    }
}
