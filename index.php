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
 * Lists all the users within a given course.
 *
 * @package   local_parents
 * @copyright 2015 Gilles-Philippe Leblanc <contact@gpleblanc.com> and Maxime Pelletier <maxime.pelletier@educsa.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->libdir.'/filelib.php');

define('USER_SMALL_CLASS', 20);   // Below this is considered small
define('USER_LARGE_CLASS', 200);  // Above this is considered large
define('DEFAULT_PAGE_SIZE', 10);
define('SHOW_ALL_PAGE_SIZE', 1000);
define('MODE_BRIEF', 0);
define('MODE_USERDETAILS', 1);

$page         = optional_param('page', 0, PARAM_INT);                     // which page to show
$perpage      = optional_param('perpage', SHOW_ALL_PAGE_SIZE, PARAM_INT);  // how many per page
$mode         = optional_param('mode', NULL, PARAM_INT);                  // use the MODE_ constants
$accesssince  = optional_param('accesssince',0,PARAM_INT);                // filter by last access. -1 = never
$search       = optional_param('search','',PARAM_RAW);                    // make sure it is processed with p() or s() when sending to output!
$roleid       = optional_param('roleid', 0, PARAM_INT);                   // optional roleid, 0 means all enrolled users (or all on the frontpage)

$contextid    = optional_param('contextid', 0, PARAM_INT);                // one of this or
$courseid     = optional_param('id', 0, PARAM_INT);                       // this are required

$PAGE->set_url('/local/parents/index.php', array(
        'page' => $page,
        'perpage' => $perpage,
        'mode' => $mode,
        'accesssince' => $accesssince,
        'search' => $search,
        'roleid' => $roleid,
        'contextid' => $contextid,
        'id' => $courseid));

if ($contextid) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($context->contextlevel != CONTEXT_COURSE) {
        print_error('invalidcontext');
    }
    $course = $DB->get_record('course', array('id'=>$context->instanceid), '*', MUST_EXIST);
} else {
    $course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
    $context = context_course::instance($course->id, MUST_EXIST);
}
// Not needed anymore.
unset($contextid);
unset($courseid);

require_login($course);

$systemcontext = context_system::instance();
$isfrontpage = ($course->id == SITEID);

$frontpagectx = context_course::instance(SITEID);

if ($isfrontpage) {
    print_error('This page is not intended to be used for whole site');
} else {
    $PAGE->set_pagelayout('incourse');
    require_capability('moodle/course:viewparticipants', $context);
}

$rolenamesurl = new moodle_url("$CFG->wwwroot/local/parents/index.php?contextid=$context->id&sifirst=&silast=");

$allroles = get_all_roles();
$roles = get_profile_roles($context);
$allrolenames = array();
$rolenames = array(0 => get_string('allparents', 'local_parents'));

foreach ($allroles as $role) {
    $allrolenames[$role->id] = strip_tags(role_get_name($role, $context));   // Used in menus etc later on
    $rolenames[$role->id] = $allrolenames[$role->id];
}

// On selectionne le role qui a ete defini dans la config.
$roleid = get_config('local_parents','parent_role');

// Make sure other roles may not be selected by any means.
if (empty($rolenames[$roleid])) {
    print_error('noparents', 'local_parents');
}

// No roles to display yet?
// Frontpage course is an exception, on the front page course we should display all users.
if (empty($rolenames)) {
    if (has_capability('moodle/role:assign', $context)) {
        redirect($CFG->wwwroot.'/'.$CFG->admin.'/roles/assign.php?contextid=' . $context->id);
    } else {
        print_error('noparents', 'local_parents');
    }
}

$bulkoperations = has_capability('moodle/course:bulkmessaging', $context);

$strnever = get_string('never');

$datestring = new stdClass();
$datestring->year  = get_string('year');
$datestring->years = get_string('years');
$datestring->day   = get_string('day');
$datestring->days  = get_string('days');
$datestring->hour  = get_string('hour');
$datestring->hours = get_string('hours');
$datestring->min   = get_string('min');
$datestring->mins  = get_string('mins');
$datestring->sec   = get_string('sec');
$datestring->secs  = get_string('secs');

