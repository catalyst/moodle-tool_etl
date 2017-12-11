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
 * SFTP using ssh key for authentication.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\source;

use tool_etl\config_field;
use phpseclib\Crypt\RSA;
use phpseclib\Net\SFTP;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/admin/tool/etl/extlib/vendor/autoload.php');

class source_sftp_key extends source_ftp {

    /**
     * Name of the source.
     *
     * @var string
     */
    protected $name = "SFTP (key auth)";

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
     * A path to a directory which stores private keys.
     *
     * @var string
     */
    protected $keydir;

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
        'fileregex' => '',
        'filterdate' => 0,
        'delete' => 0,
    );

    /**
     * @inheritdoc
     */
    public function __construct(array $settings = array()) {
        parent::__construct($settings);

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
     * Check if we should copy the remote file.
     *
     * @param string $remotefile Remote file path.
     *
     * @return int
     */
    protected function should_copy($remotefile) {
        $shouldcopy = parent::should_copy($remotefile);

        if ($shouldcopy && $this->settings['filterdate']) {
            $datestring = date('Ymd', time());
            $shouldcopy = preg_match("/$datestring/", $remotefile);
        }

        return $shouldcopy;
    }

    /**
     * @inheritdoc
     */
    protected function get_remote_files() {
        return $this->connid->nlist($this->settings['directory']);
    }

    /**
     * @inheritdoc
     */
    protected function get_remote_file_path($remotefile) {
        return $this->settings['directory'] . '/' . basename($remotefile);
    }

    /**
     * @inheritdoc
     */
    protected function copy_file($remotefile, $localfile) {
        $result = $this->connid->get($remotefile, $localfile);
        $this->log_copy_result($result, $remotefile, $localfile);

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function delete_file($filepath) {
        return $this->connid->delete($filepath);
    }

    /**
     * @inheritdoc
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        $fields = array();

        $fields['keyname'] = new config_field('keyname', 'Host', 'hidden', $this->settings['keyname'],  PARAM_RAW);
        $fields['host'] = new config_field('host', 'Host', 'text', $this->settings['host'],  PARAM_HOST);
        $fields['port'] = new config_field('port', 'Port', 'text', $this->settings['port'], PARAM_INT);
        $fields['username'] = new config_field('username', 'User name', 'text', $this->settings['username'], PARAM_ALPHAEXT);
        $fields['password'] = new config_field('password', 'Password', 'passwordunmask', $this->settings['password'], PARAM_RAW);

        if (!empty($this->settings['keyname'])) {
            $fields['owerwritekey'] = new config_field('owerwritekey', 'Overwrite existing key?', 'advcheckbox', 0, PARAM_INT);
        }

        $fields['key'] = new config_field('key', 'Private key', 'textarea', '', PARAM_RAW);
        $fields['directory'] = new config_field('directory', 'Directory', 'text', $this->settings['directory'], PARAM_SAFEPATH);
        $fields['fileregex'] = new config_field('fileregex', 'File regex', 'text', $this->settings['fileregex'], PARAM_RAW);

        $fields['filterdate'] = new config_field(
            'filterdate',
            'Filter by today date in filename',
            'advcheckbox',
            $this->settings['filterdate'],
            PARAM_BOOL
        );

        $fields['delete'] = new config_field('delete', 'Delete loaded files', 'advcheckbox', $this->settings['delete'], PARAM_BOOL);

        $elements = $this->get_config_form_elements($mform, $fields);

        if (!empty($this->settings['keyname'])) {
            $key = $this->get_config_form_prefix() . 'key';
            $overwrite = $this->get_config_form_prefix() . 'owerwritekey';
            $mform->disabledIf($key, $overwrite);
        }

        return $elements;
    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        $errors = parent::validate_config_form_elements($data, $files, $errors);

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
