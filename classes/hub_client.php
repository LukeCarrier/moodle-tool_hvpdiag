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

namespace tool_hvpdiag;

use coding_exception;
use curl;

defined('MOODLE_INTERNAL') || die;
require_once "{$CFG->libdir}/filelib.php";

class hub_client {
    const BASE_URL = 'https://api.h5p.org';
    const SERVICE_CONTENT_TYPES = '/v1/content-types/';

    const REGEX_RESPONSE_FILENAME = '/attachment;\s*filename\s*=\s*("([^"]+)"|([^$]+)$|([^;]+);)/';

    protected $curl;
    protected $uuid;

    const INFO_DEFAULT = '¯\_(ツ)_/¯';

    public function __construct($uuid, curl $curl=null) {
        $this->uuid = $uuid;
        $this->curl = $curl ?? new curl();
    }

    protected function make_default_params() {
        return [
            'uuid' => $this->uuid,
        ];
    }

    public function get_libraries() {
        $url = static::BASE_URL . static::SERVICE_CONTENT_TYPES;
        $response = $this->curl->post($url, $this->make_default_params());
    }

    public function get_library_h5p($machinename, $targetfilename=null) {
        $url = static::BASE_URL . static::SERVICE_CONTENT_TYPES . $machinename;
        $body = $this->curl->post($url, $this->make_default_params());

        $this->require_success();
        $this->require_content_type('application/zip');

        $filebasename = $this->get_response_filename();
        if ($targetfilename === null) {
            $targetfilename = $_SERVER['PWD'] . "/{$filebasename}";
        } elseif (is_dir($targetfilename)) {
            $targetfilename .= "/{$filebasename}";
        }

        file_put_contents($targetfilename, $body);
    }

    protected function get_response_header($header) {
        foreach ($this->curl->response as $name => $value) {
            if (strtolower($name) === $header) {
                return $value;
            }
        }
        throw new coding_exception(sprintf(
                'no %s header in response', $header));
    }

    protected function require_content_type($contenttype) {
        $this->require_info('content_type', $contenttype);
    }

    protected function require_success() {
        $this->require_info('http_code', 200);
    }
    
    protected function require_info($field, $value) {
        $info = $this->curl->get_info();
        $value = array_key_exists($field, $info)
            ? $info[$field] : static::INFO_DEFAULT;

        if ($value !== $value) {
            throw new coding_exception(sprintf(
                    'unexpected %s value %s', $field, $value));
        }
    }

    protected function get_response_filename() {
        $contentdisposition = $this->get_response_header('content-disposition');
        $matches = null;
        preg_match(static::REGEX_RESPONSE_FILENAME, $contentdisposition,$matches);
        if (count($matches) < 2) {
            throw new coding_exception('content-disposition header didn\'t contain a filename');
        }

        unset($matches[0]);
        foreach ($matches as $match) {
            if ($match !== '') {
                return $match;
            }
        }

        throw new coding_exception('filename in content-disposition header was manky');
    }
}
