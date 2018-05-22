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
$opened = $archive->open($archivefilename);
if ($opened !== true) {
    mtrace("Failed to open archive {$archivefilename} ({$opened})");
    die;
}

try {
    $transaction = $DB->start_delegated_transaction();

    foreach (impex::$HVP_TABLES as $table) {
        $qualifiedtable = "hvp_{$table}";
        $csviid = csv_import_reader::get_new_iid('hvpdiag');
        $csv = new csv_import_reader($csviid, 'hvpdiag');
        $count = $csv->load_csv_content(
                $archive->getFromName("db/{$table}.csv"), 'utf-8', ',');
        $csv->init();
        $columns = $csv->get_columns();
        if ($count === false || $count === 1) {
            throw new coding_exception("Unable to read file db/{$table}.csv");
        }

        mtrace("importing table {$table}", '');
        $DB->execute("truncate table {{$qualifiedtable}}");
        while ($row = $csv->next()) {
            mtrace('.', '');
            $record = (object) array_combine($columns, $row);
            $DB->insert_record_raw($qualifiedtable, $record);
        }
        $csv->close();
        mtrace('');
    }

    $csviid = csv_import_reader::get_new_iid('hvpdiag');
    $csv = new csv_import_reader($csviid, 'hvpdiag');
    $content = $archive->getFromName("db/files.csv");
    $count = $csv->load_csv_content(
            $content, 'utf-8', ',');
    $csv->init();
    $columns = $csv->get_columns();
    if ($count === false || $count === 1) {
        throw new coding_exception("Unable to read file db/file.    s.csv");
    }

    $filedir = isset($CFG->filedir)
            ? $CFG->filedir : "{$CFG->dataroot}/filedir";

    mtrace('importing table files', '');
    $DB->delete_records('files', [
        'component' => 'mod_hvp',
        'contextid' => $syscontext->id,
    ]);
    while ($row = $csv->next()) {
        mtrace('.', '');
        $record = (object) array_combine($columns, $row);
        $record->id = null;
        $DB->insert_record('files', $record);

        $filedata = $archive->getFromName("file/{$record->contenthash}");
        if ($filedata === false) {
            mtrace('M', '');
        } else {
            $filedirparent = $filedir
                    . '/' . substr($record->contenthash, 0, 2)
                    . '/' . substr($record->contenthash, 2, 2);
            $filedirpath = $filedirparent
                    . '/' . $record->contenthash;

            if (!is_dir($filedirparent)) {
                if (!mkdir($filedirparent, 0777, true)) {
                    mtrace('P', '');
                }
            }
            if (file_put_contents($filedirpath, $filedata) === false) {
                mtrace('E', '');
            }
        }
        mtrace(' ', '');
    }
    $csv->close();
    mtrace('');

    $transaction->allow_commit();
} catch (Exception $e) {
    $transaction->rollback($e);
}
