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
 * Data class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl;

defined('MOODLE_INTERNAL') || die;

class data implements data_interface {

    /**
     * Supported formats of data.
     *
     * @var array
     */
    protected $formats = array('files', 'string', 'array', 'object');

    /**
     * Data as files.
     *
     * @var array|null
     */
    protected $files = null;

    /**
     * Data as a string.
     *
     * @var string|null
     */
    protected $string = null;

    /**
     * Data as array.
     *
     * @var array|null
     */
    protected $array  = null;

    /**
     * Data as object.
     *
     * @var null|\stdClass
     */
    protected $object = null;

    /**
     * Constructor.
     *
     * @param array|null $files
     * @param null $string
     * @param array|null $array
     * @param \stdClass|null $object
     */
    public function __construct(array $files = null, $string = null, array $array = null, \stdClass $object = null) {
        $this->files = $files;
        $this->string = $string;
        $this->array = $array;
        $this->object = $object;
    }

    /**
     * @inheritdoc
     */
    public function get_supported_formats() {
        $supported = array();

        foreach ($this->formats as $format) {
            if (!is_null($this->$format)) {
                $supported[] = $format;
            }
        }

        return $supported;
    }

    /**
     * @inheritdoc
     */
    public function get_data($format) {
        if (!in_array($format, $this->get_supported_formats())) {
            throw new \Exception('Data is not available in ' . $format . ' format');
        }

        return $this->$format;
    }

}
