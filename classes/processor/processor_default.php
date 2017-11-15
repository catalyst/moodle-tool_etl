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
 * Default processor instance.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\processor;

defined('MOODLE_INTERNAL') || die;

class processor_default extends processor_base {

    /**
     * Name of the processor.
     *
     * @var string
     */
    protected $name = "Default processor";

    /**
     * @inheritdoc
     */
    public function process() {
        try {
            parent::process();

            $this->source->extract();

            if ($sourcefiles = $this->source->get_file_paths()) {
                $this->target->load_from_files($sourcefiles);
            } else if ($data = $this->source->get_data()) {
                $this->target->load($data);
            } else {
                // Log empty.
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
