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
 * Fake source for testing.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\source\source_base;

defined('MOODLE_INTERNAL') || die;

class source_fake extends source_base {

    protected $name = "Fake source";
    protected $result;
    protected $exception;
    protected $extracted = false;

    public function set_extract_result($result = false, $exception = false) {
        $this->result = $result;
        $this->exception = $exception;
    }

    public function extract() {
        if ($this->exception) {
            throw new Exception($this->exception);
        } else {
            $this->extracted = true;
            return $this->result;
        }
    }

    public function is_extracted() {
        return $this->extracted;
    }

    public function is_available() {
        return true;
    }

}
