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

namespace tool_etl;
use tool_etl\source\source_interface;
use tool_etl\target\target_interface;
use tool_etl\processor\processor_interface;

defined('MOODLE_INTERNAL') || die;

/**
 * An interface describing task class behaviour.
 */
interface task_interface {
    public function __construct($id = 0);
    public function set_source(source_interface $source);
    public function set_target(target_interface $target);
    public function set_processor(processor_interface $processor);
    public function add_schedule(scheduler $schedule);
    public function save();
    public function delete();
    public function execute();
    public function __get($name);
}
