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
    protected $tempdir;

    /**
     * @inheritdoc
     */
    public function __construct(array $settings = array()) {
        global $CFG;

        parent::__construct($settings);

        if (!extension_loaded('ftp')) {
            throw new \Exception('PHP extension FTP is not loaded.');
        }

        $this->tempdir = $CFG->tempdir . DIRECTORY_SEPARATOR . 'source_ftp';
        check_dir_exists($this->tempdir);
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
            $this->filepaths = $this->get_files();
        } else {
            throw new \Exception($this->name . ' source is not available!');
        }
    }

    /**
     * @inheritdoc
     */
    public function is_available() {
        if ($this->connid && $this->logginresult) {
            return true;
        }

        return false;
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
        $remotefiles = ftp_nlist($this->connid, $this->settings['directory']);

        if ($remotefiles) {
            foreach ($remotefiles as $remotefile) {
                if (preg_match($this->settings['fileregex'], $remotefile)) {
                    $localfile = $this->tempdir . DIRECTORY_SEPARATOR . basename($remotefile);

                    if (ftp_get($this->connid, $localfile, $remotefile, FTP_BINARY, 0)) {
                        $localfiles[] = $localfile;
                    }
                }
            }
        }

        return $localfiles;
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

        if (empty($data[$this->get_config_form_prefix() . 'fileregex'])) {
            $errors[$this->get_config_form_prefix() . 'fileregex'] = 'Files regex could not be empty';
        }

        return $errors;
    }

    /**
     * Close FTP connection.
     */
    public function __destruct() {
        if ($this->connid) {
            ftp_close($this->connid);
        }
    }
}
