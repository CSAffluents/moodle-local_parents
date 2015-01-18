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
 * The local parents email grades edit form.
 *
 * @package   local_parents
 * @copyright 2015 Gilles-Philippe Leblanc <contact@gpleblanc.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->libdir.'/gradelib.php');

/**
 * Class local_parents_edit_emailgrades_form
 *
 * The form used for editing email student grades to their parents, using the parent user role.
 *
 * @copyright  2014 Gilles-Philippe Leblanc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_parents_edit_emailgrades_form extends moodleform {
    public function definition() {
        global $CFG, $USER, $SESSION;

        $mform =& $this->_form;
        $data = $this->_customdata;

        $course = $data['course'];

        $mform->addElement('hidden', 'deluser', 0);
        $mform->setType('deluser', PARAM_INT);
        $mform->setType('returnto', PARAM_LOCALURL);

        $mform->addElement('header', 'general', get_string('general'));

        $mform->addElement('static', 'fromstatic', get_string('from'), $data['from']);
        $mform->addElement('hidden', 'from', $data['from']);
        $mform->setType('from', PARAM_TEXT);

        $attributes = array('maxlength' => 254, 'size' => 50);
        $mform->addElement('text', 'subject', get_string('subject', 'local_parents'), $attributes);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required');
        $mform->setDefault('subject', get_string('defaultsubject', 'local_parents', $course->shortname));

        $editor = $mform->addElement('editor', 'messageprefix', get_string('message', 'local_parents'));
        $mform->setType('messageprefix', PARAM_CLEANHTML);
        $mform->addRule('messageprefix', null, 'required');
        $editor->setValue(array('text' => get_string('defaultmessage', 'local_parents', $course->fullname)));

        $editor = $mform->addElement('editor', 'signature', get_string('signature', 'local_parents'));
        $mform->setType('signature', PARAM_CLEANHTML);
        $editor->setValue(array('text' => fullname($USER, true)));

        $mform->addElement('advcheckbox', 'export_feedback', get_string('exportfeedback', 'grades'));
        $mform->setDefault('export_feedback', 0);

        $options = array(GRADE_DISPLAY_TYPE_REAL       => get_string('real', 'grades'),
                         GRADE_DISPLAY_TYPE_PERCENTAGE => get_string('percentage', 'grades'),
                         GRADE_DISPLAY_TYPE_LETTER     => get_string('letter', 'grades'));

        $mform->addElement('select', 'display', get_string('gradeexportdisplaytype', 'grades'), $options);
        $mform->setDefault('display', $CFG->grade_export_displaytype);

        $options = array(0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5);
        $mform->addElement('select', 'decimals', get_string('gradeexportdecimalpoints', 'grades'), $options);
        $mform->setDefault('decimals', $CFG->grade_export_decimalpoints);
        $mform->disabledIf('decimals', 'display', 'eq', GRADE_DISPLAY_TYPE_LETTER);

        $mform->addElement('advcheckbox', 'showmaxgrade', get_string('showmaxgrade', 'local_parents'));
        $mform->setDefault('showmaxgrade', 1);
        $mform->disabledIf('showmaxgrade', 'display', 'eq', GRADE_DISPLAY_TYPE_LETTER);

        $mform->addElement('header', 'currentlyselectedusers', get_string('currentlyselectedusers'));

        $table = new html_table();
        $table->attributes['class'] = 'emailtable';

        $parentlist = array();

        if ($nbparents = count($SESSION->emailtoparents[$course->id])) {
            foreach ($SESSION->emailtoparents[$course->id] as $user) {

                $fullname = new html_table_cell();
                $fullname->text = fullname($user, true);

                $email = new html_table_cell();
                // Check to see if we should be showing the email address.
                if ($user->maildisplay == 0) { // 0 = don't display my email to anyone.
                    $email->text = get_string('emaildisplayhidden');
                } else {
                    $email->text = $user->email;
                }

                // Display a delete button only if the number of users is greater than 1.
                if ($nbparents > 1) {
                    $remove = new html_table_cell();
                    $remove->text = html_writer::empty_tag('input', array('type' => 'submit', 'onClick' =>
                        'this.form.deluser.value=' . $user->id . ';', 'value' => get_string('remove')));
                    $table->data[] = new html_table_row(array($fullname, $email, $remove));
                } else {
                    $email->colspan = 2;
                    $table->data[] = new html_table_row(array($fullname, $email));
                }

                $parentlist[] = $user->id;
            }
        } else {
            $empty = new html_table_cell();
            $empty->colspan = 3;
            $empty->text = get_string('nousersyet');
            $table->data[] = new html_table_row(array($empty));
        }

        $mform->addElement('hidden', 'parentlist', implode(',', $parentlist));
        $mform->setType('parentlist', PARAM_RAW);

        $mform->addElement('html', html_writer::table($table));

        $mform->addElement('header', 'gradeitems', get_string('gradeitemsinc', 'grades'));
        $mform->setExpanded('gradeitems');

        $switch = grade_get_setting($course->id, 'aggregationposition', $CFG->grade_aggregationposition);

        // Grab the grade_seq for this course.
        $gseq = new grade_seq($course->id, $switch);

        if ($gradeitems = $gseq->items) {

            $canviewhidden = has_capability('moodle/grade:viewhidden', context_course::instance($course->id));

            foreach ($gradeitems as $gradeitem) {
                // Is the grade_item hidden? If so, can the user see hidden grade_items?
                if ($gradeitem->is_hidden() && !$canviewhidden) {
                    continue;
                }

                $mform->addElement('advcheckbox', 'itemids[' . $gradeitem->id . ']', $gradeitem->get_name(), null,
                        array('group' => 1));

                $mform->setDefault('itemids[' . $gradeitem->id . ']', 0);
            }
            
        }

        $mform->addElement('hidden', 'itemsids');
        $mform->setType('itemsids', PARAM_RAW);

        $mform->addElement('hidden', 'id', $course->id);
        $mform->setType('id', PARAM_INT);
        $this->add_action_buttons(true, get_string('preview'));
    }

    /**
     * Check if at least one grading component is checked to validate the form.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        if (empty(array_sum($data["itemids"]))) {
            $firstgrades = "itemids[" . current(array_keys($data["itemids"])) . "]";
            return array($firstgrades => get_string('nogradeselected', 'local_parents'));
        }
        return array();
    }
}
