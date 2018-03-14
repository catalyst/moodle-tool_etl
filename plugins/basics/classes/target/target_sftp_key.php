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
 * SFTP with key auth target.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace etl_basics\target;
use tool_etl\config_field;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;
use tool_etl\logger;
use tool_etl\target\target_base;


defined('MOODLE_INTERNAL') || die;

class target_sftp_key extends target_base {

    /**
     * RSA key.
     *
     * @var \phpseclib\Crypt\RSA
     */
    protected $key;

    /**
     * Opened connection.
     *
     * @var \phpseclib\Net\SFTP
     */
    protected $connid;

    /**
     * Result of connection.
     *
     * @var bool
     */
    protected $logginresult;

    /**
     * A path to a directory which stores private keys.
     *
     * @var string
     */
    protected $keydir;

    /**
     * A temp folder to save files.
     *
     * @var string
     */
    protected $filedir;

    /**
     * Settings.
     *
     * @var array
     */
    protected $settings = array(
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
    );

    /**
     * @inheritdoc
     */
    public function __construct(array $settings = array()) {
        global $CFG;

        parent::__construct($settings);

        $this->filedir = $CFG->dataroot . DIRECTORY_SEPARATOR . $this->get_short_name();
        check_dir_exists($this->filedir);
        $this->keydir = $this->filedir . DIRECTORY_SEPARATOR . 'key';
        check_dir_exists($this->keydir);
    }