if ($mode !== NULL) {
    $mode = (int)$mode;
    $SESSION->userindexmode = $mode;
} else if (isset($SESSION->userindexmode)) {
    $mode = (int)$SESSION->userindexmode;
} else {
    $mode = MODE_BRIEF;
}

// Check to see if groups are being used in this course and if so, set $currentgroup to reflect the current group.

$groupmode    = groups_get_course_groupmode($course);   // Groups are being used
$currentgroup = groups_get_course_group($course, true);

if (!$currentgroup) {      // To make some other functions work better later
    $currentgroup  = NULL;
}

$isseparategroups = ($course->groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context));

$PAGE->set_title("$course->shortname: ".get_string('parents','local_parents'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagetype('course-view-' . $course->format);
$PAGE->add_body_class('path-user');                     // So we can style it independently
$PAGE->set_other_editing_capability('moodle/course:manageactivities');
$PAGE->requires->css('/local/parents/styles.css');

$link = new moodle_url("/local/parents/index.php", array('id'=>$course->id));
$PAGE->navbar->add(get_string('parents', 'local_parents'), $link);

echo $OUTPUT->header();

echo '<div class="userlist parentlist">';

if ($isseparategroups and (!$currentgroup) ) {
    // The user is not in the group so show message and exit.
    echo $OUTPUT->heading(get_string("notingroup"));
    echo $OUTPUT->footer();
    exit;
}


// Should use this variable so that we don't break stuff every time a variable is added or changed.
$baseurl = new moodle_url('/local/parents/index.php', array(
        'contextid' => $context->id,
        'roleid' => $roleid,
        'id' => $course->id,
        'perpage' => $perpage,
        'accesssince' => $accesssince,
        'search' => s($search)));

// Setting up tags.
if ($course->id && !$currentgroup) {
    $filtertype = 'course';
    $filterselect = $course->id;
} else {
    $filtertype = 'group';
    $filterselect = $currentgroup;
}



// Get the hidden field list.
if (has_capability('moodle/course:viewhiddenuserfields', $context)) {
    $hiddenfields = array();  // teachers and admins are allowed to see everything.
} else {
    $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
}

// Print settings and things in a table across the top.
$controlstable = new html_table();
$controlstable->attributes['class'] = 'controls';
$controlstable->cellspacing = 0;
$controlstable->data[] = new html_table_row();

// Print my course menus when user is enrolled in courses.
if ($mycourses = enrol_get_my_courses()) {
    $courselist = array();
    $popupurl = new moodle_url('/local/parents/index.php?roleid='.$roleid.'&sifirst=&silast=');
    foreach ($mycourses as $mycourse) {
        $coursecontext = context_course::instance($mycourse->id);
        $courselist[$mycourse->id] = format_string($mycourse->shortname, true, array('context' => $coursecontext));
    }
    $select = new single_select($popupurl, 'id', $courselist, $course->id, array(''=>'choosedots'), 'courseform');
    $select->set_label(get_string('mycourses'));
    $controlstable->data[0]->cells[] = $OUTPUT->render($select);
}

$controlstable->data[0]->cells[] = groups_print_course_menu($course, $baseurl->out(), true);

$formatmenu = array( '0' => get_string('brief'),
                     '1' => get_string('userdetails'));
$select = new single_select($baseurl, 'mode', $formatmenu, $mode, null, 'formatmenu');
$select->set_label(get_string('userlist'));
$userlistcell = new html_table_cell();
$userlistcell->attributes['class'] = 'right';
$userlistcell->text = $OUTPUT->render($select);
$controlstable->data[0]->cells[] = $userlistcell;

echo html_writer::table($controlstable);

// Display info about the group.
if ($currentgroup and (!$isseparategroups or has_capability('moodle/site:accessallgroups', $context))) {
    if ($group = groups_get_group($currentgroup)) {
        if (!empty($group->description) or (!empty($group->picture) and empty($group->hidepicture))) {
            $groupinfotable = new html_table();
            $groupinfotable->attributes['class'] = 'groupinfobox';
            $picturecell = new html_table_cell();
            $picturecell->attributes['class'] = 'left side picture';
            $picturecell->text = print_group_picture($group, $course->id, true, true, false);

            $contentcell = new html_table_cell();
            $contentcell->attributes['class'] = 'content';

            $contentheading = $group->name;
            if (has_capability('moodle/course:managegroups', $context)) {
                $aurl = new moodle_url('/group/group.php', array('id' => $group->id, 'courseid' => $group->courseid));
                $contentheading .= '&nbsp;' . $OUTPUT->action_icon($aurl, new pix_icon('t/edit', get_string('editgroupprofile')));
            }

            $group->description = file_rewrite_pluginfile_urls($group->description, 'pluginfile.php', $context->id, 'group', 'description', $group->id);
            if (!isset($group->descriptionformat)) {
                $group->descriptionformat = FORMAT_MOODLE;
            }
            $options = array('overflowdiv'=>true);
            $contentcell->text = $OUTPUT->heading($contentheading, 3) . format_text($group->description, $group->descriptionformat, $options);
            $groupinfotable->data[] = new html_table_row(array($picturecell, $contentcell));
            echo html_writer::table($groupinfotable);
        }
    }
}

// Define a table showing a list of users in the current role selection.
//$extrachildfields = array( 'childfullname', 'childemail', 'childusername', 'childdepartment', 'childinstitution');
$extrachildfields = array( 'childfullname', 'childemail', 'childusername');
//$extrachildfieldsdesc = array( 'childfullname' => get_string('childfullname','local_parents'), 'childemail' => get_string('childemail','local_parents'), 'childusername' => get_string('childusername','local_parents'), 'childdepartment' => get_string('childdepartment','local_parents'), 'childinstitution' => get_string('childinstitution','local_parents'));
$extrachildfieldsdesc = array( 'childfullname' => get_string('childfullname','local_parents'), 'childemail' => get_string('childemail','local_parents'), 'childusername' => get_string('childusername','local_parents'));

$tablecolumns = array();
$tableheaders = array();

if ($bulkoperations && $mode === MODE_BRIEF) {
    $tablecolumns[] = 'select';
    $tableheaders[] = get_string('select');
}

$tablecolumns[] = 'fullname';
$tableheaders[] = get_string('fullnameuser');

$extrafields = get_extra_user_fields($context);
if ($mode === MODE_BRIEF) {
    foreach ($extrafields as $field) {
        $tablecolumns[] = $field;
        $tableheaders[] = get_user_field_name($field);
    }
    foreach ($extrachildfields as $field) {
        $tablecolumns[] = $field;
        $tableheaders[] = $extrachildfieldsdesc[$field];
    }
}

$table = new \local_parents\parents_table('user-index-parents-'.$course->id);
$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($baseurl->out());
$table->sortable(true);
$table->no_sorting('roles');
$table->no_sorting('groups');
$table->no_sorting('groupings');
$table->no_sorting('select');

$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'parents');
$table->set_attribute('class', 'generaltable generalbox');

$table->set_control_variables(array(
            TABLE_VAR_SORT    => 'ssort',
            TABLE_VAR_HIDE    => 'shide',
            TABLE_VAR_SHOW    => 'sshow',
            TABLE_VAR_IFIRST  => 'sifirst',
            TABLE_VAR_ILAST   => 'silast',
            TABLE_VAR_PAGE    => 'spage'
            ));
$table->setup();

list($esql, $params) = get_enrolled_sql($context, NULL, $currentgroup, true);
$joins = array("FROM {user} u");
$wheres = array();

// performance hacks - we preload user contexts together with accounts.
$ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
$ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)";
$params['contextlevel'] = CONTEXT_USER;
$joins[] = $ccjoin;

