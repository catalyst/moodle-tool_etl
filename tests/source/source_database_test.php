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
 * Tests for source folder class.
 *
 * @package    tool_etl
 * @copyright  2018 Ilya Tregubov <ilyatregubov@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\source\source_database;
use tool_etl\logger;

defined('MOODLE_INTERNAL') || die;

class tool_etl_source_database_testcase extends advanced_testcase {
    /**
     * @var source_database
     */
    protected $source;

    /**
     * @var data_fake
     */
    protected $data;

    public function setUp() {
        global $CFG;

        require_once($CFG->dirroot . '/lib/formslib.php');
        require_once($CFG->dirroot . '/admin/tool/etl/tests/fake/data_fake.php');

        parent::setUp();

        $this->resetAfterTest(true);
        $this->source = new source_database();
        $this->data = new data_fake();
        logger::get_instance()->set_task_id(1); // Emulate running task.
    }

    public function test_get_name() {
        $this->assertEquals('Database', $this->source->get_name());
    }

    public function test_default_settings() {
        $expected = array(
            'querysql' => '',
            'querylimit' => 5000,
            'columnheader' => 0,
            'weekstart' => 6,
        );
        $this->assertEquals($expected, $this->source->get_settings());
    }

    public function test_short_name() {
        $this->assertEquals('source_database', $this->source->get_short_name());
    }
    public function test_config_form_prefix() {
        $this->assertEquals('source_database-', $this->source->get_config_form_prefix());
    }

    public function test_config_form_elements() {
        $elements = $this->source->create_config_form_elements(new \MoodleQuickForm('test', 'POST', '/index.php'));

        $this->assertCount(4, $elements);

        $this->assertEquals('textarea', $elements[0]->getType());
        $this->assertEquals('text', $elements[1]->getType());

        $this->assertEquals('source_database-querysql', $elements[0]->getName());
        $this->assertEquals('source_database-querylimit', $elements[1]->getName());
    }

    public function test_config_form_validation() {
        $errors = $this->source->validate_config_form_elements(
            array(),
            array(),
            array()
        );
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('source_database-querysql', $errors);
        $this->assertArrayHasKey('source_database-querylimit', $errors);

        $errors = $this->source->validate_config_form_elements(
            array('source_database-querysql' => 'test', 'source_database-querylimit' => '1000'),
            array(),
            array()
        );
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('source_database-querysql', $errors);
        $this->assertArrayNotHasKey('source_database-querylimit', $errors);

        $errors = $this->source->validate_config_form_elements(
            array('source_database-querysql' => 'SELECT * FROM {user}',
                'source_database-querylimit' => '1000'),
            array(),
            array()
        );
        $this->assertEmpty($errors);
        $this->assertArrayNotHasKey('source_database-querysql', $errors);
        $this->assertArrayNotHasKey('source_database-querylimit', $errors);

        $errors = $this->source->validate_config_form_elements(
            array('source_database-querylimit' => '1000'),
            array(),
            array()
        );
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('source_database-querysql', $errors);
        $this->assertArrayNotHasKey('source_database-querylimit', $errors);

        $errors = $this->source->validate_config_form_elements(
            array('source_database-querysql' => 'SELECT * FROM {user}', 'source_database-querylimit' => '1'),
            array(),
            array()
        );
        $this->assertEmpty($errors);
    }

    public function test_get_week_starts_test() {
        $this->assertEquals(array(
            strtotime('00:00 7 November 2009'), strtotime('00:00 31 October 2009')),
            $this->source->tool_etl_get_week_starts(strtotime('12:36 10 November 2009'), 6));

        $this->assertEquals(array(
            strtotime('00:00 7 November 2009'), strtotime('00:00 31 October 2009')),
            $this->source->tool_etl_get_week_starts(strtotime('00:00 7 November 2009'), 6));

        $this->assertEquals(array(
            strtotime('00:00 7 November 2009'), strtotime('00:00 31 October 2009')),
            $this->source->tool_etl_get_week_starts(strtotime('23:59 13 November 2009'), 6));
    }

    public function test_get_month_starts_test() {
        $this->assertEquals(array(
            strtotime('00:00 1 November 2009'), strtotime('00:00 1 October 2009')),
            $this->source->tool_etl_get_month_starts(strtotime('12:36 10 November 2009')));

        $this->assertEquals(array(
            strtotime('00:00 1 November 2009'), strtotime('00:00 1 October 2009')),
            $this->source->tool_etl_get_month_starts(strtotime('00:00 1 November 2009')));

        $this->assertEquals(array(
            strtotime('00:00 1 November 2009'), strtotime('00:00 1 October 2009')),
            $this->source->tool_etl_get_month_starts(strtotime('23:59 29 November 2009')));
    }

    public function test_tool_etl_get_element_type() {
        $this->assertEquals('date_time_selector', $this->source->tool_etl_get_element_type('start_date'));
        $this->assertEquals('date_time_selector', $this->source->tool_etl_get_element_type('startdate'));
        $this->assertEquals('date_time_selector', $this->source->tool_etl_get_element_type('date_closed'));
        $this->assertEquals('date_time_selector', $this->source->tool_etl_get_element_type('dateclosed'));

        $this->assertEquals('text', $this->source->tool_etl_get_element_type('anythingelse'));
        $this->assertEquals('text', $this->source->tool_etl_get_element_type('not_a_date_field'));
        $this->assertEquals('text', $this->source->tool_etl_get_element_type('mandated'));
    }

    public function test_tool_etl_substitute_user_token() {
        $this->assertEquals('SELECT COUNT(*) FROM oh_quiz_attempts WHERE user = 123',
            $this->source->tool_etl_substitute_user_token('SELECT COUNT(*) FROM oh_quiz_attempts '.
                'WHERE user = %%USERID%%', 123));
    }

    public function test_tool_etl_bad_words_list() {
        $options = array('ALTER', 'CREATE', 'DELETE', 'DROP', 'GRANT', 'INSERT', 'INTO', 'TRUNCATE', 'UPDATE');
        $this->assertEquals($options, $this->source->tool_etl_bad_words_list());
    }

    public function test_tool_etl_contains_bad_word() {
        $string = 'DELETE * FROM prefix_user u WHERE u.id  > 0';
        $this->assertEquals(1, $this->source->tool_etl_contains_bad_word($string));
    }

    public function test_tool_etl_is_integer() {
        $this->assertTrue($this->source->tool_etl_is_integer(1));
        $this->assertTrue($this->source->tool_etl_is_integer('1'));
        $this->assertFalse($this->source->tool_etl_is_integer('frog'));
        $this->assertFalse($this->source->tool_etl_is_integer('2013-10-07'));
    }

}
