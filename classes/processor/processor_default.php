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
 * Does not manipulate data. Simply pass it to target.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_etl\processor;

use tool_etl\logger;

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

            $result = $this->source->extract();

            if (empty($result->get_supported_formats())) {
                $this->log('process', 'No data to process', logger::TYPE_WARNING);
            }

            $this->target->load($result);

        } catch (\Exception $e) {
            $this->log('process', $e->getMessage(), logger::TYPE_ERROR);
        }

        return true;
    }
}
