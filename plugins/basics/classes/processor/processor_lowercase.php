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
 * Lowercase required fields in CSV files.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace etl_basics\processor;

use tool_etl\data;
use tool_etl\logger;
use tool_etl\config_field;
use tool_etl\processor\processor_base;

defined('MOODLE_INTERNAL') || die;

class processor_lowercase extends processor_base {

    /**
     * A list of fields to lowercase from the config of the processor.
     *
     * @var array
     */
    protected $csvfields = array();

    /**
     * Settings.
     *
     * @var array
     */
    protected $settings = array(
        'csvfields' => '',
        'csvdelimiter' => '',
    );

    /**
     * Cache the current time.
     *
     * @var false|string
     */
    protected $now;

    /**
     * File dir for the processor to store all files there.
     *
     * @var string
     */
    protected $filedir;

    /**
     * Constructor.
     *
     * @param array $settings
     */
    public function __construct(array $settings = array()) {
        global $CFG;

        parent::__construct($settings);

        if (!empty($this->get_settings()['csvfields'])) {
            $this->csvfields = explode('||', $this->get_settings()['csvfields']);
        }

        $this->now = date('YmdHis', time());
        $this->filedir = $CFG->dataroot . DIRECTORY_SEPARATOR . $this->get_short_name();

        check_dir_exists($this->filedir);
    }

    /**
     * @inheritdoc
     *
     * - csvfields: a list of csv field that need to be updated. The names should be separated using ||.
     *   E.g. username||email||firstname
     *
     * - csvdelimiter: a delimiter used in the csv file.
     *
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        $elements = parent::create_config_form_elements($mform);

        $fields = array(
            'csvfields' => new config_field(
                'csvfields',
                'Fields to modify',
                'textarea',
                $this->settings['csvfields'],
                PARAM_ALPHAEXT
            ),
            '' => new config_field(
                'csvdelimiter',
                'Csv fields delimiter',
                'text',
                $this->settings['csvdelimiter'],
                PARAM_ALPHAEXT
            ),
        );

        return array_merge($elements, $this->get_config_form_elements($mform, $fields));
    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        $errors = parent::validate_config_form_elements($data, $files, $errors);

        if (!empty($data[$this->get_config_form_prefix() . 'csvfields'])) {

            if (empty($data[$this->get_config_form_prefix() . 'csvdelimiter'])) {
                $errors[$this->get_config_form_prefix() . 'csvdelimiter'] = 'Csv fields delimiter key could not be empty';
            } else {
                if (!array_key_exists($data[$this->get_config_form_prefix() . 'csvdelimiter'], $this->get_delimiter_options())) {
                    $errors[$this->get_config_form_prefix() . 'csvdelimiter'] = 'Invalid Csv fields delimiter';
                }
            }
        }

        return $errors;
    }

    /**
     * Return delimiter option list for config settings of the processor.
     *
     * @return array
     */
    protected function get_delimiter_options() {
        return array(
            ',' => 'Comma (,)',
            ';' => 'Semi-colon (;)',
            ':' => 'Colon (:)',
            '\t' => 'Tab (\t)',
            '|' => 'Pipe (|)',
        );
    }

    /**
     * @inheritdoc
     */
    public function process() {
        parent::process();

        $result = $this->source->extract();

        if (!empty($result->get_supported_formats())) {
            $files = $result->get_data('files');

            if (!empty($files)) {
                $newfiles = array();

                foreach ($files as $file) {
                    try {
                        $newfile = $this->process_file($file);
                        if ($newfile) {
                            $newfiles[] = $newfile;
                            $this->log('process', 'Successfully processed file ' . $file . ' to ' . $newfile, logger::TYPE_INFO);
                        }
                    } catch (\Exception $e) {
                        $this->log('process', 'Failed processing file ' . $file . ' ' . $e->getMessage(), logger::TYPE_ERROR);
                    }
                }

                $newresult = new data($newfiles);
                $this->target->load($newresult);
            }
        } else {
            $this->log('process', 'No data to process', logger::TYPE_WARNING);
        }

        return true;
    }

