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
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\source\source_folder;
use tool_etl\logger;

defined('MOODLE_INTERNAL') || die;

class tool_etl_source_folder_testcase extends advanced_testcase {
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
        $this->source = new source_folder();
        $this->data = new data_fake();
        logger::get_instance()->set_task_id(1); // Emulate running task.
    }

    public function test_get_name() {
        $this->assertEquals('Folder', $this->source->get_name());
    }

    public function test_default_settings() {
        $expected = array(
            'folder' => '',
            'fileregex' => '',
        );
        $this->assertEquals($expected, $this->source->get_settings());
    }

    public function test_short_name() {
        $this->assertEquals('source_folder', $this->source->get_short_name());
    }
    public function test_config_form_prefix() {
        $this->assertEquals('source_folder-', $this->source->get_config_form_prefix());
    }

    public function test_is_not_available_by_default() {
        $this->assertFalse($this->source->is_available());
    }

    public function test_not_available_if_path_is_not_exists_and_not_set_to_create() {
        $this->source = new source_folder(array('folder' => '/var/not_exist'));
        $this->assertFalse($this->source->is_available());
    }

    public function test_config_form_elements() {
        $elements = $this->source->create_config_form_elements(new \MoodleQuickForm('test', 'POST', '/index.php'));

        $this->assertCount(2, $elements);

        $this->assertEquals('text', $elements[0]->getType());
        $this->assertEquals('text', $elements[1]->getType());

        $this->assertEquals('source_folder-folder', $elements[0]->getName());
        $this->assertEquals('source_folder-fileregex', $elements[1]->getName());
    }

    public function test_config_form_validation() {
        $errors = $this->source->validate_config_form_elements(
            array(),
            array(),
            array()
        );
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('source_folder-folder', $errors);
        $this->assertArrayNotHasKey('source_folder-fileregex', $errors);

        $errors = $this->source->validate_config_form_elements(
            array('folder' => 'test', 'fileregex' => 'test'),
            array(),
            array()
        );
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('source_folder-folder', $errors);
        $this->assertArrayNotHasKey('source_folder-fileregex', $errors);

        $errors = $this->source->validate_config_form_elements(
            array('source_folder-folder' => 'test'),
            array(),
            array()
        );
        $this->assertEmpty($errors);
        $this->assertArrayNotHasKey('source_folder-folder', $errors);
        $this->assertArrayNotHasKey('source_folder-fileregex', $errors);

        $errors = $this->source->validate_config_form_elements(
            array('source_folder-fileregex' => '/test/'),
            array(),
            array()
        );
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('source_folder-folder', $errors);
        $this->assertArrayNotHasKey('source_folder-fileregex', $errors);

        $errors = $this->source->validate_config_form_elements(
            array('source_folder-folder' => 'test', 'source_folder-fileregex' => '/test/'),
            array(),
            array()
        );
        $this->assertEmpty($errors);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage Folder source is not available!
     */
    public function test_exception_thrown_when_extract_and_source_is_not_available() {
        $this->source->extract();
    }

    public function test_can_extract_required_files() {
        global $CFG;

        // Set up.
        $testfolder = $CFG->dataroot . DIRECTORY_SEPARATOR . 'test_folder';
        $testfile1 = $testfolder . DIRECTORY_SEPARATOR . 'test1.txt';
        $testfile2 = $testfolder . DIRECTORY_SEPARATOR . 'test2.txt';
        $testfile3 = $testfolder . DIRECTORY_SEPARATOR . 'test3.txt';
        $testfile4 = $testfolder . DIRECTORY_SEPARATOR . 'test4.txt';
        mkdir($testfolder);
        touch($testfile1);
        touch($testfile2);
        touch($testfile3);
        touch($testfile4);

        // Filter files by regex.
        $this->source = new source_folder(array('folder' => $testfolder, 'fileregex' => '/test[1-3].txt/'));
        $result = $this->source->extract();
        $this->assertInstanceOf('tool_etl\\data_interface', $result);

        $files = $result->get_data('files');
        $this->assertTrue(is_array($files));
        $this->assertCount(3, $files);
        $this->assertEquals($testfile1, $files[0]);
        $this->assertEquals($testfile2, $files[1]);
        $this->assertEquals($testfile3, $files[2]);

        // Get all files as regex is empty.
        $this->source = new source_folder(array('folder' => $testfolder));
        $result = $this->source->extract();
        $this->assertInstanceOf('tool_etl\\data_interface', $result);

        $files = $result->get_data('files');
        $this->assertTrue(is_array($files));
        $this->assertCount(4, $files);
        $this->assertEquals($testfile1, $files[0]);
        $this->assertEquals($testfile2, $files[1]);
        $this->assertEquals($testfile3, $files[2]);
        $this->assertEquals($testfile4, $files[3]);

        // Clean up.
        unlink($testfile1);
        unlink($testfile2);
        unlink($testfile3);
        unlink($testfile4);
        rmdir($testfolder);
    }

}
