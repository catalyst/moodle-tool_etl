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
use tool_etl\logger;
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
     * @var
     */
    protected $keydir;

    /**
     * Settings.
     *
     * @var array
     */
    protected $settings = array(
        'host' => '',
        'port' => 21,
        'username' => '',
        'password' => '',
        'key' => '',
        'keyfile' => '',
        'directory' => '',
        'fileregex' => '',
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
     * @inheritdoc
     */
    public function get_settings_for_display() {
        $settings = $this->get_settings();
        $settings['keyfile'] = $this->keydir . DIRECTORY_SEPARATOR . $settings['keyfile'];
        unset($settings['password']);
        unset($settings['key']);

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

            $this->key->loadKey(file_get_contents($this->keydir . DIRECTORY_SEPARATOR . $this->settings['keyfile']));

            $this->connid = new SFTP($this->settings['host'], $this->settings['port']);
        }
    }

    /**
     * Login to FTP server.
     */
    protected function login() {
        if (!empty($this->connid)) {
            $this->logginresult = $this->connid->login($this->settings['username'], $this->key);
        }
    }

    /**
     * @inheritdoc
     */
    public function extract() {
        $this->connect();
        $this->login();

        if ($this->is_available()) {
            $this->filepaths = $this->get_files();
        } else {
            throw new \Exception($this->name . ' source is not available!');
        }
    }

    /**
     * Return files.
     */
    protected function get_files() {
        $localfiles = array();
        $remotefiles = $this->connid->nlist($this->settings['directory']);

        $this->log('get_files', $remotefiles);

        if ($remotefiles) {
            foreach ($remotefiles as $remotefile) {
                if (preg_match($this->settings['fileregex'], $remotefile)) {
                    $localfile = $this->filedir . DIRECTORY_SEPARATOR . basename($remotefile);
                    $remotefile = $this->settings['directory'] . '/' . basename($remotefile);

                    if ($this->connid->get($remotefile, $localfile)) {
                        $localfiles[] = $localfile;
                        $this->log('copy_from_ftp', 'Completed copy ' . $remotefile . ' to ' . $localfile);
                    } else {
                        $this->log('copy_from_ftp', 'Failed to copy ' . $remotefile . ' to ' . $localfile, logger::TYPE_ERROR);
                    }
                }
            }
        }

        if (empty($localfiles)) {
            $this->log('match_files', 'No files found matching regex ' . $this->settings['fileregex'], logger::TYPE_WARNING);
        }

        return $localfiles;
    }

    /**
     * @inheritdoc
     */
    public function is_available() {
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
     * @inheritdoc
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        $fields = array();

        $fields['keyfile'] = new config_field('keyfile', 'Host', 'hidden', $this->settings['keyfile'],  PARAM_RAW);
        $fields['host'] = new config_field('host', 'Host', 'text', $this->settings['host'],  PARAM_HOST);
        $fields['port'] = new config_field('port', 'Port', 'text', $this->settings['port'], PARAM_INT);
        $fields['username'] = new config_field('username', 'User name', 'text', $this->settings['username'], PARAM_ALPHAEXT);
        $fields['password'] = new config_field('password', 'Password', 'passwordunmask', $this->settings['password'], PARAM_RAW);

        if (!empty($this->settings['keyfile'])) {
            $fields['owerwritekey'] = new config_field('owerwritekey', 'Overwrite existing key?', 'checkbox', 0, PARAM_INT);
        }

        $fields['key'] = new config_field('key', 'Private key', 'textarea', '', PARAM_RAW);
        $fields['directory'] = new config_field('directory', 'Directory', 'text', $this->settings['directory'], PARAM_SAFEPATH);
        $fields['fileregex'] = new config_field('fileregex', 'File regex', 'text', $this->settings['fileregex'], PARAM_RAW);
        $fields['host'] = new config_field('host', 'Host', 'text', $this->settings['host'],  PARAM_HOST);

        $elements = $this->get_config_form_elements($mform, $fields);

        if (!empty($this->settings['keyfile'])) {
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
        if (empty($data[$this->get_config_form_prefix() . 'host'])) {
            $errors[$this->get_config_form_prefix() . 'host'] = 'Host could not be empty';
        }

        if (empty($data[$this->get_config_form_prefix() . 'port'])) {
            $errors[$this->get_config_form_prefix() . 'port'] = 'Port could not be empty';
        }

        if (empty($data[$this->get_config_form_prefix() . 'username'])) {
            $errors[$this->get_config_form_prefix() . 'username'] = 'Username could not be empty';
        }

        if (empty($data[$this->get_config_form_prefix() . 'key']) && empty($data[$this->get_config_form_prefix() . 'keyfile'])) {
            $errors[$this->get_config_form_prefix() . 'key'] = 'Private key could not be empty';
        }

        if (empty($data[$this->get_config_form_prefix() . 'directory'])) {
            $errors[$this->get_config_form_prefix() . 'directory'] = 'File directory could not be empty';
        }

        if (empty($data[$this->get_config_form_prefix() . 'fileregex'])) {
            $errors[$this->get_config_form_prefix() . 'fileregex'] = 'File regex could not be empty';
        }

        return $errors;
    }

    /**
     * @inheritdoc
     */
    public function get_settings_from_submitted_data(array $data) {
        $settings = parent::get_settings_from_submitted_data($data);

        if (!empty($settings['key'])) {
            $settings['keyfile'] = generate_uuid();
            file_put_contents($this->keydir . DIRECTORY_SEPARATOR . $settings['keyfile'], $settings['key']);
            unset($settings['key']);

            if (!empty($data['keyfile'])) {
                $filename = $this->keydir . DIRECTORY_SEPARATOR . $data['keyfile'];
                if (file_exists($filename)) {
                    unlink($filename);
                }
            }
        }

        return $settings;
    }

}
