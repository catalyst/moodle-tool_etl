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

namespace tool_etl\processor;

use tool_etl\common\common_base;
use tool_etl\source\source_interface;
use tool_etl\target\target_interface;


defined('MOODLE_INTERNAL') || die;

abstract class processor_base extends common_base implements processor_interface {
    /**
     * Configured source instance.
     *
     * @var \tool_etl\source\source_interface
     */
    protected $source;

    /**
     * Configured target instance.
     *
     * @var \tool_etl\target\target_interface
     */
    protected $target;

    /**
     * @inheritdoc
     */
    public function set_source(source_interface $source) {
        $this->source = $source;
    }

    /**
     * @inheritdoc
     */
    public function set_target(target_interface $target) {
        $this->target = $target;
    }

    /**
     * @inheritdoc
     */
    public function process() {
        if (empty($this->source) || empty($this->target)) {
            throw new \coding_exception('Can not process. Source and target must be set!');
        }
    }

    /**
     * Return available processor options.
     *
     * @return array A list of existing processor classes.
     */
    final public static function get_options() {
        return array(
            'processor_default',
            'processor_lowercase',
            'processor_add_time_column'
        );
    }

}
