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

class source_folder extends source_base {

    /**
     * Name of the source.
     *
     * @var string
     */
    protected $name = "Folder";

    /**
     * Settings.
     *
     * @var array
     */
    protected $settings = array(
        'folder' => '',
        'fileregex' => '',
    );

    /**
     * @inheritdoc
     */
    public function extract() {
        if ($this->is_available()) {
            $this->data = new data($this->get_files());
        } else {
            throw new \Exception($this->name . ' source is not available!');
        }

        return $this->data;
    }

    /**
     * Return files.
     */
    protected function get_files() {
        $matchedfiles = array();
        $files = scandir($this->settings['folder']);

        $this->log('get_files', $files);

        if ($files) {
            foreach ($files as $file) {
                if ($this->should_extract($file)) {
                    $matchedfiles[] = $this->settings['folder'] . DIRECTORY_SEPARATOR . $file;
                }
            }
        }

        $this->log('match_files', count ($matchedfiles) . ' files matched regex ' . $this->settings['fileregex']);

        return $matchedfiles;
    }


    /**
     * @inheritdoc
     */
    public function is_available() {
        if (is_dir($this->settings['folder']) && is_readable($this->settings['folder'])) {
            return true;
        }

        $this->log('load_data', 'Folder is not readable ' . $this->settings['folder'], logger::TYPE_ERROR);

        return false;
    }

    /**
     * @inheritdoc
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        $elements = parent::create_config_form_elements($mform);

        $fields = array(
            'folder' => new config_field('folder', 'Files folder', 'text', $this->settings['folder'], PARAM_SAFEPATH),
            'fileregex' => new config_field('fileregex', 'Files regex', 'text', $this->settings['fileregex'], PARAM_RAW),
        );

        return array_merge($elements, $this->get_config_form_elements($mform, $fields));
    }

    /**
     * Check if we should copy the remote file.
     *
     * @param string $filename Remote file path.
     *
     * @return int
     */
    protected function should_extract($filename) {
        if ($filename == '.' || $filename == '..') {
            return false;
        }

        if (empty($this->settings['fileregex'])) {
            return true;
        } else {
            return preg_match($this->settings['fileregex'], $filename);
        }
    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        if (empty($data[$this->get_config_form_prefix() . 'folder'])) {
            $errors[$this->get_config_form_prefix() . 'folder'] = 'Files folder could not be empty';
        }

        $regexfield = $this->get_config_form_prefix() . 'fileregex';
        if (!empty($data[$regexfield])) {
            $regexerror = $this->validate_regex($data[$regexfield]);
            if (!empty($regexerror)) {
                $errors[$regexfield] = $regexerror;
            }
        }

        return $errors;
    }

}
