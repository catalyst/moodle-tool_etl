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
 * Tests for default processor class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\processor\processor_default;
use tool_etl\logger;

defined('MOODLE_INTERNAL') || die;


class tool_etl_processor_default_testcase extends advanced_testcase {
    /**
     * @var source_fake
     */
    protected $source;
    /**
     * @var target_fake
     */
    protected $target;

    /**
     * @var data_fake
     */
    protected $data;

    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/formslib.php');
        require_once($CFG->dirroot . '/admin/tool/etl/tests/fake/source_fake.php');
        require_once($CFG->dirroot . '/admin/tool/etl/tests/fake/target_fake.php');
        require_once($CFG->dirroot . '/admin/tool/etl/tests/fake/data_fake.php');

        parent::setUp();
        $this->resetAfterTest();

        $this->source = new source_fake();
        $this->target = new target_fake();
        $this->data = new data_fake();

        logger::get_instance()->set_task_id(1); // Emulate running task.
    }

    public function test_no_settings_exist() {
        $processor = new processor_default();
        $this->assertEmpty($processor->get_settings());
    }

    public function test_no_config_form_elements() {
        $processor = new processor_default();
        $this->assertEmpty($processor->create_config_form_elements(new \MoodleQuickForm('test', 'POST', '/index.php')));
    }

    public function test_short_name() {
        $processor = new processor_default();
        $this->assertEquals('processor_default', $processor->get_short_name());
    }

    public function test_config_form_prefix() {
        $processor = new processor_default();
        $this->assertEquals('processor_default-', $processor->get_config_form_prefix());
    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage Coding error detected, it must be fixed by a programmer: Can not process.
     * Source and target must be set!
     */
    public function test_throw_exception_if_source_and_target_are_not_set() {
        $this->expectException('coding_exception');
        $this->expectExceptionMessage('Coding error detected, it must be fixed by a programmer: Can not process.');
        $processor = new processor_default();
        $processor->process();
    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage Coding error detected, it must be fixed by a programmer: Can not process.
     * Source and target must be set!
     */
    public function test_throw_exception_if_source_is_not_set() {
        $this->expectException('coding_exception');
        $this->expectExceptionMessage('Coding error detected, it must be fixed by a programmer: Can not process.');
        $processor = new processor_default();
        $processor->set_source($this->source);
        $processor->process();
    }

    /**
     * @expectedException coding_exception
     * @expectedExceptionMessage Coding error detected, it must be fixed by a programmer: Can not process.
     * Source and target must be set!
     */
    public function test_throw_exception_if_target_is_not_set() {
        $this->expectException('coding_exception');
        $this->expectExceptionMessage('Coding error detected, it must be fixed by a programmer: Can not process.');
        $processor = new processor_default();
        $processor->set_target($this->target);
        $processor->process();
    }

    public function test_it_never_loads_if_extracted_empty_data() {
        $this->source->set_extract_result($this->data);

        $processor = new processor_default();
        $processor->set_source($this->source);
        $processor->set_target($this->target);
        $processor->process();

        $this->assertTrue($this->source->is_extracted());
        $this->assertFalse($this->target->is_loaded());
    }

    public function test_it_extracts_and_loads_data() {
        $this->data->set_supported_formats(array('test_format'));
        $this->source->set_extract_result($this->data);

        $processor = new processor_default();
        $processor->set_source($this->source);
        $processor->set_target($this->target);
        $processor->process();

        $this->assertTrue($this->source->is_extracted());
        $this->assertTrue($this->target->is_loaded());
    }
}