$getnamestring = 'parent.firstname, parent.lastname';

if($CFG->version >= 2013111800){
       $getnamestring = get_all_user_name_fields(true, 'parent');
}
$select = "SELECT parent.id, parent.username, $getnamestring,
                  parent.email, parent.city, parent.country, parent.picture,
                  parent.lang, parent.timezone, parent.maildisplay, parent.imagealt, parent.lastaccess,
          u.id AS childid, u.username AS childusername,". $DB->sql_fullname('u.firstname','u.lastname')." AS childfullname,
                  u.email AS childemail, u.department AS childdepartment, u.institution AS childinstitution";
$joins[] = "JOIN ($esql) e ON e.id = u.id"; // course enrolled users only
$joins[] = "LEFT JOIN {role_assignments} ra ON (ra.contextid = ctx.id)";
$joins[] = "LEFT JOIN {role} r ON (ra.roleid = r.id)"; // Context
$joins[] = "JOIN {user} parent ON (ra.userid = parent.id)";

$select .= $ccselect;

// Patch pas propre pour enlever les doublons cause par les comptes pXXXXXXX.
// $wheres[] = "parent.id IN (select pu.id from {user} pu where pu.email = parent.email order by length(pu.username) desc limit 1)";

// Limit list to users with some role only.
if ($roleid) {
    $wheres[] = "ra.roleid = :roleid";
    $params['roleid'] = $roleid;
}