    /**
     * Get key file path.
     *
     * @param string $filename A key file name.
     *
     * @return string
     */
    protected function get_key_path($filename) {
        return $this->keydir . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * Get a key content as a string.
     *
     * @return string
     * @throws \Exception
     */
    protected function get_key_content() {
        $content = file_get_contents($this->get_key_path($this->settings['keyname']));

        if ($content === false) {
            throw new \Exception('Error reading key content form ' . $this->get_key_path($this->settings['keyname']));
        }

        return $content;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_for_display() {
        $settings = $this->get_settings();
        unset($settings['password']);
        unset($settings['key']);

        $settings['key'] = $this->get_key_path($settings['keyname']);

        return $settings;
    }

    /**
     * Connect to SFTP server.
     */
    protected function connect() {
        if (!empty($this->settings['host'])) {

            $this->key = new RSA();
            if (!empty($this->settings['password'])) {
                $this->key->setPassword($this->settings['password']);
            }

            $this->key->loadKey($this->get_key_content());
            $this->connid = new SFTP($this->settings['host'], $this->settings['port']);
        }
    }

    /**
     * Login to FTP server.
     */
    protected function login() {
        if (!empty($this->connid) && !empty($this->key)) {
            $this->logginresult = $this->connid->login($this->settings['username'], $this->key);
        }
    }

    /**
     * @inheritdoc
     */
    public function is_available() {
        $this->connect();
        $this->login();

        if (!$this->connid) {
            $this->log('connect', 'Connection failed', logger::TYPE_ERROR);
            return false;
        }

        if (!$this->logginresult) {
            $this->log('login', 'Login failed using ' . $this->settings['username'], logger::TYPE_ERROR);
            return false;
        }

        return true;
    }

    /**
     * Load data from files.
     *
     * @param array $filepaths A list of files to load from.
     *
     * @return bool
     * @throws \coding_exception If incorrect file paths format.
     */
    protected function load_from_files($filepaths) {
        if (!is_array($filepaths)) {
            throw new \coding_exception('File paths should be an array');
        }

        $this->backup_files($filepaths);

        $result = true;

        foreach ($filepaths as $file) {
            $target = $this->get_full_target_file_path($this->get_target_file_name($file));

            if ($this->connid->file_exists($target) && empty($this->settings['overwrite'])) {
                $this->log('load_data', 'Skip copying file ' . $file . ' to ' . $target . ' File exists.', logger::TYPE_WARNING);
                continue;
            }

            if (!$this->connid->put($target, $file, SFTP::SOURCE_LOCAL_FILE)) {
                $this->log('load_data', 'Failed to copy file ' . $file . ' to ' . $target, logger::TYPE_ERROR);
                $result = false; // Fail result if any file is failed.
            } else {
                $this->log('load_data', 'Successfully copied file ' . $file . ' to ' . $target);
            }
        }

        return $result;
    }

    /**
     * Return full path of the target file.
     *
     * @param string $targetfilename A name of the target file.
     *
     * @return string
     */
    protected function get_full_target_file_path($targetfilename) {
        if (!empty($this->settings['directory'])) {
            return rtrim($this->settings['directory'], '/') . '/' . $targetfilename;
        } else {
            return $targetfilename;
        }
    }

    /**
     * @inheritdoc
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        $fields = array();

        $fields['keyname'] = new config_field('keyname', 'keyname', 'hidden', $this->settings['keyname'],  PARAM_RAW);
        $fields['host'] = new config_field('host', 'Host', 'text', $this->settings['host'],  PARAM_HOST);
        $fields['port'] = new config_field('port', 'Port', 'text', $this->settings['port'], PARAM_INT);
        $fields['username'] = new config_field('username', 'User name', 'text', $this->settings['username'], PARAM_ALPHAEXT);
        $fields['password'] = new config_field('password', 'Password', 'passwordunmask', $this->settings['password'], PARAM_RAW);

        if (!empty($this->settings['keyname'])) {
            $fields['owerwritekey'] = new config_field('owerwritekey', 'Overwrite existing key?', 'checkbox', 0, PARAM_INT);
        }

        $fields['key'] = new config_field(
            'key',
            'Private key',
            'textarea',
            '',
            PARAM_RAW
        );
        $fields['directory'] = new config_field(
            'directory',
            'Directory',
            'text',
            $this->settings['directory'],
            PARAM_SAFEPATH
        );
        $fields['filename'] = new config_field(
            'filename',
            'File name',
            'text',
            $this->settings['filename'],
            PARAM_RAW
        );
        $fields['overwrite'] = new config_field(
            'overwrite',
            'Overwrite existing files?',
            'advcheckbox',
            $this->settings['overwrite'],
            PARAM_BOOL
        );
        $fields['addtime'] = new config_field(
            'addtime',
            'Append files by date like ' . date($this->get_date_format(), time()),
            'advcheckbox',
            $this->settings['addtime'],
            PARAM_BOOL
        );
        $fields['delimiter'] = new config_field(
            'delimiter',
            'Date delimiter',
            'text',
            $this->settings['delimiter'],
            PARAM_RAW
        );
        $fields['backupfiles'] = new config_field(
            'backupfiles',
            'Backup files?',
            'advcheckbox',
            $this->settings['backupfiles'],
            PARAM_BOOL
        );

        $elements = $this->get_config_form_elements($mform, $fields);

        if (!empty($this->settings['keyname'])) {
            $key = $this->get_config_form_prefix() . 'key';
            $overwrite = $this->get_config_form_prefix() . 'owerwritekey';
            $mform->disabledIf($key, $overwrite);
        }

        // Disable delimiter setting if not appending files by date.
        $mform->disabledIf(
            $this->get_config_form_prefix() . 'delimiter' ,
            $this->get_config_form_prefix() . 'addtime'
        );

        return $elements;
    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        if (empty($data[$this->get_config_form_prefix() . 'host'])) {
            $errors[$this->get_config_form_prefix() . 'host'] = 'Host could not be empty';
        }

        if (empty($data[$this->get_config_form_prefix() . 'port'])) {
            $errors[$this->get_config_form_prefix() . 'port'] = 'Port could not be empty';
        }

        if (empty($data[$this->get_config_form_prefix() . 'username'])) {
            $errors[$this->get_config_form_prefix() . 'username'] = 'Username could not be empty';
        }

        if (empty($data[$this->get_config_form_prefix() . 'key']) && empty($data[$this->get_config_form_prefix() . 'keyname'])) {
            $errors[$this->get_config_form_prefix() . 'key'] = 'Private key could not be empty';
        }

        return $errors;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_from_submitted_data(array $data) {
        $settings = parent::get_settings_from_submitted_data($data);

        if (!empty($settings['key'])) {
            $settings['keyname'] = $this->save_key($settings['key']);
            $settings['key'] = '';

            if (!empty($data[$this->get_config_form_prefix() . 'keyname'])) {
                $this->delete_key($data[$this->get_config_form_prefix() . 'keyname']);
            }
        }

        return $settings;
    }

    /**
     * Save key file and return saved name.
     *
     * @param string $content A key text.
     *
     * @return string Key name.
     * @throws \Exception on failure
     */
    protected function save_key($content) {
        $name = generate_uuid();

        if (file_put_contents($this->get_key_path($name), $content) === false) {
            throw new \Exception('Error saving key to ' . $this->get_key_path($name));
        }

        return $name;
    }

    /**
     * Delete provided key file.
     *
     * @param string $filename A name of the file.
     */
    protected function delete_key($filename) {
        $filepath = $this->get_key_path($filename);
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}
