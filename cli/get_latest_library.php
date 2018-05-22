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

use tool_hvpdiag\hub_client;

define('CLI_SCRIPT', true);
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/config.php';
require_once "{$CFG->libdir}/clilib.php";

list($params, $unrecognised) = cli_get_params([
    'librarymachinename' => null,
    'uuid' => get_config('mod_hvp', 'site_uuid'),
], [
    'l' => 'librarymachinename',
    'u' => 'uuid',
]);

if ($params['librarymachinename'] === null) {
    mtrace('gimme an -l (--librarymachinename)');
    exit(1);
}
if ($params['uuid'] === null) {
    mtrace('gimme a -u (--uuid)');
    exit(1);
}

$hub = new hub_client($params['uuid']);
$hub->get_library_h5p($params['librarymachinename']);
