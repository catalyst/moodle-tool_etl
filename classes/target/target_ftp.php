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
 * Base processor class. All new processors have to extend this class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\target;

use tool_etl\config_field;

defined('MOODLE_INTERNAL') || die;

class target_ftp extends target_base {
    /**
     * Name of the source.
     *
     * @var string
     */
    protected $name = "FTP";

    protected $settings = array(
        'host' => '',
        'port' => 22,
        'username' => '',
        'password' => '',
        'directory' => '',
        'fileregex' => '',
    );

    protected $connid;
    protected $logginresult;


    public function __construct(array $settings) {
        parent::__construct($settings);

        if (!extension_loaded('ftp')) {
            throw new \Exception('PHP extension FTP is not loaded.');
        }

        $this->connect();
        $this->login();
    }

    protected function connect() {
        if (!empty($this->settings['host'])) {
            $this->connid = ftp_connect($this->settings['host'], $this->settings['port']);
        }
    }

    protected function login() {
        if ($this->connid) {
            $this->logginresult = ftp_login($this->connid, $this->settings['username'], $this->settings['password']);
        }
    }


    public function load_from_files($filepaths) {
        return true;
    }

    public function is_available() {
        if ($this->connid && $this->logginresult) {
            return true;
        }

        return false;
    }

    public function create_config_form_elements(\MoodleQuickForm $mform) {
        $elements = parent::create_config_form_elements($mform);

        $fields = array(
            'host' => new config_field('host', 'Host', 'text', $this->settings['host'],  PARAM_ALPHAEXT),
            'port' => new config_field('port', 'Port', 'text', $this->settings['port'], PARAM_INT),
            'username' => new config_field('username', 'User name', 'text', $this->settings['username'], PARAM_ALPHAEXT),
            'password' => new config_field('password', 'Password', 'passwordunmask', $this->settings['password'], PARAM_RAW),
            'directory' => new config_field('directory', 'Files directory', 'text', $this->settings['directory'], PARAM_SAFEPATH),
            'fileregex' => new config_field('fileregex', 'Files regex', 'text', $this->settings['fileregex'], PARAM_RAW),
        );

        foreach ($fields as $field) {
            $fieldname = $this->get_config_form_prefix() . $field->name;
            $element = $mform->createElement($field->type, $fieldname, $field->title);
            $mform->addElement($field->type, $fieldname, $field->title);
            $mform->setDefault($fieldname, $field->default);
            $mform->setType($fieldname, $field->filter);
            $elements[] = $element;
        }

        return $elements;
    }

    public function __destruct() {
        if ($this->connid) {
            ftp_close($this->connid);
        }
    }
}
