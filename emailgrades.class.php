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
 * @copyright 2016 Gilles-Philippe Leblanc <contact@gpleblanc.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/grade/export/lib.php');

/**
 * The local parents email grades class.
 *
 * @copyright 2015 Gilles-Philippe Leblanc <contact@gpleblanc.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * @var boolean If we show the max possible grade for a grade item.
     */
    private $showmaxgrade;

    /**
     * Constructor should set up all the private variables ready to be pulled.
     *
     * This constructor used to accept the individual parameters as separate arguments, in
     * 2.8 this was simplified to just accept the data from the moodle form.
     *
     * @param object $course The course object containing the user grades to email.
     * @param stdClass|null $formdata
     */
    public function __construct($course, $formdata) {
        parent::__construct($course, 0, $formdata);
    }

    /**
     * Init object based using data from form
     * @param object $formdata
     */
    public function process_form($formdata) {
        parent::process_form($formdata);

        if (isset($formdata->from)) {
            $this->from = $formdata->from;
        }

        if (isset($formdata->subject)) {
            $this->subject = $formdata->subject;
        }

        if (isset($formdata->messageprefix)) {
            $this->messageprefix = $formdata->messageprefix;
        }

        if (isset($formdata->signature)) {
            $this->signature = $formdata->signature;
        }

        if (isset($formdata->parentlist)) {
            $this->parentlist = $formdata->parentlist;
        }

        if (isset($formdata->showmaxgrade)) {
            $this->showmaxgrade = $formdata->showmaxgrade;
        }

        // Redefine items if it comes from preview instead of edit form.
        if (!empty($formdata->itemsids) && $formdata->itemsids != '-1' && !isset($formdata->itemids)) {
            $this->columns = array();
            $itemids = explode(',', $formdata->itemsids);
            foreach ($itemids as $itemid) {
                if (array_key_exists($itemid, $this->grade_items)) {
                    $this->columns[$itemid] =& $this->grade_items[$itemid];
                }
            }
        }
    }

    /**
     * This is an abstract method se we need to implement it but we will
     * not use it since we email the grades instead of creating a file.
     */
    public function print_grades() {
    }

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
            $body = html_to_text($htmlbody);
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

        $exporttracking = $this->track_exports();
        $userswithparents = $this->get_course_users_by_parents_id();
        $parentsmessage = array();
        $showallgrades = $this->showmaxgrade && $this->displaytype != GRADE_DISPLAY_TYPE_LETTER;

        $displaytypes = array(
            GRADE_DISPLAY_TYPE_REAL => 'real',
            GRADE_DISPLAY_TYPE_PERCENTAGE => 'percentage',
            GRADE_DISPLAY_TYPE_LETTER => 'letter'
        );

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
            $head = array(get_string('gradeitem', 'grades'), get_string('grade', 'grades'));

            if ($showallgrades) {
                $head[] = get_string('maxgrade', 'grades');
            }

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
                $row[] = $this->format_column_name($gradeitem, false, $displaytypes[$this->displaytype]);
                $row[] = $this->format_grade($userdata->grades[$itemid], $this->displaytype);
                if ($showallgrades) {
                    $row[] = $this->format_max_grade($gradeitem);
                }
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

    /**
     * Returns string representation of final grade.
     * Override completely this method to add the scale position if the scale is used.
     *
     * @param grade_grade $grade instance of grade_grade class
     * @param integer $gradedisplayconst grade display type constant.
     * @return string The formatted grade.
     */
    public function format_grade($grade, $gradedisplayconst = null) {
        $formattedgrade = parent::format_grade($grade, $gradedisplayconst);
        $gradeitem = $this->grade_items[$grade->itemid];
        if ($gradeitem->gradetype == GRADE_TYPE_SCALE) {
            $formattedgrade = $this->format_with_scale_position($formattedgrade, $gradeitem);
        }
        return $formattedgrade;
    }

    /**
     * Returns a string representing grademax for a grade item.
     *
     * @param grade_item $gradeitem The grade item.
     * @return string The formatted max grade.
     */
    public function format_max_grade(grade_item $gradeitem) {
        if ($this->displaytype == GRADE_DISPLAY_TYPE_PERCENTAGE) {
            $grademax = "100 %";
        } else {
            $grademax = grade_format_gradevalue($gradeitem->grademax, $gradeitem, true, $this->displaytype, $this->decimalpoints);
            if ($gradeitem->gradetype == GRADE_TYPE_SCALE) {
                $grademax = $this->format_with_scale_position($grademax, $gradeitem);
            }
        }
        return $grademax;
    }

    /**
     * Format a scale grade by adding its scale position.
     * Ex: "quite acceptable (3)" instead of "quite acceptable".
     *
     * @param string $formattedgrade The formatted grade.
     * @param grade_item $gradeitem The grade item.
     * @return string The formatted grade with position.
     */
    private function format_with_scale_position($formattedgrade, grade_item $gradeitem) {
        global $DB;
        $string = $formattedgrade;
        if ($gradeitem->gradetype == GRADE_TYPE_SCALE && $formattedgrade != "-") {
            $scale = $DB->get_record('scale', array('id' => $gradeitem->scaleid));
            // If the item is using a scale that's not been removed.
            if (!empty($scale)) {
                $scaleitems = array_map('trim', explode(',', $scale->scale));
                $key = array_search($formattedgrade, $scaleitems) + 1;
                $string .= ' (' . $key . ')';
            }
        }
        return $string;
    }
}
