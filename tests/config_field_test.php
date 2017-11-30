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
 * Tests for config_field class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\config_field;

defined('MOODLE_INTERNAL') || die;

/**
 * Test report scheduler class
 */
class tool_etl_config_field_testcase extends advanced_testcase {

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage Coding error detected, it must be fixed by a programmer: Unknown parameter bla
     */
    public function test_exception_when_getting_unknown_parameter() {
        $instance = new config_field('test_name', 'Test title');
        $instance->bla;
    }

    public function test_can_get_parameter() {
        $instance = new config_field('test_name', 'Test title', 'checkbox', 1, PARAM_RAW);

        $this->assertEquals('test_name', $instance->name);
        $this->assertEquals('Test title', $instance->title);
        $this->assertEquals('checkbox', $instance->type);
        $this->assertEquals(1, $instance->default);
        $this->assertEquals(PARAM_RAW, $instance->filter);
    }
}
