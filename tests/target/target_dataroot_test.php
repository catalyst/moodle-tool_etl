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
 * Tests for target base class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\target\target_dataroot;
use tool_etl\logger;

defined('MOODLE_INTERNAL') || die;

class tool_etl_target_dataroot_testcase extends advanced_testcase {
    /**
     * @var \tool_etl\target\target_dataroot
     */
    protected $target;

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
        $this->target = new target_dataroot();
        $this->data = new data_fake();
        logger::get_instance()->set_task_id(1); // Emulate running task.
    }

    public function test_get_name() {
        $this->assertEquals('Site data', $this->target->get_name());
    }

    public function test_default_settings() {
        $expected = array(
            'path' => '',
            'filename' => '',
            'clreateifnotexist' => 0,
            'overwrite' => 1,
            'addtime' => 0,
            'delimiter' => '',
            'backupfiles' => 1,

        );
        $this->assertEquals($expected, $this->target->get_settings());
    }

    public function test_short_name() {
        $this->assertEquals('target_dataroot', $this->target->get_short_name());
    }

    public function test_config_form_prefix() {
        $this->assertEquals('target_dataroot-', $this->target->get_config_form_prefix());
    }

    public function test_available_by_default() {
        $this->assertTrue($this->target->is_available());
    }

    public function test_not_available_if_path_is_not_exists_and_not_set_to_create() {
        $this->target = new target_dataroot(array('path' => 'not_exist'));
        $this->assertFalse($this->target->is_available());
    }

    public function test_available_if_path_is_not_exists_and_set_to_create() {
        $this->target = new target_dataroot(array('path' => 'not_exist', 'clreateifnotexist' => 1));
        $this->assertTrue($this->target->is_available());
    }

    public function test_config_form_elements() {
        $elements = $this->target->create_config_form_elements(new \MoodleQuickForm('test', 'POST', '/index.php'));

        $this->assertCount(7, $elements);

        $this->assertEquals('text', $elements[0]->getType());
        $this->assertEquals('advcheckbox', $elements[1]->getType());
        $this->assertEquals('text', $elements[2]->getType());
        $this->assertEquals('advcheckbox', $elements[3]->getType());
        $this->assertEquals('advcheckbox', $elements[4]->getType());
        $this->assertEquals('text', $elements[5]->getType());
        $this->assertEquals('advcheckbox', $elements[6]->getType());

        $this->assertEquals('target_dataroot-path', $elements[0]->getName());
        $this->assertEquals('target_dataroot-clreateifnotexist', $elements[1]->getName());
        $this->assertEquals('target_dataroot-filename', $elements[2]->getName());
        $this->assertEquals('target_dataroot-overwrite', $elements[3]->getName());
        $this->assertEquals('target_dataroot-addtime', $elements[4]->getName());
        $this->assertEquals('target_dataroot-delimiter', $elements[5]->getName());
        $this->assertEquals('target_dataroot-backupfiles', $elements[6]->getName());
    }

    public function test_config_form_validation() {
        $errors = $this->target->validate_config_form_elements(
            array(),
            array(),
            array()
        );
        $this->assertEmpty($errors);

        $errors = $this->target->validate_config_form_elements(
            array('target_dataroot-clreateifnotexist' => 1),
            array(),
            array()
        );
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('target_dataroot-path', $errors);

        $errors = $this->target->validate_config_form_elements(
            array('target_dataroot-clreateifnotexist' => 1, 'target_dataroot-path' => 'test'),
            array(),
            array()
        );
        $this->assertEmpty($errors);
    }

    public function test_load_returns_false_if_target_is_not_available() {
        $this->target = new target_dataroot(array('path' => 'not_exist'));
        $actual = $this->target->load($this->data);
        $this->assertEmpty($actual->get_results());
    }

    public function test_that_all_results_empty_if_load_empty_data() {
        $this->data->set_supported_formats(array('files', 'array', 'object', 'string'));
        $actual = $this->target->load($this->data);
        $this->assertFalse($actual->get_result('files'));
        $this->assertFalse($actual->get_result('array'));
        $this->assertFalse($actual->get_result('object'));
        $this->assertFalse($actual->get_result('string'));
    }

    public function test_that_support_loading_from_files_only() {
        global $CFG;

        $testfile = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test.txt';
        touch($testfile);

        $filescontent = array($testfile);
        $arraycontent = array('content' => 'Some array content');
        $dataobject = new stdClass();
        $dataobject->content = 'Some object content';

        $this->data->set_supported_formats(array('files', 'array', 'object', 'string'));
        $this->data->set_get_data('files', $filescontent);
        $this->data->set_get_data('array', $arraycontent);
        $this->data->set_get_data('object', $dataobject);
        $this->data->set_get_data('string', 'String content to load');

        $this->target->set_settings(array('filename' => 'target_test.txt'));
        $actual = $this->target->load($this->data);

        $this->assertTrue($actual->get_result('files'));
        $this->assertFalse($actual->get_result('array'));
        $this->assertFalse($actual->get_result('object'));
        $this->assertFalse($actual->get_result('string'));
    }

    public function test_that_can_not_load_from_random_format() {
        $this->data->set_supported_formats(array('random_format'));
        $this->data->set_get_data('random_format', array('content' => 'Some random content'));

        $actual = $this->target->load($this->data);
        $this->assertFalse($actual->get_result('random_format'));
    }

    public function test_loading_when_files_data_is_not_array() {
        $this->data->set_supported_formats(array('files'));

        $this->data->set_get_data('files', 'String');
        $actual = $this->target->load($this->data);
        $this->assertFalse($actual->get_result('files'));

        $this->data->set_get_data('files', new stdClass());
        $actual = $this->target->load($this->data);
        $this->assertFalse($actual->get_result('files'));

        $this->data->set_get_data('files', 1);
        $actual = $this->target->load($this->data);
        $this->assertFalse($actual->get_result('files'));

        $this->data->set_get_data('files', 1.5);
        $actual = $this->target->load($this->data);
        $this->assertFalse($actual->get_result('files'));

        $this->data->set_get_data('files', null);
        $actual = $this->target->load($this->data);
        $this->assertFalse($actual->get_result('files'));
    }

    public function data_provider_for_test_append_filename_by_date() {
        return array(
            array('/var/lib/test.csv', 'test20171129071554.csv'),
            array('test.csv', 'test20171129071554.csv'),
            array('/var/lib/test', 'test20171129071554'),
            array('test', 'test20171129071554'),
            array('/var/lib/test.', 'test.20171129071554'),
            array('test.', 'test.20171129071554'),
            array('/var/lib/.test', '.test20171129071554'),
            array('.test', '.test20171129071554'),
            array('/var/lib/test.inc.csv', 'test.inc20171129071554.csv'),
            array('test.inc.csv', 'test.inc20171129071554.csv'),
            array('/var/lib/test.inc.csv.', 'test.inc.csv.20171129071554'),
            array('test.inc.csv.', 'test.inc.csv.20171129071554'),
        );
    }

    /**
     * @dataProvider data_provider_for_test_append_filename_by_date
     */
    public function test_append_filename_by_date($filename, $expected) {
        $time = '1511910954';
        $actual = $this->target->append_filename_by_date($filename, $time);
        $this->assertEquals($expected, $actual);
    }

    public function test_loading_from_files_default_settings() {
        global $CFG;

        $testfile1 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test1.txt';
        $testfile2 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test2.txt';
        $testfile3 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test3.txt';

        touch($testfile1);
        touch($testfile2);
        touch($testfile3);

        $files = array($testfile1, $testfile2, $testfile3);

        $this->data->set_supported_formats(array('files'));
        $this->data->set_get_data('files', $files);
        $actual = $this->target->load($this->data);

        $this->assertTrue($actual->get_result('files'));
        $this->assertTrue(file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . 'test1.txt'));
        $this->assertTrue(file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . 'test2.txt'));
        $this->assertTrue(file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . 'test3.txt'));
    }

    public function test_loading_from_files_set_path() {
        global $CFG;

        $testfile1 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test1.txt';
        $testfile2 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test2.txt';
        $testfile3 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test3.txt';

        touch($testfile1);
        touch($testfile2);
        touch($testfile3);

        $files = array($testfile1, $testfile2, $testfile3);

        $this->data->set_supported_formats(array('files'));
        $this->data->set_get_data('files', $files);
        $this->target->set_settings(array('path' => 'test', 'clreateifnotexist' => 1));
        $actual = $this->target->load($this->data);

        $this->assertTrue($actual->get_result('files'));
        $this->assertTrue(file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'test1.txt'));
        $this->assertTrue(file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'test2.txt'));
        $this->assertTrue(file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . 'test' . DIRECTORY_SEPARATOR . 'test3.txt'));
    }

    public function test_loading_from_files_set_filename() {
        global $CFG;

        $testfile1 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test1.txt';
        $testfile2 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test2.txt';
        $testfile3 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test3.txt';

        touch($testfile1);
        touch($testfile2);
        touch($testfile3);

        $files = array($testfile1, $testfile2, $testfile3);

        $this->data->set_supported_formats(array('files'));
        $this->data->set_get_data('files', $files);
        $this->target->set_settings(array('filename' => 'new_file_name.csv'));
        $actual = $this->target->load($this->data);

        $this->assertTrue($actual->get_result('files'));
        $this->assertFalse(file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . 'test1.txt'));
        $this->assertFalse(file_exists($CFG->dataroot . DIRECTORY_SEPARATOR .'test2.txt'));
        $this->assertFalse(file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . 'test3.txt'));
        $this->assertTrue(file_exists($CFG->dataroot . DIRECTORY_SEPARATOR . 'new_file_name.csv'));
    }

}
