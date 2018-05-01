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
use tool_etl\result;


defined('MOODLE_INTERNAL') || die;

abstract class target_base extends common_base implements target_interface {

    /**
     * Return available target options.
     *
     * @return array A list of existing target classes.
     */
    final public static function get_options() {
        $plugins = \core_component::get_plugin_list('etl');
        $options = [];
        foreach ($plugins as $name => $dir) {
            $classname = "\\etl_$name\\capabilities";
            /** @var \tool_etl\capabilities_interface $capabilities */
            $capabilities = new $classname();
            foreach ($capabilities->targets() as $item) {
                $options[] = (object)[
                    'subplugin'  => "etl_$name",
                    'classname'  => $item,
                ];
            }
        }
        return $options;
    }

    /**
     * @inheritdoc
     */
    public function load(data_interface $data) {
        $result = new result();

        if (!$this->is_available()) {
            return $result;
        }

        foreach ($data->get_supported_formats() as $format) {
            $result->add_result($format, false);
            $functionname = 'load_from_' . $format;

            if (!method_exists($this, $functionname)) {
                $this->log('load_data', 'Loading from ' . $format . ' is not supported yet', logger::TYPE_WARNING);
            } else {
                try {
                    if ($datainformat = $data->get_data($format)) {
                        $formatresult = $this->$functionname($datainformat);
                        $result->add_result($format, $formatresult);
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

        return $result;
    }

    /**
     * Base load from files method.
     *
     * This method could be overridden in a child class.
     *
     * @param array $data Data as a list of files.
     *
     * @return bool
     */
    protected function load_from_files($data) {
        return false;
    }

    /**
     * Base load from array method.
     *
     * This method could be overridden in a child class.
     *
     * @param array $data Data as array.
     *
     * @return bool
     */
    protected function load_from_array($data) {
        return false;
    }

    /**
     * Base load from array method.
     *
     * This method could be overridden in a child class.
     *
     * @param string $data Data as string.
     *
     * @return bool
     */
    protected function load_from_string($data) {
        return false;
    }

    /**
     * Base load from array method.
     *
     * This method could be overridden in a child class.
     *
     * @param \stdClass $data Data as object.
     *
     * @return bool
     */
    protected function load_from_object($data) {
        return false;
    }

    /**
     * Return target file name based on configuration.
     *
     * @param string $filepath File path.
     *
     * @return string
     */
    protected function get_target_file_name($filepath) {
        if (!empty($this->settings['filename'])) {
            $filename = basename($this->settings['filename']);
        } else {
            $filename = basename($filepath);
        }

        if (!empty($this->settings['addtime'])) {
            $filename = $this->append_filename_by_date($filename);
        }

        return $filename;
    }

    /**
     * Backup loaded files if required.
     *
     * @param array $filepaths A list of files to backup.
     */
    protected function backup_files(array $filepaths) {
        global $CFG;

        if (!empty($this->settings['backupfiles'])) {
            $date = date($this->get_date_format(), time());
            $backupfolder = $CFG->dataroot . DIRECTORY_SEPARATOR . $this->get_short_name() . DIRECTORY_SEPARATOR . $date;
            check_dir_exists($backupfolder);

            foreach ($filepaths as $filepath) {
                $target = $backupfolder . DIRECTORY_SEPARATOR . basename($filepath);
                if (!copy($filepath, $target)) {
                    $this->log('backup_files', 'Failed to back up file ' . $filepath . ' to ' . $target, logger::TYPE_ERROR);
                } else {
                    $this->log('backup_files', 'Successfully backed up file ' . $filepath . ' to ' . $target);
                }
            }
        }
    }

}