$from = implode("\n", $joins);
if ($wheres) {
    $where = "WHERE " . implode(" AND ", $wheres);
} else {
    $where = "";
}

$totalcount = $DB->count_records_sql("SELECT COUNT(parent.id) $from $where", $params);

if (!empty($search)) {
    $fullname = $DB->sql_fullname('parent.firstname','parent.lastname');
    $wheres[] = "(". $DB->sql_like($fullname, ':search1', false, false) .
                " OR ". $DB->sql_like('email', ':search2', false, false) .
                " OR ". $DB->sql_like('idnumber', ':search3', false, false) .") ";
    $params['search1'] = "%$search%";
    $params['search2'] = "%$search%";
    $params['search3'] = "%$search%";
}

list($twhere, $tparams) = $table->get_sql_where();
if ($twhere) {
    $wheres[] = $twhere;
    $params = array_merge($params, $tparams);
}

$from = implode("\n", $joins);
if ($wheres) {
    $where = "WHERE " . implode(" AND ", $wheres);
} else {
    $where = "";
}

if ($table->get_sql_sort()) {
    $sort = ' ORDER BY '.$table->get_sql_sort();
} else {
    $sort = ' ORDER BY u.firstname';
}

$matchcount = $DB->count_records_sql("SELECT COUNT(parent.id) $from $where", $params);

$table->initialbars(true);
$table->pagesize($perpage, $matchcount);

// List of users at the current visible page - paging makes it relatively short.
$userlist = $DB->get_recordset_sql("$select $from $where $sort", $params, $table->get_page_start(), $table->get_page_size());

if ($roleid > 0) {
    $a = new stdClass();
    $a->number = $totalcount;
    $a->role = $rolenames[$roleid];
    $heading = format_string(get_string('xuserswiththerole', 'role', $a));

    if ($currentgroup and $group) {
        $a->group = $group->name;
        $heading .= ' ' . format_string(get_string('ingroup', 'role', $a));
    }

    if ($accesssince) {
        $a->timeperiod = $timeoptions[$accesssince];
        $heading .= ' ' . format_string(get_string('inactiveformorethan', 'role', $a));
    }

    $heading .= ": $a->number";

    echo $OUTPUT->heading($heading, 3);
} else {
    if (has_capability('moodle/course:enrolreview', $context)) {
        $editlink = $OUTPUT->action_icon(new moodle_url('/enrol/users.php', array('id' => $course->id)),
                                         new pix_icon('t/edit', get_string('edit')));
    } else {
        $editlink = '';
    }
    $strallparents = get_string('allparents', 'local_parents');
    if ($matchcount < $totalcount) {
        echo $OUTPUT->heading($strallparents.get_string('labelsep', 'langconfig').$matchcount.'/'.$totalcount . $editlink, 3);
    } else {
        echo $OUTPUT->heading($strallparents.get_string('labelsep', 'langconfig').$matchcount . $editlink, 3);
    }
}

