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
 * This file is part of the User section Moodle
 *
 * @package   local_parents
 * @copyright 2014 Commission Scolaire des Affluents
 * @author    Gilles-Philippe Leblanc <contact@gpleblanc.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/message/lib.php');
require_once('local_parents_edit_emailgrades_form.php');
require_once('local_parents_review_emailgrades_form.php');
require_once('emailgrades.class.php');

$id = required_param('id', PARAM_INT);
$deluser = optional_param('deluser', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$tempdata = optional_param('tempdata', '', PARAM_RAW);

$url = new moodle_url('/local/parents/emailgrades.php', array('id' => $id));

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}

require_login($course);

if ($course->id == $SITE->id) {
    print_error('cannoteditsiteform');
}

$coursecontext = context_course::instance($id);   // Course context.
$systemcontext = context_system::instance();   // SYSTEM context.
require_capability('moodle/course:bulkmessaging', $coursecontext);
require_capability('moodle/grade:export', $coursecontext);

if (empty($SESSION->emailtoparents)) {
    $SESSION->emailtoparents = array();
}
if (!array_key_exists($id, $SESSION->emailtoparents)) {
    $SESSION->emailtoparents[$id] = array();
}

$link = null;
if (has_capability('moodle/course:viewparticipants', $coursecontext) ||
        has_capability('moodle/site:viewparticipants', $systemcontext)) {
    $link = new moodle_url("/local/parents/parents.php", array('id' => $course->id));
}
$strtitle = get_string('emailgradesadd', 'local_parents');

$PAGE->set_pagelayout('incourse');
$PAGE->navbar->add(get_string('parents', 'local_parents'), $link);
$PAGE->navbar->add($strtitle);
$PAGE->set_url($url);
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);

if (empty($action)) {
    if ($deluser) {
        if (array_key_exists($id, $SESSION->emailtoparents) &&
                array_key_exists($deluser, $SESSION->emailtoparents[$id])) {
            unset($SESSION->emailtoparents[$id][$deluser]);
        }
    }

    $count = 0;

    if ($data = data_submitted()) {
        require_sesskey();
        $namefields = get_all_user_name_fields(true);
        foreach ($data as $k => $v) {
            if (preg_match('/^(user|teacher)(\d+)$/', $k, $m)) {
                if (!array_key_exists($m[2], $SESSION->emailtoparents[$id])) {
                    if ($user = $DB->get_record_select('user', "id = ?", array($m[2]), 'id,
                            ' . $namefields . ',idnumber,email,mailformat,lastaccess, lang, maildisplay')) {
                        $SESSION->emailtoparents[$id][$m[2]] = $user;
                        $count++;
                    }
                }
            }
        }
    }

    $emailgradeseditform = new local_parents_edit_emailgrades_form('emailgrades.php', array('course' => $course,
        'from' => $USER->email));

    if ($emailgradeseditform->is_cancelled()) {
        redirect(new moodle_url('parents.php', array('id' => $id, 'filtertype' => 'course')));
    } else if (($data = $emailgradeseditform->get_data()) && isset($data->submitbutton)) {
        echo $OUTPUT->header();
        $emailgradesreviewform = new local_parents_review_emailgrades_form('emailgrades.php',
                array('course' => $course));
        $data->messageprefix = $data->messageprefix['text'];
        $data->signature = $data->signature['text'];
        $emails = explode(',', $data->parentlist);
        $firstemailid = $emails[0];
        $data->to = $DB->get_field('user', 'email', array('id' => $emails[0]));
        unset($data->submitbutton);

        if (isset($data->itemids) && count($data->itemids)) {
            $data->itemsids = implode(',', array_keys($data->itemids, 1));
        } else {
            $data->itemsids = '-1';
        }

        $emailgrades = new local_parents_emailgrades($course, $data->from, $data->subject, $data->messageprefix,
                $data->signature, $data->parentlist, $data->itemsids, $data->export_feedback, $data->display,
                $data->decimals);

        $data->message = $emailgrades->display_message_preview($firstemailid);

        $emailgradesreviewform->set_data($data);
        $emailgradesreviewform->display();
        echo $OUTPUT->footer();
        exit();
    }

    echo $OUTPUT->header();

    if ($count) {
        if ($count == 1) {
            $message = get_string('addedrecip', 'moodle', $count);
        } else {
            $message = get_string('addedrecips', 'moodle', $count);
        }
        echo $OUTPUT->notification($message, 'notifymessage');
    }
    $emailgradeseditform->display();

    echo $OUTPUT->footer();
    exit();
}
$emailgradesreviewform = new local_parents_review_emailgrades_form('emailgrades.php', array('course' => $course));

if ($emailgradesreviewform->is_cancelled()) {
    redirect($url);
} else if ($data = $emailgradesreviewform->get_data()) {
    $emailgrades = new local_parents_emailgrades($course, $data->from, $data->subject, $data->messageprefix,
            $data->signature, $data->parentlist, $data->itemsids, $data->export_feedback, $data->display,
            $data->decimals);

    if (!isset($data->refreshpreview)) {
        echo $OUTPUT->header();
        echo $OUTPUT->box_start();

        if ($emailgrades->email_grades()) {
            echo $OUTPUT->notification(get_string('emailgradessuccess', 'local_parents'), 'notifysuccess');
        } else {
            echo $OUTPUT->notification(get_string('emailgradesfailure', 'local_parents'));
        }

        echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $id)));
        echo $OUTPUT->box_end();
        echo $OUTPUT->footer();
        exit();
    }
    $data->to = $DB->get_field('user', 'email', array('id' => $data->parentselectpreview));
    $data->message = $emailgrades->display_message_preview($data->parentselectpreview);
    $emailgradesreviewform->set_data($data);
}

echo $OUTPUT->header();
$emailgradesreviewform->display();
echo $OUTPUT->footer();
