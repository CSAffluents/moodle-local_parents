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
 * This file contains the definition for the courses table which subclassses table_sql
 *
 * @package    local_parents
 * @copyright  2015 Gilles-Philippe Leblanc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_parents;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Extends flexible_table to provide a table ocntaining parents informations.
 *
 * @package    local_parents
 * @copyright  2015 Gilles-Philippe Leblanc
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class parents_table extends \flexible_table {

    /**
     * Get the to add to the where statement specific to the parents.
     *
     * @return string sql to add to where statement.
     */
    public function get_sql_where() {
        global $DB;
        $conditions = array();
        $params = array();
        if (isset($this->columns['fullname'])) {
            static $i = 0;
            $i++;

            if (!empty($this->sess->i_first)) {
                $conditions[] = $DB->sql_like('parent.firstname', ':ifirstc' . $i, false, false);
                $params['ifirstc' . $i] = $this->sess->i_first . '%';
            }
            if (!empty($this->sess->i_last)) {
                $conditions[] = $DB->sql_like('parent.lastname', ':ilastc' . $i, false, false);
                $params['ilastc' . $i] = $this->sess->i_last . '%';
            }
        }
        return array(implode(" AND ", $conditions), $params);
    }
}
