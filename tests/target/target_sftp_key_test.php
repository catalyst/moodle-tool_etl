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
 * @copyright  2019 Srdjan Janković <srdjan@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_etl\target\target_sftp_key;
use tool_etl\logger;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/admin/tool/etl/tests/fake/data_fake.php');
require_once($CFG->dirroot . '/admin/tool/etl/extlib/vendor/autoload.php');

class mock_sftp {
    public $username;
    public $key;
    public $fileschecked = [];
    public $filespushed = [];

    public function login($username, $key) {
        $this->username = $username;
        $this->key = $key;
        return "Logged in";
    }

    public function file_exists($target) {
        $this->fileschecked[] = $target;
        return false;
    }

    public function put($target) {
        if ($target == 'explode.txt') {
            return false;
        }
        $this->filespushed[] = $target;
        return true;
    }
}

class mock_target_sftp_key extends target_sftp_key {
    public $connid;
    public $notification;

    protected function connect() {
        if (empty($this->settings['host'])) {
            return;
        }

        $this->key = 'fake-key';
        $this->connid = new mock_sftp();
    }
}

class tool_etl_target_sftp_key_testcase extends advanced_testcase {
    /**
     * @var \tool_etl\target\target_sftp_key
     */
    protected $target;

    /**
     * @var data_fake
     */
    protected $data;

    public function setUp(): void {
        parent::setUp();

        $this->resetAfterTest(true);
        $this->data = new data_fake();
        logger::get_instance()->set_task_id(1); // Emulate running task.
    }

    public function test_get_name() {
        $target = new target_sftp_key();
        $this->assertEquals('SFTP (key auth)', $target->get_name());
    }

    public function test_default_settings() {
        $expected = array(
            'host' => '',
            'port' => 22,
            'username' => '',
            'password' => '',
            'key' => '',
            'keyname' => '',
            'directory' => '',
            'filename' => '',
            'overwrite' => 1,
            'addtime' => 0,
            'delimiter' => '',
            'backupfiles' => 1,
            'notifymailto' => '',
        );
        $target = new target_sftp_key();
        $this->assertEquals($expected, $target->get_settings());
    }

    public function test_short_name() {
        $target = new target_sftp_key();
        $this->assertEquals('target_sftp_key', $target->get_short_name());
    }

    public function test_config_form_prefix() {
        $target = new target_sftp_key();
        $this->assertEquals('target_sftp_key-', $target->get_config_form_prefix());
    }

    public function test_available_by_default() {
        $target = new target_sftp_key();
        $this->assertFalse($target->is_available());
    }

    public function test_not_available_if_cannot_connect() {
        $target = new target_sftp_key(array('host' => 'not_exist'));
        $this->assertFalse($target->is_available());
    }

    public function test_load_returns_false_if_target_is_not_available() {
        $target = new target_sftp_key(array('host' => 'not_exist'));
        $actual = $target->load($this->data);
        $this->assertEmpty($actual->get_results());
    }

    public function test_that_all_results_empty_if_load_empty_data() {
        $target = new mock_target_sftp_key(array('host' => 'not_exist'));
        $this->data->set_supported_formats(array('files', 'array', 'object', 'string'));
        $actual = $target->load($this->data);
        $this->assertFalse($actual->get_result('files'));
        $this->assertFalse($actual->get_result('array'));
        $this->assertFalse($actual->get_result('object'));
        $this->assertFalse($actual->get_result('string'));
    }

    public function test_that_support_loading_from_files_only() {
        global $CFG;

        $target = new mock_target_sftp_key(array('host' => 'not_exist'));
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

        $target->set_settings(array('filename' => 'target_test.txt'));
        $actual = $target->load($this->data);

        $this->assertTrue($actual->get_result('files'));
        $this->assertFalse($actual->get_result('array'));
        $this->assertFalse($actual->get_result('object'));
        $this->assertFalse($actual->get_result('string'));
    }

    public function test_that_can_not_load_from_random_format() {
        $target = new mock_target_sftp_key(array('host' => 'not_exist'));

        $this->data->set_supported_formats(array('random_format'));
        $this->data->set_get_data('random_format', array('content' => 'Some random content'));

        $actual = $target->load($this->data);
        $this->assertFalse($actual->get_result('random_format'));
    }

    public function test_loading_when_files_data_is_not_array() {
        $target = new mock_target_sftp_key(array('host' => 'not_exist'));
        $this->data->set_supported_formats(array('files'));

        $this->data->set_get_data('files', 'String');
        $actual = $target->load($this->data);
        $this->assertFalse($actual->get_result('files'));

        $this->data->set_get_data('files', new stdClass());
        $actual = $target->load($this->data);
        $this->assertFalse($actual->get_result('files'));

        $this->data->set_get_data('files', 1);
        $actual = $target->load($this->data);
        $this->assertFalse($actual->get_result('files'));

        $this->data->set_get_data('files', 1.5);
        $actual = $target->load($this->data);
        $this->assertFalse($actual->get_result('files'));

        $this->data->set_get_data('files', null);
        $actual = $target->load($this->data);
        $this->assertFalse($actual->get_result('files'));
    }

    public function test_loading_from_files_default_settings() {
        global $CFG;

        $testfile1 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test1.txt';
        $testfile2 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test2.txt';
        $testfile3 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test3.txt';

        touch($testfile1);
        touch($testfile2);
        touch($testfile3);

        $target = new mock_target_sftp_key(array('host' => 'not_exist'));
        $files = array($testfile1, $testfile2, $testfile3);

        $this->data->set_supported_formats(array('files'));
        $this->data->set_get_data('files', $files);
        $actual = $target->load($this->data);

        $this->assertTrue($actual->get_result('files'));
        $this->assertEquals(['test1.txt', 'test2.txt', 'test3.txt'], $target->connid->filespushed);
    }

    public function test_loading_from_files_set_filename() {
        global $CFG;

        $testfile1 = $CFG->tempdir . DIRECTORY_SEPARATOR . 'test1.txt';

        touch($testfile1);

        $target = new mock_target_sftp_key(array('host' => 'not_exist'));
        $files = array($testfile1);

        $this->data->set_supported_formats(array('files'));
        $this->data->set_get_data('files', $files);
        $target->set_settings(array('filename' => 'new_file_name.csv'));
        $actual = $target->load($this->data);

        $this->assertTrue($actual->get_result('files'));
        $this->assertEquals(['new_file_name.csv'], $target->connid->filespushed);
    }

    public function test_loading_from_files_error() {
        global $CFG;

        $fname = 'explode.txt';
        $testfile1 = $CFG->tempdir . DIRECTORY_SEPARATOR . $fname;
        $email = 'nobody@nowhere.com';

        touch($testfile1);

        $target = new mock_target_sftp_key(['host' => 'not_exist', 'notifymailto' => $email]);
        $files = array($testfile1);

        $this->data->set_supported_formats(array('files'));
        $this->data->set_get_data('files', $files);

        $sink = $this->redirectEmails();

        $actual = $target->load($this->data);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertContains('load_data error', $messages[0]->subject);
        $this->assertEquals($email, $messages[0]->to);

        $this->assertFalse($actual->get_result('files'));
        $rs = logger::get_instance()->get_current_run_logs();
        $last = end($rs);
        $this->assertEquals('load_data', $last->action);
        $this->assertEquals("Failed to copy file $testfile1 to $fname", $last->info);
    }
}
