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
 * Tests for common base class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\common\common_base;

defined('MOODLE_INTERNAL') || die;

class tool_etl_common_base_testcase extends advanced_testcase {

    public function data_provider_for_test_validate_type() {
        return array(
            array('', false),
            array(array(), false),
            array(new stdClass(), false),
            array(1, false),
            array('random type', false),
            array('source', true),
            array('target', true),
            array('processor', true),
            array('schedule', false),

        );
    }

    /**
     * @dataProvider data_provider_for_test_validate_type
     */
    public function test_validate_type($type, $expected) {
        $this->assertEquals($expected, common_base::is_valid_type($type));
    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage Invalid type Random type
     */
    public function test_throw_exception_when_get_options_for_invalid_type() {
        $options = common_base::options('Random type');
    }

    public function data_provider_for_test_get_options() {
        return array(
            array('source', array(
                (object) array('subplugin' => 'etl_basics', 'classname' => 'source_ftp'),
                (object) array('subplugin' => 'etl_basics', 'classname' => 'source_sftp'),
                (object) array('subplugin' => 'etl_basics', 'classname' => 'source_sftp_key'),
                (object) array('subplugin' => 'etl_basics', 'classname' => 'source_folder'),
                (object) array('subplugin' => 'etl_basics', 'classname' => 'source_url'),
            )),
        );
    }

    /**
     * @dataProvider data_provider_for_test_get_options
     */
    public function test_get_options($type, $expected) {
        $this->assertArraySubset($expected, common_base::options($type));
    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage Can not initialise element. Class etl_random\random_type\RandonName is not exist
     */
    public function test_throw_exception_when_init_invalid_type() {
        $options = common_base::init('random_type', 'RandonName', 'etl_random', '');
    }

    public function data_provider_for_test_init_new_element() {
        return array(
            array('source'),
            array('target'),
            array('processor'),
        );
    }

    /**
     * @dataProvider data_provider_for_test_init_new_element
     */
    public function test_init_new_element_of_given_type($type) {
        $options = common_base::options($type);
        foreach ($options as $option) {
            // Test is it's correct class.
            $this->assertInstanceOf("$option->subplugin\\$type\\" . $option->classname, common_base::init(
                $type, $option->classname, $option->subplugin)
            );
            // Test is it extends required base class.
            $this->assertInstanceOf("tool_etl\\$type\\$type" . "_base", common_base::init(
                $type, $option->classname, $option->subplugin)
            );
            // Test that it implements an interface of required type.
            $this->assertInstanceOf("tool_etl\\". $type . '\\' . $type. "_interface", common_base::init(
                $type, $option->classname, $option->subplugin)
            );
            // Test that it implements common interface.
            $this->assertInstanceOf("tool_etl\\common\\common_interface", common_base::init(
                $type, $option->classname, $option->subplugin)
            );
        }
    }

}
