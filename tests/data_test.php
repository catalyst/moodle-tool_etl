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
 * Tests for data class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\data;

defined('MOODLE_INTERNAL') || die;

/**
 * Test report scheduler class
 */
class tool_etl_data_testcase extends advanced_testcase {

    public function test_get_supported_formats() {
        $data = new data();
        $actual = $data->get_supported_formats();

        $this->assertTrue(is_array($actual));
        $this->assertEmpty($actual);

        $data = new data(array());
        $actual = $data->get_supported_formats();

        $this->assertTrue(is_array($actual));
        $this->assertNotEmpty($actual);
        $this->assertEquals(1, count($actual));
        $this->assertEquals('files', $actual[0]);

        $data = new data(array(), '', array(), new stdClass());
        $actual = $data->get_supported_formats();

        $this->assertTrue(is_array($actual));
        $this->assertNotEmpty($actual);
        $this->assertEquals(4, count($actual));
        $this->assertEquals('files', $actual[0]);
        $this->assertEquals('string', $actual[1]);
        $this->assertEquals('array', $actual[2]);
        $this->assertEquals('object', $actual[3]);
    }

    /**
     * @expectedException coding_exception
     * @@expectedExceptionMessage Format should be a string
     */
    public function test_get_data_throwing_exception_if_format_is_not_string() {
        $data = new data();
        $data->get_data(array());
    }

    /**
     * @expectedException Exception
     * @@expectedExceptionMessage Data is not available in bla format
     */
    public function test_get_data_throwing_exception_on_unknown_format() {
        $data = new data();
        $data->get_data('bla');
    }

    /**
     * @expectedException Exception
     * @@expectedExceptionMessage Data is not available in files format
     */
    public function test_get_data_throwing_exception_on_files_format() {
        $data = new data();
        $data->get_data('files');
    }

    /**
     * @expectedException Exception
     * @@expectedExceptionMessage Data is not available in string format
     */
    public function test_get_data_throwing_exception_on_string_format() {
        $data = new data();
        $data->get_data('string');
    }

    /**
     * @expectedException Exception
     * @@expectedExceptionMessage Data is not available in array format
     */
    public function test_get_data_throwing_exception_on_array_format() {
        $data = new data();
        $data->get_data('array');
    }

    /**
     * @expectedException Exception
     * @@expectedExceptionMessage Data is not available in object format
     */
    public function test_get_data_throwing_exception_on_object_format() {
        $data = new data();
        $data->get_data('object');
    }

    public function test_get_data_returns_data() {
        $data = new data(array('test'), 'test', array('test'), new stdClass());

        $this->assertEquals(array('test'), $data->get_data('files'));
        $this->assertEquals('test', $data->get_data('string'));
        $this->assertEquals(array('test'), $data->get_data('array'));
        $this->assertEquals(new stdClass(), $data->get_data('object'));
    }

}
