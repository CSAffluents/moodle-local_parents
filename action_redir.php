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
 * Wrapper script redirecting user operations to correct destination.
 *
 * @package   local_parents
 * @copyright 2016 Gilles-Philippe Leblanc <contact@gpleblanc.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

$formaction = required_param('formaction', PARAM_FILE);
$id = required_param('id', PARAM_INT);

$PAGE->set_url('/local/parents/action_redir.php', array('formaction' => $formaction, 'id' => $id));

// Add every page will be redirected by this script.
$actions = array(
    'messageselect.php',
    'emailgrades.php',
);

if (array_search($formaction, $actions) === false) {
    print_error('unknownuseraction');
}

if (!confirm_sesskey()) {
    print_error('confirmsesskeybad');
}

require_once($formaction);
