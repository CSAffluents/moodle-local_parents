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
 * The local parents email grades review form.
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
 * Class local_parents_review_emailgrades_form
 *
 * The form used for review email student grades to their parents, using the parent user role.
 *
 * @copyright  2014 Gilles-Philippe Leblanc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_parents_review_emailgrades_form extends moodleform {

    /**
     * The form definition.
     */
    public function definition() {
        global $SESSION;

        $mform =& $this->_form;
        $courseid = $this->_customdata['course']->id;

        $mform->addElement('header', 'preview', get_string('preview'));

        $parents = array();
        if (count($SESSION->emailtoparents[$courseid])) {
            foreach ($SESSION->emailtoparents[$courseid] as $user) {
                $parents[$user->id] = fullname($user, true);
            }
            $elements = array();
            $emailpreviewfor = get_string('emailpreviewfor', 'local_parents');
            $elements[] = &$mform->createElement('select', 'parentselectpreview', $emailpreviewfor, $parents);
            $elements[] = &$mform->createElement('submit', 'refreshpreview', get_string('refresh'));
            $mform->addGroup($elements, 'emailpreviewar', $emailpreviewfor, array(' '), false);
        }

        $this->add_static_hidden_element('from', get_string('from'), PARAM_TEXT);

        $mform->addElement('static', 'to', get_string('to'));

        $this->add_static_hidden_element('subject', get_string('subject', 'local_parents'), PARAM_TEXT);

        $mform->addElement('hidden', 'messageprefix');
        $mform->setType('messageprefix', PARAM_CLEANHTML);

        $mform->addElement('hidden', 'signature');
        $mform->setType('signature', PARAM_CLEANHTML);

        $mform->addElement('static', 'message', get_string('message', 'local_parents'));

        $mform->addElement('hidden', 'parentlist');
        $mform->setType('parentlist', PARAM_RAW);

        $mform->addElement('hidden', 'itemsids');
        $mform->setType('itemsids', PARAM_RAW);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'export_feedback');
        $mform->setType('export_feedback', PARAM_BOOL);

        $mform->addElement('hidden', 'display');
        $mform->setType('display', PARAM_INT);

        $mform->addElement('hidden', 'decimals');
        $mform->setType('decimals', PARAM_INT);

        $mform->addElement('hidden', 'showmaxgrade');
        $mform->setType('showmaxgrade', PARAM_BOOL);

        $mform->addElement('hidden', 'action', 'send');
        $mform->setType('action', PARAM_ALPHA);

        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton',
                get_string('emailgradesadd', 'local_parents'));
        $buttonarray[] = &$mform->createElement('cancel', 'cancel', get_string('backtoediting', 'local_parents'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Set the static elements based on the the value of the hidden ones.
     */
    public function definition_after_data() {
        $this->set_static_element_value('from');
        $this->set_static_element_value('subject');
    }

    /**
     * Set the value of the static element based of an hidden one.
     *
     * @param string $name The name of the element
     */
    public function set_static_element_value($name) {
        $mform =& $this->_form;
        $staticname = $name . 'static';
        if ($mform->elementExists($name) && $mform->elementExists($staticname)) {
            $element = $mform->getElement($staticname);
            $element->setValue($mform->getElementValue($name));
        }
    }

    /**
     * Add a static and an hidden element.
     * This is usefull when we want to display
     *
     * @param string $name The name of the element.
     * @param string $label The label of the element.
     * @param int $type The type of the element
     */
    private function add_static_hidden_element($name, $label, $type) {
        $mform = &$this->_form;
        $mform->addElement('static', $name . 'static', $label);
        $mform->addElement('hidden', $name);
        $mform->setType($name, $type);
    }
}
