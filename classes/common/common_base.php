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
 * Base common class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\common;

defined('MOODLE_INTERNAL') || die;

abstract class common_base implements common_interface {
    /**
     * Name of the element.
     *
     * @var string
     */
    protected $name = '';

    /**
     * A list of settings.
     *
     * @var array
     */
    protected $settings = array();

    /**
     * Constructor.
     *
     * @param array $settings
     */
    public function __construct(array $settings = array()) {
        $this->set_settings($settings);
    }

    /**
     * @inheritdoc
     */
    public function get_name() {
        if (empty($this->name)) {
            throw new \coding_exception('Name should be not empty');
        }

        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public final function get_short_name() {
        $reflection = new \ReflectionClass($this);

        return $reflection->getShortName();
    }

    /**
     * @inheritdoc
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * @inheritdoc
     */
    public function set_settings(array $data) {
        foreach ($data as $name => $value) {
            if (isset($this->settings[$name])) {
                $this->settings[$name] = $value;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        return array();
    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        return array();
    }

    /**
     * Return a prefix for config form.
     *
     * @return string
     */
    public final function get_config_form_prefix() {
        return $this->get_short_name() . '-';
    }

    /**
     * Helper function to retrieve settings from submitted data.
     *
     * @param array $data Submitted data of the task settings form.
     *
     * @return array
     */
    public function get_settings_from_submitted_data(array $data) {
        $settings = array();

        foreach ($data as $name => $value) {
            if (strpos($name, $this->get_config_form_prefix()) === 0 ) {
                $result = substr($name, strlen($this->get_config_form_prefix()));

                if ($result) {
                    $settings[$result] = $value;
                }
            }
        }

        return $settings;
    }

    /**
     * Init an instance of required type element.
     *
     * @param string $type Element type: source, target or processor.
     * @param string $name A name of the class to initialise.
     * @param array $settings Settings to pass to the element.
     *
     * @return common_interface
     */
    public static function init($type, $name, $settings = array()) {
        $classname = "tool_etl\\$type\\" . $name;
        return new $classname($settings);
    }

    /**
     * Return a list of options of provided type.
     *
     * @param string $type Element type: source, target or processor.
     *
     * @return array
     */
    public static function options($type) {
        $baseclass = "tool_etl\\$type\\$type" . "_base";

        return $baseclass::get_options();
    }

    /**
     * A helper function to create elements in the provided form for particular instance of element.
     *
     * @param \MoodleQuickForm $mform
     * @param array $fields
     *
     * @return array
     */
    public function get_config_form_elements(\MoodleQuickForm $mform, array $fields) {
        $elements = array();

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

}
