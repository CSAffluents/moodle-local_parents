<?php

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
