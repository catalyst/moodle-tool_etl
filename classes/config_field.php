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
 * Config field class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl;

class config_field {
    /**
     * A list of parameters for every config field instance.
     * @var array
     */
    protected $parameters = array('name', 'title', 'type', 'default', 'filter', 'options', 'help');

    /**
     * Name parameter.
     *
     * @var string
     */
    protected $name;

    /**
     * Title parameter.
     *
     * @var string
     */
    protected $title;

    /**
     * Type parameter.
     *
     * @var string
     */
    protected $type;

    /**
     * Default value of the config field.
     *
     * @var mixed
     */
    protected $default;

    /**
     * Filter for the config field.
     *
     * @var string
     */
    protected $filter;

    protected $options;

    /**
     * Display help button.
     *
     * @var boolean
     */
    protected $help;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $title
     * @param string $type
     * @param mixed $default
     * @param string $filter
     * @param array $options
     */
    public function __construct($name, $title, $type = 'text', $default = null, $filter = PARAM_ALPHAEXT,
            $options = null, $help = false) {
        $this->name = $name;
        $this->title = $title;
        $this->type = $type;
        $this->default = $default;
        $this->filter = $filter;
        $this->options = $options;
        $this->help = $help;
    }

    /**
     * Magic method to return parameter.
     *
     * @param $name
     *
     * @return mixed
     * @throws \coding_exception If parameter is not found.
     */
    public function __get($name) {
        if (!in_array($name, $this->parameters)) {
            throw new \coding_exception('Unknown parameter ' . $name);
        }

        return $this->$name;
    }
}
