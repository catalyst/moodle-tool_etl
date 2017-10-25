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
 * A form for manipulating tasks,
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\form;

use tool_etl\common\common_base;
use tool_etl\task_interface;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/formslib.php");

class task_form extends \moodleform {
    /**
     * Task instance.
     *
     * @var task_interface
     */
    protected $task;

    /**
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    public function definition() {
        $mform = $this->_form;
        $this->task = $this->_customdata['task'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $this->task->id);

        $mform->addElement('header', 'sourcesettings', 'Source');
        $mform->addElement('select', 'source', 'Select source', $this->get_list_of_options('source'));
        $mform->setType('source', PARAM_ALPHAEXT);
        $mform->setDefault('source', $this->task->source->get_short_name());
        $this->add_config_fields_anchor('source');

        $mform->addElement('header', 'targetsettings', 'Target');
        $mform->addElement('select', 'target', 'Select target', $this->get_list_of_options('target'));
        $mform->setType('target', PARAM_ALPHAEXT);
        $mform->setDefault('target', $this->task->target->get_short_name());
        $this->add_config_fields_anchor('target');


        $mform->addElement('header', 'processorsettings', 'Processor');
        $mform->addElement('select', 'processor', 'Select processor', $this->get_list_of_options('processor'));
        $mform->setType('processor', PARAM_ALPHAEXT);
        $mform->setDefault('processor', $this->task->processor->get_short_name());
        $this->add_config_fields_anchor('processor');

        $mform->registerNoSubmitButton('updateform');
        $mform->addElement('submit', 'updateform', 'updateform');
        $this->add_action_buttons();
    }

    /**
     * Fill in the current page data for this course.
     */
    public function definition_after_data() {
        $this->add_config_fields('source');
        $this->add_config_fields('target');
        $this->add_config_fields('processor');
    }

    /**
     * @inheritdoc
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $types = array('source', 'target', 'processor');

        foreach ($types as $type) {
            $typeerrors = $this->validate_config_fields($type, $data[$type], $data, $files, $errors);

            if (!empty($typeerrors) && is_array($typeerrors)) {
                $errors = array_merge($errors, $typeerrors);
            }
        }

        return $errors;
    }

    /**
     * Get a list of available options for provide type of item in the form.
     * @param $type
     *
     * @return array
     */
    protected function get_list_of_options($type) {
        $options = array();

        foreach (common_base::options($type) as $classname) {
            $instance = common_base::init($type, $classname);
            $options[$instance->get_short_name()] = $instance->get_name();
        }

        return $options;
    }

    /**
     * Add config fields based on selected type.
     *
     * @param string $type Type of item, source, target or processor.
     */
    protected function add_config_fields($type) {
        $mform = $this->_form;

        $data = $mform->getElementValue($type);

        if (isset($data[0])) {
            $selectedtype = $data[0];

            if ($this->task->$type->get_short_name() != $selectedtype) {
                $instance = common_base::init($type, $selectedtype);
            } else {
                $instance = $this->task->$type;
            }

            foreach ($instance->create_config_form_elements($mform) as $element) {
                if ($element instanceof \HTML_QuickForm_element) {
                    $mform->insertElementBefore(
                        $mform->removeElement($element->getName(), false),
                        $this->build_placeholder_name($type)
                    );
                }
            }
        }
    }

    /**
     * Validate configuration fields data for selected type element.
     *
     * @param string $type Selected type: source, target or processor.
     * @param string $name A name of the selected type.
     * @param \stdClass $data Submitted data.
     * @param array $files Submitted files.
     * @param array $errors Already exist errors.
     *
     * @return array A list of errors.
     */
    protected function validate_config_fields($type, $name, $data, $files, $errors) {
        return common_base::init($type, $name)->validate_config_form_elements($data, $files, $errors);
    }

    /**
     * Add an anchor to move config fields to it later.
     *
     * @param string $type A name of required element type: source, target or processor.
     */
    protected function add_config_fields_anchor($type) {
        $mform = $this->_form;

        $mform->addElement('hidden', $this->build_placeholder_name($type));
        $mform->setType( $this->build_placeholder_name($type), PARAM_BOOL);

    }

    /**
     * Build a name of placeholder depending on provided type.
     *
     * @param string $type A name of required element type: source, target or processor.
     *
     * @return string
     */
    protected function build_placeholder_name($type) {
        return $type . 'placeholder';
    }

}

