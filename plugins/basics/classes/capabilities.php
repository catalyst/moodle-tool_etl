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
 * Data class.
 *
 * @package    tool_etl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace etl_basics;

defined('MOODLE_INTERNAL') || die;

class capabilities implements \tool_etl\capabilities_interface {

    public function sources() {
        return array(
            'source_ftp',
            'source_sftp',
            'source_sftp_key',
            'source_folder',
            'source_url',
        );
    }

    public function processors() {
        return array(
            'processor_default',
            'processor_lowercase',
        );
    }

    public function targets() {
        return array(
            'target_dataroot',
            'target_folder',
            'target_sftp_key',
        );
    }
}