if ($bulkoperations) {
    echo '<form action="action_redir.php" method="post" id="participantsform">';
    echo '<div>';
    echo '<input type="hidden" name="sesskey" value="'.sesskey().'" />';
    echo '<input type="hidden" name="returnto" value="'.s($PAGE->url->out(false)).'" />';
}

if ($mode === MODE_USERDETAILS) {    // Print simple listing
    if ($totalcount < 1) {
        echo $OUTPUT->heading(get_string('nothingtodisplay'));
    } else {
        if ($totalcount > $perpage) {

            $firstinitial = $table->get_initial_first();
            $lastinitial  = $table->get_initial_last();
            $strall = get_string('all');
            $alpha  = explode(',', get_string('alphabet', 'langconfig'));

            // Bar of first initials.
            echo '<div class="initialbar firstinitial">'.get_string('firstname').' : ';
            if (!empty($firstinitial)) {
                echo '<a href="'.$baseurl->out().'&amp;sifirst=">'.$strall.'</a>';
            } else {
                echo '<strong>'.$strall.'</strong>';
            }
            foreach ($alpha as $letter) {
                if ($letter == $firstinitial) {
                    echo ' <strong>'.$letter.'</strong>';
                } else {
                    echo ' <a href="'.$baseurl->out().'&amp;sifirst='.$letter.'">'.$letter.'</a>';
                }
            }
            echo '</div>';

            // Bar of last initials.
            echo '<div class="initialbar lastinitial">'.get_string('lastname').' : ';
            if (!empty($lastinitial)) {
                echo '<a href="'.$baseurl->out().'&amp;silast=">'.$strall.'</a>';
            } else {
                echo '<strong>'.$strall.'</strong>';
            }
            foreach ($alpha as $letter) {
                if ($letter == $lastinitial) {
                    echo ' <strong>'.$letter.'</strong>';
                } else {
                    echo ' <a href="'.$baseurl->out().'&amp;silast='.$letter.'">'.$letter.'</a>';
                }
            }
            echo '</div>';

            $pagingbar = new paging_bar($matchcount, intval($table->get_page_start() / $perpage), $perpage, $baseurl);
            $pagingbar->pagevar = 'spage';
            echo $OUTPUT->render($pagingbar);
        }

        if ($matchcount > 0) {
            $usersprinted = array();
            foreach ($userlist as $user) {
                if (in_array($user->id, $usersprinted)) { // Prevent duplicates by r.hidden - MDL-13935
                    continue;
                }
                $usersprinted[] = $user->id; // Add new user to the array of users printed

                context_helper::preload_from_record($user);

                $context = context_course::instance($course->id);
                $usercontext = context_user::instance($user->id);

                // Get the hidden field list.
                if (has_capability('moodle/course:viewhiddenuserfields', $context)) {
                    $hiddenfields = array();
                } else {
                    $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
                }
                $table = new html_table();
                $table->attributes['class'] = 'userinfobox';

                $row = new html_table_row();
                /*$row->cells[0] = new html_table_cell();
                $row->cells[0]->attributes['class'] = 'left side';

                $row->cells[0]->text = $OUTPUT->user_picture($user, array('size' => 100, 'courseid'=>$course->id));*/
                $row->cells[1] = new html_table_cell();
                $row->cells[1]->attributes['class'] = 'content';

                $row->cells[1]->text = $OUTPUT->container(fullname($user, has_capability('moodle/site:viewfullnames', $context)), 'username');
                $row->cells[1]->text .= $OUTPUT->container_start('info');

                if (!empty($user->role)) {
                    $row->cells[1]->text .= get_string('role').get_string('labelsep', 'langconfig').$user->role.'<br />';
                }
                if ($user->maildisplay == 1 or ($user->maildisplay == 2 and !isguestuser()) or
                            has_capability('moodle/course:viewhiddenuserfields', $context) or
                            in_array('email', $extrafields)) {
                    $row->cells[1]->text .= get_string('email').get_string('labelsep', 'langconfig').html_writer::link("mailto:$user->email", $user->email) . '<br />';
                }
                foreach ($extrafields as $field) {
                    if ($field === 'email') {
                        // Skip email because it was displayed with different logic above.
                        // This is because this page is intended for students too.
                        continue;
                    }
                    $row->cells[1]->text .= get_user_field_name($field) .
                            get_string('labelsep', 'langconfig') . s($user->{$field}) . '<br />';
                }

                foreach ($extrachildfields as $field) {
                    $row->cells[1]->text .= $extrachildfieldsdesc[$field] .
                            get_string('labelsep', 'langconfig') . s($user->{$field}) . '<br />';
                }

                $row->cells[1]->text .= $OUTPUT->container_end();

                $row->cells[2] = new html_table_cell();
                //$row->cells[2]->attributes['class'] = 'links';
                $row->cells[2]->attributes['class'] = 'right';
                $row->cells[2]->text = '';

                $links = array();

                /*if ($CFG->bloglevel > 0) {
                    $links[] = html_writer::link(new moodle_url('/blog/index.php?userid='.$user->id), get_string('blogs','blog'));
                }

                if (!empty($CFG->enablenotes) and (has_capability('moodle/notes:manage', $context) || has_capability('moodle/notes:view', $context))) {
                    $links[] = html_writer::link(new moodle_url('/notes/index.php?course=' . $course->id. '&user='.$user->id), get_string('notes','notes'));
                }

                if (has_capability('moodle/site:viewreports', $context) or has_capability('moodle/user:viewuseractivitiesreport', $usercontext)) {
                    $links[] = html_writer::link(new moodle_url('/course/user.php?id='. $course->id .'&user='. $user->id), get_string('activity'));
                }

                if ($USER->id != $user->id && !\core\session\manager::is_loggedinas() && has_capability('moodle/user:loginas', $context) && !is_siteadmin($user->id)) {
                    $links[] = html_writer::link(new moodle_url('/course/loginas.php?id='. $course->id .'&user='. $user->id .'&sesskey='. sesskey()), get_string('loginas'));
                }

                $links[] = html_writer::link(new moodle_url('/user/view.php?id='. $user->id .'&course='. $course->id), get_string('fullprofile') . '...');
                */
                $row->cells[2]->text .= implode('', $links);

                if ($bulkoperations) {
                    $row->cells[2]->text .= '<br /><input type="checkbox" class="usercheckbox" name="user'.$user->id.'" /> ';
                }
                $table->data = array($row);
                echo html_writer::table($table);
            }

        } else {
            echo $OUTPUT->heading(get_string('nothingtodisplay'));
        }
    }

} else {
    // Print detailed listing.
    //echo 'MODE_BRIEF......';
    $timeformat = get_string('strftimedate');

    if ($userlist)  {

        $usersprinted = array();
        foreach ($userlist as $user) {
            // Prevent duplicates by r.hidden - MDL-13935.
            if (in_array($user->id, $usersprinted)) { 
                continue;
            }
            // Add new user to the array of users printed.
            $usersprinted[] = $user->id; 

            context_helper::preload_from_record($user);

            $usercontext = context_user::instance($user->id);

            if ($piclink = ($USER->id == $user->id || has_capability('moodle/user:viewdetails', $context) || has_capability('moodle/user:viewdetails', $usercontext))) {
                $profilelink = '<strong><a href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&amp;course='.$course->id.'">'.fullname($user).'</a></strong>';
            } else {
                $profilelink = '<strong>'.fullname($user).'</strong>';
            }

            //$data = array ('<input type="checkbox" class="usercheckbox" name="user'.$user->id.'" />',$OUTPUT->user_picture($user, array('size' => 35, 'courseid'=>$course->id)), $profilelink);
            $data = array ('<input type="checkbox" class="usercheckbox" name="user'.$user->id.'" />', $profilelink);
            if ($bulkoperations) {
                //$data[] = '<input type="checkbox" class="usercheckbox" name="user'.$user->id.'" />';
            }

            if ($mode === MODE_BRIEF) {
                foreach ($extrafields as $field) {
                    $data[] = $user->{$field};
                }
                foreach ($extrachildfields as $field) {
                    $data[] = $user->{$field};
                }
            }

            if (isset($userlist_extra) && isset($userlist_extra[$user->id])) {
                $ras = $userlist_extra[$user->id]['ra'];
                $rastring = '';
                foreach ($ras AS $key=>$ra) {
                    $rolename = $allrolenames[$ra['roleid']] ;
                    if ($ra['ctxlevel'] == CONTEXT_COURSECAT) {
                        $rastring .= $rolename. ' @ ' . '<a href="'.$CFG->wwwroot.'/course/category.php?id='.$ra['ctxinstanceid'].'">'.s($ra['ccname']).'</a>';
                    } elseif ($ra['ctxlevel'] == CONTEXT_SYSTEM) {
                        $rastring .= $rolename. ' - ' . get_string('globalrole','role');
                    } else {
                        $rastring .= $rolename;
                    }
                }
                $data[] = $rastring;
                if ($groupmode != 0) {
                    // Use htmlescape with s() and implode the array.
                    $data[] = implode(', ', array_map('s',$userlist_extra[$user->id]['group']));
                    $data[] = implode(', ', array_map('s', $userlist_extra[$user->id]['gping']));
                }
            }
            $table->add_data($data);
        }
    }

    $table->print_html();

}

