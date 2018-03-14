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
 * FTP source.
 *
 * @package    tool_etl
 * @copyright  2017 Dmitrii Metelkin <dmitriim@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace etl_basics\source;

use tool_etl\config_field;
use tool_etl\data;
use tool_etl\logger;
use tool_etl\source\source_base;

defined('MOODLE_INTERNAL') || die;

class source_folder extends source_base {

    /**
     * Settings.
     *
     * @var array
     */
    protected $settings = array(
        'folder' => '',
        'fileregex' => '',
        'delete' => 0,
    );

    /**
     * A temp folder to save files.
     *
     * @var string
     */
    protected $filedir;

    /**
     * Date and time now.
     *
     * @var string
     */
    protected $now;

    public function __construct(array $settings = array()) {
        global $CFG;

        parent::__construct($settings);

        $this->now = date('YmdHis', time());

        $this->filedir = $CFG->dataroot . DIRECTORY_SEPARATOR . $this->get_short_name();
        check_dir_exists($this->filedir);
    }

    /**
     * @inheritdoc
     */
    public function extract() {
        if ($this->is_available()) {
            $this->data = new data($this->get_files());
        } else {
            throw new \Exception($this->name . ' source is not available!');
        }

        return $this->data;
    }

    /**
     * Return folder path.
     *
     * @return string
     */
    protected function get_folder_path() {
        return $this->settings['folder'];
    }

    /**
     * Return files.
     */
    protected function get_files() {
        $matchedfiles = array();
        $localfiles = array();

        $files = scandir($this->get_folder_path());

        $this->log('get_files', $files);

        if ($files) {
            foreach ($files as $file) {
                if ($this->should_extract($file)) {
                    $localfile = $this->get_local_file_path($file);
                    $remotefile = $this->get_folder_path() . DIRECTORY_SEPARATOR . $file;
                    if ($this->copy_file($remotefile, $localfile)) {
                        $localfiles[] = $localfile;

                        if (!empty($this->settings['delete'])) {
                            $this->delete_remote_file($remotefile);
                        }
                    }

                    $matchedfiles[] = $remotefile;
                }
            }
        }

        $this->log('match_files', count ($matchedfiles) . ' files matched regex ' . $this->settings['fileregex']);

        return $localfiles;
    }

    /**
     * Get a path of local file to copy.
     *
     * @param string $remotefile Remote file path.
     *
     * @return string
     */
    protected function get_local_file_path($remotefile) {
        $localfolder = $this->filedir . DIRECTORY_SEPARATOR . $this->now;
        check_dir_exists($localfolder);
        return $localfolder . DIRECTORY_SEPARATOR . basename($remotefile);
    }

    /**
     * Copy file from source.
     *
     * @param string $remotefile Remote file path.
     * @param string $localfile Local file path.
     *
     * @return bool
     * @throws \coding_exception
     */
    protected function copy_file($remotefile, $localfile) {
        $result = copy($remotefile, $localfile);

        if ($result) {
            $this->log('copy_from_folder', 'Completed copy ' . $remotefile . ' to ' . $localfile);
        } else {
            $this->log('copy_from_folder', 'Failed to copy ' . $remotefile . ' to ' . $localfile, logger::TYPE_ERROR);
        }

        return $result;
    }

    /**
     * Delete remote file.
     *
     * @param string $filepath File path.
     *
     * @throws \coding_exception
     */
    protected function delete_remote_file($filepath) {
        if (!$this->delete_file($filepath)) {
            $this->log('delete_file', 'Failed to delete ' . $filepath, logger::TYPE_ERROR);
        } else {
            $this->log('delete_file', 'Successfully deleted ' . $filepath);
        }
    }

    /**
     * Delete file.
     *
     * @param string $filepath File path.
     *
     * @return bool
     */
    protected function delete_file($filepath) {
        if (!is_writable($filepath)) {
            return false;
        }

        return unlink($filepath);
    }

    /**
     * @inheritdoc
     */
    public function is_available() {
        if (is_dir($this->get_folder_path()) && is_readable($this->get_folder_path())) {
            return true;
        }

        $this->log('load_data', 'Folder is not readable ' . $this->get_folder_path(), logger::TYPE_ERROR);

        return false;
    }

    /**
     * @inheritdoc
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        $elements = parent::create_config_form_elements($mform);

        $fields = array(
            'folder' => new config_field('folder', 'Files folder', 'text', $this->settings['folder'], PARAM_SAFEPATH),
            'fileregex' => new config_field('fileregex', 'Files regex', 'text', $this->settings['fileregex'], PARAM_RAW),
            'delete' => new config_field('delete', 'Delete loaded files', 'advcheckbox', $this->settings['delete'], PARAM_BOOL),
        );

        return array_merge($elements, $this->get_config_form_elements($mform, $fields));
    }

    /**
     * Check if we should copy the remote file.
     *
     * @param string $filename Remote file path.
     *
     * @return int
     */
    protected function should_extract($filename) {
        if ($filename == '.' || $filename == '..') {
            return false;
        }

        if (empty($this->settings['fileregex'])) {
            return true;
        } else {
            return preg_match($this->settings['fileregex'], $filename);
        }
    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        if (empty($data[$this->get_config_form_prefix() . 'folder'])) {
            $errors[$this->get_config_form_prefix() . 'folder'] = 'Files folder could not be empty';
        }

        $regexfield = $this->get_config_form_prefix() . 'fileregex';
        if (!empty($data[$regexfield])) {
            $regexerror = $this->validate_regex($data[$regexfield]);
            if (!empty($regexerror)) {
                $errors[$regexfield] = $regexerror;
            }
        }

        return $errors;
    }

}
