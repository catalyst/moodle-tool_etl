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
 * Data root folder target class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\target;

use tool_etl\config_field;
use tool_etl\logger;
use tool_etl\data_interface;

defined('MOODLE_INTERNAL') || die;

class target_dataroot extends target_base {
    /**
     * Name of the source.
     *
     * @var string
     */
    protected $name = "Site data";

    /**
     * Data root path.
     *
     * @var string
     */
    protected $path;

    /**
     * Settings of the target.
     *
     * @var array
     */
    protected $settings = array(
        'path' => '',
        'filename' => '',
        'clreateifnotexist' => 0,
    );

    /**
     * Constructor.
     *
     * @param array $settings
     */
    public function __construct(array $settings = array()) {
        global $CFG;

        parent::__construct($settings);
        $this->path = $CFG->dataroot . DIRECTORY_SEPARATOR .  $this->settings['path'];
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

        $source = reset($filepaths); // We copy only one file.
        $target = $this->path . '/' . $this->settings['filename'];

        if (!copy($source, $target)) {
            $this->log('load_data', 'Failed to copy file ' . $source . ' to ' . $target, logger::TYPE_ERROR);
            return false;
        }

        $this->log('load_data', 'Successfully copied file ' . $source . ' to ' . $target);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        $elements = parent::create_config_form_elements($mform);

        $fields = array(
            'path' => new config_field(
                'path',
                ' Local folder path',
                'text',
                $this->settings['path'],
                PARAM_SAFEPATH
            ),
            'clreateifnotexist' => new config_field(
                'clreateifnotexist',
                'Create folder if not exists',
                'checkbox',
                $this->settings['clreateifnotexist'],
                PARAM_BOOL
            ),
            'filename' => new config_field(
                'filename',
                'File name',
                'text',
                $this->settings['filename'],
                PARAM_FILE
            ),
        );

        return array_merge($elements, $this->get_config_form_elements($mform, $fields));
    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        if (empty($data[$this->get_config_form_prefix() . 'path'])) {
            $errors[$this->get_config_form_prefix() . 'path'] = 'Local folder path could not be empty';
        }

        if (empty($data[$this->get_config_form_prefix() . 'filename'])) {
            $errors[$this->get_config_form_prefix() . 'filename'] = 'File name could not be empty';
        }

        return $errors;
    }

    /**
     * @inheritdoc
     */
    public function is_available() {
        if (!empty($this->settings['clreateifnotexist'])) {
            check_dir_exists($this->path);
        }

        if (is_dir($this->path) && is_writable($this->path)) {
            return true;
        }

        $this->log('load_data', 'Directory is not writable ' . $this->path, logger::TYPE_ERROR);

        return false;
    }

}
