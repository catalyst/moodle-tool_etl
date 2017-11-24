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
 * Result class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl;

defined('MOODLE_INTERNAL') || die;

class result {
    /**
     * A list of results. Keys are names of formats.
     * @var array
     */
    protected $result = array();

    /**
     * Set a result for provided format.
     *
     * @param string $format Format name.
     * @param bool $result Result for the format
     *
     * @throws \coding_exception If format is not string.
     */
    public function set_result($format, $result = false) {
        $this->validate_format($format);
        $this->result[$format] = $result;
    }

    /**
     * Return result status for required format.

     * @param string $format Format name.
     *
     * @return bool
     * @throws \coding_exception If format is not string.
     */
    public function get_result($format) {
        $this->validate_format($format);

        if (isset($this->result[$format])) {
            return boolval($this->result[$format]);
        }

        return false;
    }

    /**
     * Validate provided format name.
     *
     * @param string $format Format name.
     *
     * @throws \coding_exception If format is not valid.
     */
    protected function validate_format($format) {
        if (!is_string($format) || $format === '') {
            throw new \coding_exception('Format should be not empty string');
        }
    }
}