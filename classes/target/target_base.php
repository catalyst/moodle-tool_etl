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
 * Base Target class. All new targets have to extend this class.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\target;

use tool_etl\common\common_base;


defined('MOODLE_INTERNAL') || die;

abstract class target_base extends common_base implements target_interface {

    /**
     * Default load class.
     *
     * @param array $data
     *
     * @return bool
     *
     * @throws \coding_exception
     */
    public function load(array $data) {
        throw new \coding_exception('Loading from an array is not supported yet');
    }

    /**
     * Return available target options.
     *
     * @return array A list of existing target classes.
     */
    final public static function get_options() {
        return array(
            'target_dataroot',
            'target_ftp',
        );
    }
}
