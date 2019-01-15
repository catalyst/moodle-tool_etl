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
 * Tests for logger class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\logger;

defined('MOODLE_INTERNAL') || die;

/**
 * Test report scheduler class
 */
class tool_etl_logger_testcase extends advanced_testcase {

    public function test_get_instance_return_the_same_object() {
        $this->resetAfterTest();

        $logger = logger::get_instance();
        $this->assertSame($logger, logger::get_instance());

        // Update lastrunid and see if we still get the same object.
        set_config('lastrunid', 15, 'tool_etl');
        $this->assertSame($logger, logger::get_instance());
    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage Coding error detected, it must be fixed by a programmer: Cannot clone singleton
     */
    public function test_throw_exception_when_clone() {
        $this->resetAfterTest();
        $logger = logger::get_instance();

        $clone = clone $logger;
    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage Task or Element is not set. Can not write to the log
     */
    public function test_throw_exception_if_task_id_is_not_set() {
        $logger = logger::get_instance();
        $logger->set_element('Test element');

        $logger->add_to_log(logger::TYPE_ERROR, 'test');

    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage Task or Element is not set. Can not write to the log
     */
    public function test_throw_exception_if_element_is_not_set() {
        $logger = logger::get_instance();
        $logger->set_element(null);
        $logger->set_task_id(777);

        $logger->add_to_log(logger::TYPE_ERROR, 'test');
    }

    public function test_logging_failed_event_triggered_when_can_not_add_log_record() {
        $this->resetAfterTest();

        $sink = $this->redirectEvents();
        $this->assertEquals(0, $sink->count());

        try {
            $logger = logger::get_instance();
            $logger->set_element(null);
            $logger->set_task_id(777);
            $logger->add_to_log(logger::TYPE_ERROR, 'test');
        } catch (\Exception $exception) {
            $this->assertEquals(1, $sink->count());
            $this->assertRegExp(
                "/Task or Element is not set. Can not write to the log table. Run ID #[0-9]+/",
                $sink->get_events()[0]->get_description()
            );
        }
    }

    public function test_add_to_log() {
        global $DB;

        $this->resetAfterTest();

        $logger = logger::get_instance();
        $logger->set_element('Test element');
        $logger->set_task_id(777);
        $id = $logger->add_to_log(logger::TYPE_ERROR, 'Test log message', 'Test info', 'Test trace');

        $actual = $DB->get_record('tool_etl_log', array('id' => $id));

        $this->assertEquals(777, $actual->taskid);
        $this->assertEquals('ERROR', $actual->logtype);
        $this->assertEquals('Test element', $actual->element);
        $this->assertEquals('Test log message', $actual->action);
        $this->assertEquals('Test info', $actual->info);
        $this->assertEquals('Test trace', $actual->trace);
    }

    public function test_to_string() {
        $logger = logger::get_instance();

        // String.
        $this->assertEquals('String should be string', $logger->to_string('String should be string'));

        // Array.
        $expected = 'first=Array, second=should, third=be, fourth=string';
        $array = array('first' => 'Array', 'second' => 'should', 'third' => 'be', 'fourth' => 'string', 'empty' => '');
        $this->assertEquals($expected, $logger->to_string($array));

        // Object.
        $expected = 'first=Object, second=should, third=be, fourth=string';
        $object = new stdClass();
        $object->first = 'Object';
        $object->second = 'should';
        $object->third = 'be';
        $object->fourth = 'string';
        $object->empty = '';
        $this->assertEquals($expected, $logger->to_string($object));
    }

    public function test_get_existing_elements() {
        $this->resetAfterTest();

        $logger = logger::get_instance();

        $logger->set_element('Element 1');
        $logger->add_to_log(logger::TYPE_ERROR, 'Test log message', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_ERROR, 'Test log message', 'Test info', 'Test trace');

        $logger->set_element('Element 2');
        $logger->add_to_log(logger::TYPE_ERROR, 'Test log message', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_ERROR, 'Test log message', 'Test info', 'Test trace');

        $logger->set_element('Element 3');
        $logger->add_to_log(logger::TYPE_ERROR, 'Test log message', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_ERROR, 'Test log message', 'Test info', 'Test trace');

        $expected = array('Element 1', 'Element 2', 'Element 3');
        $this->assertEquals($expected, logger::get_existing_elements());
    }

    public function test_get_existing_actions() {
        $this->resetAfterTest();

        $logger = logger::get_instance();

        $logger->add_to_log(logger::TYPE_ERROR, 'Action 1', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_ERROR, 'Action 1', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_ERROR, 'Action 2', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_ERROR, 'Action 2', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_ERROR, 'Action 3', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_ERROR, 'Action 3', 'Test info', 'Test trace');

        $expected = array('Action 1', 'Action 2', 'Action 3');
        $this->assertEquals($expected, logger::get_existing_actions());
    }

    public function test_get_existing_run_ids() {
        global $DB;

        $this->resetAfterTest();

        $logger = logger::get_instance();
        $logger->add_to_log(logger::TYPE_ERROR, 'Test log message', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_ERROR, 'Test log message', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_INFO, 'Test log message', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_INFO, 'Test log message', 'Test info', 'Test trace');

        // Set runid as 888 for some records in the table.
        $DB->execute('UPDATE {tool_etl_log} SET runid = 888 WHERE logtype =?', array(logger::TYPE_INFO));

        $expected = array(1, 888);
        $this->assertEquals($expected, logger::get_existing_run_ids());
    }

    public function test_logging_triggers_log_record_added_event() {
        $this->resetAfterTest();

        $sink = $this->redirectEvents();
        $this->assertEquals(0, $sink->count());

        $logger = logger::get_instance();
        $logger->add_to_log(logger::TYPE_ERROR, 'Test log message', 'Test info', 'Test trace');
        $logger->add_to_log(logger::TYPE_WARNING, 'Test log message', 'Test info', 'Test trace');

        $this->assertEquals(2, $sink->count());
        foreach ($sink->get_events() as $event) {
            $this->assertRegExp(
                "/Log record added for ETL task" .
                " #[0-9]+ during run #[0-9]+. " .
                "Element: '(.)+'. Type: '[A-Z]+'. Action: '(.)+'. Information: '(.)+'/",
                $event->get_description()
            );
        }
    }

}