if ($bulkoperations) {
    echo '<br /><div class="buttons">';
    echo '<input type="button" id="checkall" value="'.get_string('selectall').'" /> ';
    echo '<input type="button" id="checknone" value="'.get_string('deselectall').'" /> ';
    $displaylist = array();
    $displaylist['messageselect.php'] = get_string('messageselectadd');
    $displaylist['emailgrades.php'] = get_string('emailgradesadd', 'local_parents');


    echo $OUTPUT->help_icon('withselectedusers');
    echo html_writer::tag('label', get_string("withselectedusers"), array('for'=>'formactionid'));
    echo html_writer::select($displaylist, 'formaction', '', array(''=>'choosedots'), array('id'=>'formactionid'));

    echo '<input type="hidden" name="id" value="'.$course->id.'" />';
    echo '<noscript style="display:inline">';
    echo '<div><input type="submit" value="'.get_string('ok').'" /></div>';
    echo '</noscript>';
    echo '</div></div>';
    echo '</form>';

    $module = array('name'=>'core_user', 'fullpath'=>'/user/module.js');
    $PAGE->requires->js_init_call('M.core_user.init_participation', null, false, $module);
}

if (has_capability('moodle/site:viewparticipants', $context) && $totalcount > ($perpage*3)) {
    echo '<form action="index.php" class="searchform"><div><input type="hidden" name="id" value="'.$course->id.'" />'.get_string('search').':&nbsp;'."\n";
    echo '<input type="text" name="search" value="'.s($search).'" />&nbsp;<input type="submit" value="'.get_string('search').'" /></div></form>'."\n";
}

$perpageurl = clone($baseurl);
$perpageurl->remove_params('perpage');
if ($perpage == SHOW_ALL_PAGE_SIZE) {
    $perpageurl->param('perpage', DEFAULT_PAGE_SIZE);
    echo $OUTPUT->container(html_writer::link($perpageurl, get_string('showperpage', '', DEFAULT_PAGE_SIZE)), array(), 'showall');

} else if ($matchcount > 0 && $perpage < $matchcount) {
    $perpageurl->param('perpage', SHOW_ALL_PAGE_SIZE);
    echo $OUTPUT->container(html_writer::link($perpageurl, get_string('showall', '', $matchcount)), array(), 'showall');
}

// Userlist.
echo '</div>';  

echo $OUTPUT->footer();

if ($userlist) {
    $userlist->close();
}

