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
 * FTP source.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\source;

use tool_etl\config_field;
use tool_etl\data;
use tool_etl\logger;

defined('MOODLE_INTERNAL') || die;

class source_ftp extends source_base {

    /**
     * Name of the source.
     *
     * @var string
     */
    protected $name = "FTP";

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
        'directory' => '',
        'fileregex' => '',
    );

    /**
     * FTP connection resource.
     *
     * @var resource a FTP stream
     */
    protected $connid;

    /**
     * Result of connection.
     *
     * @var bool
     */
    protected $logginresult;

    /**
     * A temp folder to save files.
     *
     * @var string
     */
    protected $filedir;

    /**
     * @inheritdoc
     */
    public function __construct(array $settings = array()) {
        global $CFG;

        parent::__construct($settings);

        if (!extension_loaded('ftp')) {
            throw new \Exception('PHP extension FTP is not loaded.');
        }

        $this->filedir = $CFG->dataroot . DIRECTORY_SEPARATOR . $this->get_short_name();
        check_dir_exists($this->filedir);
    }

    /**
     * @inheritdoc
     */
    public function get_settings_for_display() {
        $settings = parent::get_settings_for_display();
        unset($settings['password']);
        unset($settings['username']);

        return $settings;
    }

    /**
     * Connect to FTP server.
     */
    protected function connect() {
        if (!empty($this->settings['host'])) {
            $this->connid = ftp_connect($this->settings['host'], $this->settings['port']);
        }
    }

    /**
     * Login to FTP server.
     */
    protected function login() {
        if ($this->connid) {
            $this->logginresult = ftp_login($this->connid, $this->settings['username'], $this->settings['password']);
        }
    }

    /**
     * @inheritdoc
     */
    public function extract() {
        $this->connect();
        $this->login();

        if ($this->is_available()) {
            $this->data = new data($this->get_files());
        } else {
            throw new \Exception($this->name . ' source is not available!');
        }

        return $this->data;
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
        $elements = parent::create_config_form_elements($mform);

        $fields = array(
            'host' => new config_field('host', 'Host', 'text', $this->settings['host'],  PARAM_HOST),
            'port' => new config_field('port', 'Port', 'text', $this->settings['port'], PARAM_INT),
            'username' => new config_field('username', 'User name', 'text', $this->settings['username'], PARAM_ALPHAEXT),
            'password' => new config_field('password', 'Password', 'passwordunmask', $this->settings['password'], PARAM_RAW),
            'directory' => new config_field('directory', 'Files directory', 'text', $this->settings['directory'], PARAM_SAFEPATH),
            'fileregex' => new config_field('fileregex', 'Files regex', 'text', $this->settings['fileregex'], PARAM_RAW),
        );

        return array_merge($elements, $this->get_config_form_elements($mform, $fields));
    }

    /**
     * Return files.
     */
    protected function get_files() {
        $localfiles = array();
        $matchedfiles = array();
        $remotefiles = $this->get_remote_files();

        $this->log('get_files', $remotefiles);

        if ($remotefiles) {
            foreach ($remotefiles as $remotefile) {
                if ($this->should_copy($remotefile)) {
                    $localfile = $this->get_local_file_path($remotefile);
                    $remotefile = $this->get_remote_file_path($remotefile);

                    $matchedfiles[] = $remotefile;

                    if ($this->copy_file($remotefile, $localfile)) {
                        $localfiles[] = $localfile;
                    }
                }
            }
        }

        $this->log_get_files_results($matchedfiles, $localfiles);

        return $localfiles;
    }

    /**
     * Check if we should copy the remote file.
     *
     * @param string $remotefile Remote file path.
     *
     * @return int
     */
    protected function should_copy($remotefile) {
        return preg_match($this->settings['fileregex'], $remotefile);
    }

    /**
     * Return a list of all remote files from the configured folder.
     *
     * @return array
     */
    protected function get_remote_files() {
        return ftp_nlist($this->connid, $this->settings['directory']);
    }

    /**
     * Get a path of local file to copy.
     *
     * @param string $remotefile Remote file path.
     *
     * @return string
     */
    protected function get_local_file_path($remotefile) {
        return $this->filedir . DIRECTORY_SEPARATOR . basename($remotefile);;
    }

    /**
     * Copy file from FTP source.
     *
     * @param string $remotefile Remote file path.
     * @param string $localfile Local file path.
     *
     * @return bool
     */
    protected function copy_file($remotefile, $localfile) {
        $result = ftp_get($this->connid, $localfile, $remotefile, FTP_BINARY, 0);
        $this->log_copy_result($result, $remotefile, $localfile);

        return $result;
    }

    /**
     * Return full path to the remote file.
     *
     * @param string $remotefile Remote file name.
     *
     * @return mixed
     */
    protected function get_remote_file_path($remotefile) {
        return $remotefile;
    }

    /**
     * Log a result of the copying file.
     *
     * @param bool $result Result.
     * @param string $remotefile Remote file path.
     * @param string $localfile Local file path.
     */
    protected function log_copy_result($result, $remotefile, $localfile) {
        if ($result) {
            $this->log('copy_from_ftp', 'Completed copy ' . $remotefile . ' to ' . $localfile);
        } else {
            $this->log('copy_from_ftp', 'Failed to copy ' . $remotefile . ' to ' . $localfile, logger::TYPE_ERROR);
        }
    }

    /**
     * Log the result of getting files.
     *
     * @param array $matchedfiles A list of matched files.
     * @param array $processedfiles List of processed files.
     */
    protected function log_get_files_results(array $matchedfiles, array $processedfiles) {
        $this->log('match_files', count ($matchedfiles) . ' files matched regex ' . $this->settings['fileregex']);

        $this->log('result', count($processedfiles) . ' files processed ');
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

        if (empty($data[$this->get_config_form_prefix() . 'directory'])) {
            $errors[$this->get_config_form_prefix() . 'directory'] = 'Files directory could not be empty';
        }

        $regexfield = $this->get_config_form_prefix() . 'fileregex';

        if (empty($data[$this->get_config_form_prefix() . 'fileregex'])) {
            $errors[$this->get_config_form_prefix() . 'fileregex'] = 'Files regex could not be empty';
        } else {
            $errors[$regexfield] = $this->validate_regex($data[$regexfield]);
        }

        return $errors;
    }

    /**
     * @inheritdoc
     */
    protected function log($action, $info = '', $logtype = logger::TYPE_INFO, $trace = '') {
        $info = logger::get_instance()->to_string($info);
        $info = 'Host ' . $this->settings['host'] . ':' . $this->settings['port'] . ': ' . $info;
        parent::log($action, $info, $logtype, $trace);
    }

    /**
     * Close FTP connection.
     */
    public function __destruct() {
        if ($this->connid && is_resource($this->connid)) {
            ftp_close($this->connid);
        }
    }
}
