<?php
namespace tool_etl;

interface capabilities_interface {
    function sources();
    function processors();
    function targets();
}