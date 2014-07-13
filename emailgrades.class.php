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
 * The local parents email grades class.
 *
 * @package   local_parents
 * @copyright 2014 Commission Scolaire des Affluents
 * @author    Gilles-Philippe Leblanc <contact@gpleblanc.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/export/lib.php');

/**
 * The local parents email grades class.
 */
class local_parents_emailgrades extends grade_export {

    /**
     * @var string The plugin name;
     */
    public $plugin = 'emailgrades';

    /**
     * @var string The email address of the sender of the email.
     */
    private $from;

    /**
     * @var string The list of users who will receive the email.
     */
    private $parentlist;

    /**
     * @var string The subject of the email.
     */
    private $subject;

    /**
     * @var string The message prefix of the email.
     */
    private $messageprefix;

    /**
     * @var string The signature of the sender of the email.
     */
    private $signature;

    /**
     * Constructor should set up all the private variables ready to be pulled.
     *
     * @param object $course The course object containing the user grades to email.
     * @param string $from The email address of the sender of the email.
     * @param string $subject The subject of the email.
     * @param string $signature The signature of the sender of the email.
     * @param array $parentlist The list of users who will receive the email.
     * @param string $itemlist comma separated list of item ids, empty means all
     * @param boolean $exportfeedback If the grades feedback has to be included in the email.
     * @param int $displaytype The grades display type of the grades in the email.
     * @param int $decimalpoints The number of decimal points of the grades in the email.
     */
    public function __construct($course, $from, $subject, $messageprefix, $signature, $parentlist = '', $itemlist = '',
            $exportfeedback = false, $displaytype = GRADE_DISPLAY_TYPE_REAL, $decimalpoints = 2) {
        parent::__construct($course, 0, $itemlist, $exportfeedback, false, $displaytype, $decimalpoints, false, false);
        $this->from = $from;
        $this->subject = $subject;
        $this->signature = $signature;
        $this->parentlist = $parentlist;
        $this->messageprefix = $messageprefix;
    }

    /**
     * This is an abstract method se we need to implement it but we will
     * not use it since we email the grades instead of creating a file.
     */
    public function print_grades() {}

    /**
     * Compose and email the grades to all parents.
     */
    public function email_grades() {
        global $USER;

        $parentsmessage = $this->get_parents_message();
        $parents = $this->get_parents();
        $result = true;

        // For each parent, compose the message body and email it.
        foreach ($parents as $parent) {
            $htmlbody = $this->compose_complete_message($parentsmessage[$parent->id]);
            $body = strip_tags($htmlbody);
            if (!email_to_user($parent, $USER, $this->subject, $body, $htmlbody)) {
                $result = false;
            }
        }
        return $result;
    }

    /**
     * Prints preview of exported grades for students of a given parent on screen.
     *
     * @param int $parentid The id of a parent for whom we want to find the users grades.
     */
    public function display_message_preview($parentid) {
        $parentsmessage = $this->get_parents_message($parentid);
        return $this->compose_complete_message($parentsmessage[$parentid]);
    }

    /**
     * Compose a message by adding a prefix and signature.
     *
     * @param string $message The message to be composed.
     * @return string The composed message.
     */
    private function compose_complete_message($message) {
        $html = $this->messageprefix . "\n\n";
        $html .= $message;
        $html .= $this->signature;
        return $html;
    }

    /**
     * Prints preview of exported grades on screen as a feedback mechanism.
     *
     * @param int $parentid The id of a parent for whom we want to find the users.
     * @return array The list of user with their specific parents/mentors/tutors.
     */
    private function get_parents_message($parentid = null) {
        global $OUTPUT;

        $exporttracking = $this->track_exports();
        $userswithparents = $this->get_course_users_by_parents_id();
        $parentsmessage = array();

        // Print all the lines of data.
        $geub = new grade_export_update_buffer();
        $gui = new graded_users_iterator($this->course, $this->columns);
        $gui->init();
        while ($userdata = $gui->next_user()) {
            $user = $userdata->user;

            // If the user have to parents on the course, we skip him.
            if (!array_key_exists($user->id, $userswithparents)) {
                continue;
            }

            $table = new html_table();
            $head = array(get_string('gradeitems', 'grades'), get_string('grades', 'grades'));
            if ($this->export_feedback) {
                $head[] = get_string('feedback');
            }
            $table->head = $head;
            $table->data = array();

            foreach ($this->columns as $itemid => $gradeitem) {
                $grade = new grade_grade(array('itemid' => $itemid, 'userid' => $user->id));
                if ($exporttracking) {
                    $geub->track($grade);
                }
                $row = array();
                $row[] = $this->format_column_name($gradeitem);
                $row[] = $this->format_grade($userdata->grades[$itemid]);
                if ($this->export_feedback) {
                    $row[] = $this->format_feedback($userdata->feedbacks[$itemid]);
                }
                $table->data[] = $row;
            }

            foreach ($userswithparents[$user->id] as $parentid) {
                if (!array_key_exists($parentid, $parentsmessage)) {
                    $parentsmessage[$parentid] = '';
                }

                $firstname = html_writer::tag('b', $user->firstname);
                $parentsmessage[$parentid] .= html_writer::tag('p', get_string('gradestablefor', 'local_parents',
                        $firstname));
                $parentsmessage[$parentid] .= html_writer::table($table);
            }
        }
        $gui->close();
        $geub->close();

        return $parentsmessage;
    }

    /**
     * Get the select parents and their emails.
     *
     * @return object The parent list.
     */
    private function get_parents() {
        global $DB;

        $list = explode(',', $this->parentlist);
        return $DB->get_recordset_list('user', 'id', $list, '', 'id,email,deleted,auth,suspended');
    }

    /**
     * Get all users of the current course who having a specified parents/mentors/tutors
     * and their specific parents/mentors/tutors.
     *
     * @param int $parentid The id of a parent for whom we want to find the users.
     * @return array The list of user with their specific parents/mentors/tutors.
     */
    public function get_course_users_by_parents_id($parentid = null) {
        global $DB;
        $context = context_course::instance($this->course->id, MUST_EXIST);

        list($esql, $params) = get_enrolled_sql($context, null, 0, true);
        $joins = array("FROM {user} child");

        $parentroleid = 10;

        // Performance hacks - we preload user contexts together with accounts.
        $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = child.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_USER;
        $joins[] = $ccjoin;

        $select = "SELECT DISTINCT child.id id, u.id parentid";
        $joins[] = "JOIN ($esql) e ON e.id = child.id";
        $joins[] = "JOIN {user_enrolments} ue ON child.id = ue.userid";
        $joins[] = "LEFT JOIN {role_assignments} ra ON (ra.contextid = ctx.id)";
        $joins[] = "LEFT JOIN {role} r ON (ra.roleid = r.id) AND r.id = $parentroleid";
        $joins[] = "JOIN {user} u ON (ra.userid = u.id) AND u.id ";

        if (!empty($parentid)) {
            $joins[] = "= " . $parentid;
        } else {
             $joins[] = "IN (" . $this->parentlist . ")";
        }

        $select .= $ccselect;

        $from = implode("\n", $joins);

        $users = array();
        $userrecords = $DB->get_recordset_sql("$select $from", $params);
        foreach ($userrecords as $user) {
            if (!array_key_exists($user->id, $users)) {
                $users[$user->id] = array();
            }
            $parentid = (int)$user->parentid;
            $users[$user->id][$parentid] = $parentid;
        }
        return $users;
    }
}
