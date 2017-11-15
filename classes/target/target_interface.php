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
 * Target interface.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\target;

use tool_etl\common\common_interface;

defined('MOODLE_INTERNAL') || die;

interface target_interface extends common_interface {
    /**
     * Load data from array.
     *
     * @param array $data A data to load.
     *
     * @return bool
     */
    public function load(array $data);

    /**
     * Load data from files.
     *
     * @param array $filepaths A list of files.
     *
     * @return bool
     */
    public function load_from_files($filepaths);

    /**
     * Check if the target is available.
     *
     * @return bool
     */
    public function is_available();
}
