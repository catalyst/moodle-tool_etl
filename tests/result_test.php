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

use tool_etl\result;

defined('MOODLE_INTERNAL') || die;

/**
 * Test report scheduler class
 */
class tool_etl_result_testcase extends advanced_testcase {
    /**
     * @var result
     */
    protected $resultinstance;

    public function setUp() {
        parent::setUp();
        $this->resetAfterTest();
        $this->resultinstance = new result();
    }

    public function data_provider_invalid_format() {
        return array(
            array(array()),
            array(new stdClass()),
            array(1),
            array(null),
            array(''),
        );
    }

    /**
     * @dataProvider data_provider_invalid_format
     * @expectedException coding_exception
     * @expectedExceptionMessage Format should be not empty string
     */
    public function test_validate_format_when_set_result($format) {
        $this->resultinstance->set_result($format, true);
    }

    /**
     * @dataProvider data_provider_invalid_format
     * @expectedException coding_exception
     * @expectedExceptionMessage Format should be not empty string
     */
    public function test_validate_format_when_get_result($format) {
        $this->resultinstance->get_result($format);
    }

    public function test_set_result_for_format() {
        $this->assertFalse($this->resultinstance->get_result('test_format'));
        $this->resultinstance->set_result('test_format', true);
        $this->assertTrue($this->resultinstance->get_result('test_format'));
    }

    public function data_provider_not_bool() {
        return array(
            array(array(), false),
            array(array('1'), true),
            array(new stdClass(), true),
            array(0, false),
            array(1, true),
            array(1.4, true),
            array(null, false),
            array('string', true),
            array('', false),
        );
    }

    /**
     * @dataProvider data_provider_not_bool
     */
    public function test_get_result_return_bool($result, $expected) {
        $this->resultinstance->set_result('test_format', $result);
        $this->assertEquals($expected, $this->resultinstance->get_result('test_format'));
    }

}
