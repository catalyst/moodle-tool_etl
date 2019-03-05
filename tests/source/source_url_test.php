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
 * Tests for source url class.
 *
 * @package    tool_etl
 * @copyright  2019 John Yao <johnyao@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use etl_basics\source\source_url;
use tool_etl\logger;

defined('MOODLE_INTERNAL') || die;

class tool_etl_source_url_testcase extends advanced_testcase {
    /**
     * @var source_folder
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
        $this->source = new source_url();
        $this->data = new data_fake();
        logger::get_instance()->set_task_id(1); // Emulate running task.
    }

    public function test_get_name() {
        $this->assertEquals('URL', $this->source->get_name());
    }

    public function test_default_settings() {
        $expected = array(
            'address' => '',
        );
        $this->assertEquals($expected, $this->source->get_settings());
    }

    public function test_short_name() {
        $this->assertEquals('source_url', $this->source->get_short_name());
    }

}
