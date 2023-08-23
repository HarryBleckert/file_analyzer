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
 * Plugin version and other meta-data are defined here.
 *
 * @package     report_file_analyzer
 * @copyright   2019 Harry Bleckert for ASH Berlin <Harry.Bleckert@ASH-Berlin.eu>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// compatibility for Moodle version below 3.x
if (!isset($plugin)) {
    $plugin = new stdClass();
}


$plugin->component = 'report_file_analyzer';
$plugin->release   = 'v4.1.0';
$plugin->version   = 2023082300;
$plugin->requires  = 2016041901;
$plugin->maturity  = MATURITY_BETA;
