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
 * Base source class. All new sources have to extend this class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\source;

use tool_etl\common\common_base;
use tool_etl\data_interface;

defined('MOODLE_INTERNAL') || die;

abstract class source_base extends common_base implements source_interface {

    /**
     * Result of extraction.
     *
     * @var data_interface
     */
    protected $data;

    /**
     * Return available source options.
     *
     * @return array A list of existing source classes.
     */
    final public static function get_options() {
        $plugins = \core_component::get_plugin_list('etl');
        $options = [];
        foreach ($plugins as $name => $dir) {
            $classname = "\\etl_$name\\capabilities";
            /** @var \tool_etl\capabilities_interface $capabilities */
            $capabilities = new $classname();
            foreach ($capabilities->sources() as $item) {
                $options[] = (object)[
                    'subplugin'  => "etl_$name",
                    'classname'  => $item,
                ];
            }
        }
        return $options;
    }

}
