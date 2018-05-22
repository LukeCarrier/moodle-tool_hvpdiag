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
 * H5P diagnostics.
 *
 * @package tool_hvpdiag
 * @author Luke Carrier <luke.carrier@avadolearning.com>
 * @copyright 2018 AVADO Learning
 */

use tool_hvpdiag\impex;

define('CLI_SCRIPT', true);
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config.php';
require_once "{$CFG->libdir}/csvlib.class.php";

$syscontext = context_system::instance();

$archivefilename = $_SERVER['PWD'] . '/' . impex::FILENAME;
$archive = new ZipArchive();
$opened = $archive->open(
        $archivefilename,
        ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($opened !== true) {
    mtrace("Failed to open archive {$archivefilename} ({$opened})");
    die;
}
$archive->addEmptyDir('db');
$archive->addEmptyDir('file');

$csvs = [];
foreach (impex::$HVP_TABLES as $table) {
    $qualifiedtable = "hvp_{$table}";
    $csv = new csv_export_writer();
    $csvs[$table] = $csv;
    $csv->set_filename($table);
    $csv->add_data(array_keys($DB->get_columns($qualifiedtable)));
    $rs = $DB->get_recordset($qualifiedtable);
    foreach ($rs as $record) {
        $csv->add_data((array) $record);
    }
    $rs->close();
    $archive->addFile($csv->path, "db/{$table}.csv");
}

$csv = new csv_export_writer();
$csvs['file'] = $csv;
$csv->set_filename('file');
$csv->add_data(array_keys($DB->get_columns('files')));
$rs = $DB->get_recordset_sql(impex::SQL_ALL_FILES, [
    'component'    => 'mod_hvp',
    'syscontextid' => $syscontext->id,
]);
foreach ($rs as $record) {
    $filedir = isset($CFG->filedir)
            ? $CFG->filedir : "{$CFG->dataroot}/filedir";
    $filedirpath = $filedir
            . '/' . substr($record->contenthash, 0, 2)
            . '/' . substr($record->contenthash, 2, 2)
            . '/' . $record->contenthash;

    $csv->add_data((array) $record);
    if (is_file($filedirpath)) {
        $archive->addFile($filedirpath, "file/{$record->contenthash}");
    } else {
        mtrace("Missing file {$record->contenthash}");
    }
}
$rs->close();
$archive->addFile($csv->path, "db/files.csv");

$archive->close();
foreach ($csvs as $csv) {
    unset($csv);
}
