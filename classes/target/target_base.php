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
use tool_etl\data_interface;
use tool_etl\logger;


defined('MOODLE_INTERNAL') || die;

abstract class target_base extends common_base implements target_interface {

    /**
     * Return available target options.
     *
     * @return array A list of existing target classes.
     */
    final public static function get_options() {
        return array(
            'target_dataroot',
        );
    }

    /**
     * @inheritdoc
     */
    public function load(data_interface $data) {
        if (!$this->is_available()) {
            return false;
        }

        foreach ($data->get_supported_formats() as $format) {
            $functionname = 'load_from_' . $format;

            if (!method_exists($this, $functionname)) {
                $this->log('load_data', 'Loading from ' . $format . ' is not supported yet', logger::TYPE_WARNING);
            } else {
                try {
                    $data = $data->get_data($format);

                    if (!empty($data)) {
                        $this->$functionname($data);
                    } else {
                        $this->log('load_data', 'Nothing to load', logger::TYPE_WARNING);
                    }
                } catch (\Exception $e) {
                    $this->log(
                        'load_data',
                        "Error in loading data in $format format: " . $e->getMessage(),
                        logger::TYPE_ERROR,
                        $e->getTraceAsString()
                    );
                }
            }
        }

        return true;
    }
}
