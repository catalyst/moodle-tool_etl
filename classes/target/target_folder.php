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
 * Server folder target class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\target;

use tool_etl\logger;

class target_folder extends target_dataroot {
    /**
     * Name of the source.
     *
     * @var string
     */
    protected $name = "Server folder";

    /**
     * @inheritdoc
     */
    protected function get_full_path() {
        return $this->settings['path'];
    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        if (empty($data[$this->get_config_form_prefix() . 'path'])) {
            $errors[$this->get_config_form_prefix() . 'path'] = 'Path can not be empty';
        }

        return $errors;
    }

    /**
     * @inheritdoc
     */
    public function is_available() {
        if (!empty($this->settings['clreateifnotexist'])) {
            check_dir_exists($this->get_full_path());
        }

        if (is_dir($this->get_full_path()) && is_writable($this->get_full_path())) {
            return true;
        }

        $this->log('load_data', 'Directory is not writable ' . $this->get_full_path(), logger::TYPE_ERROR);

        return false;
    }

}
