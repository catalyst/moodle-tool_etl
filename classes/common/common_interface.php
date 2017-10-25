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
 * Common interface.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\common;

defined('MOODLE_INTERNAL') || die;

interface common_interface {
    /**
     * Return a name of the component.
     *
     * @return string
     */
    public function get_name();


    /**
     * Return a short name. Should be class name without namespace.
     *
     * @return string
     */
    public function get_short_name();

    /**
     * Return settings of the component.
     *
     * @return array
     */
    public function get_settings();

    /**
     * Set component settings.
     *
     * @param array $data A list of settings to set.
     */
    public function set_settings(array $data);

    /**
     * Create a list of config elements in the provided form and return a list of elements.
     *
     * @param \MoodleQuickForm $mform Config form.
     *
     * @return array
     */
    public function create_config_form_elements(\MoodleQuickForm $mform);

    /**
     * Validates submitted config fields.
     *
     * Gets called in validate function of the task form.
     *
     * @param array $data Array of ("fieldname"=>value) of submitted data
     * @param array $files Array of uploaded files "element_name"=>tmp_file_path
     * @param array $errors Errors already discovered in edit form validation
     *
     * @return array
     */
    public function validate_config_form_elements($data, $files, $errors);

}
