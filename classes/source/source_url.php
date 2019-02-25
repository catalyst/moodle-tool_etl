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
 * URL source.
 *
 * @package    tool_etl
 * @copyright  2019 John Yao <johnyao@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace tool_etl\source;

use curl;
use tool_etl\config_field;
use tool_etl\data;
use tool_etl\logger;


defined('MOODLE_INTERNAL') || die;

class source_url extends source_base
{

    /**
     * Name of the source.
     *
     * @var string
     */
    protected $name = "URL";

    /**
     * Settings.
     *
     * @var array
     */
    protected $settings = array(
        'address' => '',
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
            $this->data = new data([$this->get_file()]);
        } else {
            throw new \Exception($this->name . ' source is not available!');
        }

        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function is_available() {

        if ($this->check_remote_url($this->get_url_path(), $this->get_local_file_path($this->get_url_path()))) {
            return true;
        }

        $this->log('load_data', 'URL is not valid ' . $this->get_url_path(), logger::TYPE_ERROR);

        return false;
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
            $this->log('copy_from_url', 'Completed copy ' . $remotefile . ' to ' . $localfile);
        } else {
            $this->log('copy_from_url', 'Failed to copy ' . $remotefile . ' to ' . $localfile, logger::TYPE_ERROR);
        }

        return $result;
    }

    /**
     * Return url address.
     *
     * @return string
     */
    protected function get_url_path() {
        return $this->settings['address'];
    }

    /**
     * @inheritdoc
     */
    public function create_config_form_elements(\MoodleQuickForm $mform) {
        $elements = parent::create_config_form_elements($mform);

        $fields = array(
            'address' => new config_field('address', 'URL', 'text', $this->settings['address'], PARAM_URL),
        );

        return array_merge($elements, $this->get_config_form_elements($mform, $fields));
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
     * Check if remote file downloadable.
     *
     * @param string $url Remote file url.
     *
     * @return int
     */
    protected function check_remote_url($url) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new curl();

        $curl->setopt(array('CURLOPT_FOLLOWLOCATION' => true, 'CURLOPT_MAXREDIRS' => 5));
        $cmsg = $curl->head($url);
        $info = $curl->get_info();

        if (empty($info['http_code']) || $info['http_code'] != 200) {
            return false;
        }
        return true;
    }

    public function get_file() {
        $file = $this->get_url_path();
        $localfile = $this->get_local_file_path($file);
        $this->copy_file($file, $localfile);

        return $localfile;
    }

    /**
     * @inheritdoc
     */
    public function validate_config_form_elements($data, $files, $errors) {
        if (empty($data[$this->get_config_form_prefix() . 'address'])) {
            $errors[$this->get_config_form_prefix() . 'address'] = get_string('address_empty', 'tool_etl');
        }

        $url = $data[$this->get_config_form_prefix() . 'address'];
        if ($this->check_remote_url($url, $this->get_local_file_path($url)) != true) {
            $errors[$this->get_config_form_prefix() . 'address'] = get_string('address_not_url', 'tool_etl');
        }

        return $errors;
    }

}
