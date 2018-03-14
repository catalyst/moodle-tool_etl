<?php
namespace etl_basics;

class capabilities implements \tool_etl\capabilities_interface {

    public function sources() {
        return array(
            'source_ftp',
            'source_sftp',
            'source_sftp_key',
            'source_folder',
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