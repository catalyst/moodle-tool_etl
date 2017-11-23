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
 * Fake target for testing.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\target\target_base;
use tool_etl\data_interface;

defined('MOODLE_INTERNAL') || die;

class target_fake extends target_base {
    protected $name = "Fake target";
    protected $result;
    protected $exception;
    protected $loaded = false;


    public function set_load_result($result = false, $exception = false) {
        $this->result = $result;
        $this->exception = $exception;
    }

    public function load(data_interface $data) {
        if ($this->exception) {
            throw new Exception($this->exception);
        } else {
            $this->loaded = true;
            return $this->result;
        }
    }

    public function is_available() {
        return true;
    }

    public function is_loaded() {
        return $this->loaded;
    }

}