    /**
     * Process one file.
     *
     * @param string $file Path to the file.
     *
     * @return bool|string
     * @throws \Exception
     * @throws \coding_exception
     */
    protected function process_file($file) {
        $data = $this->read_csv_file_as_array($file, $this->get_settings()['csvdelimiter']);
        $processeddata = $this->lowercase_data($data);

        if (empty($processeddata)) {
            $this->log('process', 'Skip processing file ' . $file . ' Empty file.', logger::TYPE_WARNING);
            return false;
        }

        $newfile = $this->get_target_file_path($file);

        if (file_exists($newfile)) {
            $this->log(
                'process',
                'Skip processing file ' . $file . ' File ' . $newfile . ' exists.',
                logger::TYPE_WARNING
            );
            return false;
        }

        $this->save_array_as_csv_file($newfile, $processeddata, $this->get_settings()['csvdelimiter']);

        return $newfile;
    }

    /**
     * Lowercase data.
     *
     * @param array $data Data to process through. Each array element is a row array keyed by csv fields.
     *
     * @return array
     */
    protected function lowercase_data(array $data) {
        $processeddata = array();

        foreach ($data as $row) {
            foreach ($this->csvfields as $key) {
                if (!empty($row[$key])) {
                    $row[$key] = strtolower($row[$key]);
                }
            }

            $processeddata[] = $row;
        }

        // Add a header row.
        if (!empty($processeddata)) {
            $header = array_keys($processeddata[0]);
            array_unshift($processeddata, $header);
        }

        return $processeddata;
    }

    /**
     * Get target file path.
     *
     * @param string $file Path to the processing file.
     *
     * @return string
     */
    protected function get_target_file_path($file) {
        $filename = basename($file);

        check_dir_exists($this->filedir . DIRECTORY_SEPARATOR . $this->now);
        $targetfile = $this->filedir . DIRECTORY_SEPARATOR . $this->now . DIRECTORY_SEPARATOR . $filename;

        return $targetfile;
    }

    /**
     * Get data from CSV file as array.
     *
     * @param string $filepath A file path. E.g. data.csv.
     * @param string $delimiter A CSV delimiter. E.g. "," or "|".
     * @param bool $includeheader If true the first line will be treated as a header.
     *
     * @return array Array representation of CSV data or false if failed.
     *
     * @throws \Exception if something went wrong.
     */
    public function read_csv_file_as_array($filepath, $delimiter = ",", $includeheader = true) {

        if (!is_file($filepath) || !is_readable($filepath)) {
            throw new \Exception('The import file is not exist or it\'s not readable: ' . $filepath);
        }

        $header = null;
        $data = array();

        if (($handle = fopen($filepath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 10000, $delimiter)) !== false) {

                if ($includeheader) {
                    if (!$header) {
                        $header = $row;
                    } else {
                        if (count($header) == count($row)) {
                            $data[] = array_combine($header, $row);
                        }
                    }
                } else {
                    $data[] = $row;
                }
            }
            fclose($handle);
        } else {
            throw new \Exception('Can\'t open the import file for reading: ' . $filepath);
        }

        return $data;
    }

    /**
     * Saves array to CSV file.
     *
     * @param string $filepath A file path. E.g. data.csv.
     * @param array $filedata A data to save to the file.
     * @param string $delimiter A CSV delimiter. E.g. "," or "|".
     *
     * @return bool True.
     *
     * @throws \Exception If something went wrong.
     */
    public function save_array_as_csv_file($filepath, array $filedata, $delimiter = ',') {
        if (is_dir($filepath)) {
            throw new \Exception('Specified export file path is a dir: ' . $filepath);
        }

        if (($handle = fopen($filepath, 'w')) !== false) {

            foreach ($filedata as $row) {
                $result = fputcsv($handle, $row, $delimiter);

                if (!$result) {
                    throw new \Exception('Can\'t write to the export file: ' . $filepath);
                }
            }
        } else {
            throw new \Exception('Can\'t open the export file: ' . $filepath);
        }

        fclose($handle);

        return true;
    }

}
