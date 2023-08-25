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
 * Plugin administration pages are defined here.
 * This file is a file analyzing tool and report plugin for Moodle - http://moodle.org/
 * @package     report_file_analyzer
 * @category    admin
 * @copyright   2019 Harry Bleckert for ASH Berlin <Harry.Bleckert@ASH-Berlin.eu>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Timeout at 3 hours.
set_time_limit(300);

if (!defined('NO_OUTPUT_BUFFERING')) {
    define('NO_OUTPUT_BUFFERING', true);
}
require(__DIR__ . '/../../config.php');

// Required files.
require_once($CFG->libdir .'/adminlib.php');
//require_once($CFG->dirroot.'/report/file_analyzer/locallib.php');

if ( !defined('MOODLE_INTERNAL') ) 
{	die("\n\nDirect access to this script is not possible!\n\n"); }



// Login and check capabilities.
require_login();
//require_capability('report/file_analyzer:view', context_system::instance());
// Get the step.
$step = optional_param('step', false, PARAM_TEXT);

// Set link & Layout.
admin_externalpage_setup('report_file_analyzer');
$context = context_system::instance();
$PAGE->set_context($context);

$PAGE->set_url(new moodle_url('/report/file_analyzer/index.php'));
$PAGE->set_title(get_string('pluginname', 'report_file_analyzer'));
$PAGE->set_pagelayout('report');

// Rendering.
//$output = $PAGE->get_renderer('report_file_analyzer');
//echo !$step ? $output->launcher() : $output->display();

if ( is_siteadmin() ) 
{	//define('NO_OUTPUT_BUFFERING', true);
	require( "./cli/file_analyzer.php"); 
}
else
{	echo $OUTPUT->header();
	echo $OUTPUT->heading(get_string('pluginname', 'report_file_analyzer'));
	echo "<br><hr><b>You must be Site Administrator to access this report</b><br>";
}

echo $OUTPUT->footer();

/*
 *
 SELECT * FROM (SELECT DISTINCT f.contenthash AS contenthash, f.filename AS filename,f.filesize AS filesize,
 f.filearea AS filearea, f.mimetype AS mimetype,f.timemodified AS timemodified, f.userid AS userid,
 f.author AS author, f.license AS license
FROM mdl_files AS f
where f.filesize>0 AND f.component != 'core'  AND f.timemodified>=1688162400  AND f.timemodified<=1692914400  AND f.filename LIKE '%combined.pdf%'
ORDER BY f.contenthash ) AS distinct_hash
ORDER by distinct_hash.timemodified DESC;
 */
