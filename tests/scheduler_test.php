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
 * Tests for scheduler.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\scheduler;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/admin/tool/etl/classes/scheduler.php');

/**
 * Test report scheduler class
 */
class tool_etl_scheduler_testcase extends advanced_testcase {
    /**
     * Test basic scheduler functionality
     */
    public function test_scheduler_basic() {
        $row = new stdClass();
        $row->data = 'Some data';
        $row->schedule = 0;
        $row->frequency = scheduler::DAILY;
        $row->nextevent = 100;

        $scheduler = new scheduler($row);
        $timestamp = time();
        $scheduler->set_time($timestamp);
        $this->assertFalse($scheduler->is_changed());

        $scheduler->do_asap();
        $this->assertLessThan($timestamp, $scheduler->get_scheduled_time());
        $this->assertTrue($scheduler->is_changed());
        $this->assertTrue($scheduler->is_time());

        $scheduler->next($timestamp);
        $this->assertGreaterThan($timestamp, $scheduler->get_scheduled_time());
        $this->assertTrue($scheduler->is_changed());
        $this->assertFalse($scheduler->is_time());
    }

    /**
     * Test plan for schedule estimations
     */
    public function schedule_plan() {
        $data = array(
            array(scheduler::DAILY, 10, 1389394800, 1389394800, 1389434400),
            array(scheduler::DAILY, 15, 1394202900, 1394202900, 1394204400),
            array(scheduler::DAILY, 15, 1394204400, 1394204400, 1394290800),
            array(scheduler::WEEKLY, 4, 1389484800, 1389484800, 1389830400),
            array(scheduler::WEEKLY, 5, 1394118600, 1394118600, 1394150400),
            array(scheduler::WEEKLY, 5, 1394205000, 1394205000, 1394150400),
            array(scheduler::WEEKLY, 5, 1394291400, 1394291400, 1394755200),
            array(scheduler::MONTHLY, 6, 1389052800, 1389052800, 1391644800),
            array(scheduler::MONTHLY, 31, 1391212800, 1391212800, 1393545600),
            array(scheduler::MONTHLY, 31, 1454284800, 1454284800, 1456704000),
            array(scheduler::MONTHLY, 29, 1394041665, 1394041665, 1396051200),
            array(scheduler::MONTHLY, 1, 1394041665, 1394041665, 1396310400),
            array(scheduler::MONTHLY, 5, 1394041665, 1394041665, 1393977600),
            array(scheduler::HOURLY, 1, 1427346000, 1427346793, 1427349600),
            array(scheduler::HOURLY, 1, 1427410800, 1427410800, 1427414400),
            array(scheduler::HOURLY, 8, 1427446800, 1427454000, 1427472000),
            array(scheduler::HOURLY, 8, 1427472000, 1427472000, 1427500800),
            array(scheduler::MINUTELY, 1, 1427559300, 1427559300, 1427559360),
            array(scheduler::MINUTELY, 1, 1427561940, 1427561940, 1427562000),
            array(scheduler::MINUTELY, 15, 1427556600, 1427556665, 1427557500),
            array(scheduler::MINUTELY, 15, 1427557500, 1427557500, 1427558400),
        );
        return $data;
    }
    /**
     * Test scheduler calculations
     *
     * @dataProvider schedule_plan
     */
    public function test_scheduler_timing($frequency, $schedule, $currentevent, $currenttime, $expectedevent) {
        $row = new stdClass();
        $row->data = 'Some data';
        $row->schedule = $schedule;
        $row->frequency = $frequency;
        $row->nextevent = $currentevent;

        $scheduler = new scheduler($row);
        $scheduler->next($currenttime, false, 'UTC');
        $time = $scheduler->get_scheduled_time();
        $frequencystr = '';
        switch($frequency) {
            case scheduler::DAILY :
                $frequencystr = 'daily';
                break;
            case scheduler::WEEKLY :
                $frequencystr = 'weekly';
                break;
            case scheduler::MONTHLY :
                $frequencystr = 'monthly';
                break;
            case scheduler::MINUTELY :
                $frequencystr = 'minutely';
                break;
            case scheduler::HOURLY :
                $frequencystr = 'hourly';
                break;
        }
        $format = "%A, %D %B %Y, %H:%M:%S";
        $now = userdate($currenttime, $format, core_date::get_server_timezone());
        $expected = userdate($expectedevent, $format, core_date::get_server_timezone());
        $result = userdate($time, $format, core_date::get_server_timezone());
        $message = "\n$frequencystr - $schedule:\nnow:      $now ($currenttime)\n" .
            "expected: $expected ($expectedevent)\nresult:   $result ($time)\n";
        $this->assertEquals($expectedevent, $time, $message);
    }

    /**
     * Test scheduler mapping to db object row
     */
    public function test_scheduler_map() {
        $map = array(
            'nextevent' => 'test_event',
            'frequency' => 'test_frequency',
            'schedule' => 'test_schedule',
        );
        $row = new stdClass();
        $row->data = 'Some data';
        $row->test_schedule = 0;
        $row->test_frequency = 0;
        $row->test_event = 0;

        $scheduler = new scheduler($row, $map);
        $scheduler->from_array(array(
            'frequency' => scheduler::DAILY,
            'schedule' => 10,
            'initschedule' => false,
        ));

        $this->assertTrue($scheduler->is_changed());
        $this->assertEquals(10, $row->test_schedule);
        $this->assertEquals(scheduler::DAILY, $row->test_frequency);
        $this->assertEquals($scheduler->get_scheduled_time(), $row->test_event);
    }
}
