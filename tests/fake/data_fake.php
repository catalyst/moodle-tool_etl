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
 * Fake data class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\data_interface;

defined('MOODLE_INTERNAL') || die;

class data_fake implements data_interface {

    protected $formats = array();
    protected $data;
    protected $exception;

    public function __construct() {

    }

    public function set_supported_formats($formats) {
        $this->formats = $formats;
    }

    public function set_get_data($data, $exception = false) {
        $this->data = $data;
        $this->exception = $exception;
    }

    public function get_supported_formats() {
        return $this->formats;
    }

    public function get_data($format) {
        if ($this->exception) {
            throw new Exception ($this->exception);
        } else {
            return $this->data;
        }
    }

}
