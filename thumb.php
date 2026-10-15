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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Persists rendered PDF cover images, scoped to the activity and stored file.
 *
 * @package   mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$fileid = required_param('fileid', PARAM_INT);
require_sesskey();
$cm = get_coursemodule_from_id('superflipbook', $id, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/superflipbook:view', $context);
$storage = get_file_storage();
$pdf = $storage->get_file_by_id($fileid);
if (!$pdf || $pdf->get_contextid() != $context->id ||
        $pdf->get_component() !== 'mod_superflipbook' ||
        $pdf->get_filearea() !== 'content' || $pdf->get_itemid() != 0 ||
        $pdf->get_mimetype() !== 'application/pdf') {
    throw new invalid_parameter_exception('Invalid source PDF');
}
$filename = $fileid . '.jpg';
$thumb = $storage->get_file($context->id, 'mod_superflipbook', 'thumb', 0, '/', $filename);
if (!$thumb) {
    if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK ||
            $_FILES['image']['size'] > 350000 || $_FILES['image']['size'] < 1) {
        throw new invalid_parameter_exception('Invalid thumbnail file');
    }
    $image = file_get_contents($_FILES['image']['tmp_name']);
    $info = $image !== false ? getimagesizefromstring($image) : false;
    if (!$info || $info[2] !== IMAGETYPE_JPEG || $info[0] < 1 || $info[1] < 1 ||
            $info[0] > 500 || $info[1] > 700) {
        throw new invalid_parameter_exception('Thumbnail must be a small JPEG');
    }
    $thumb = $storage->create_file_from_string([
        'contextid' => $context->id,
        'component' => 'mod_superflipbook',
        'filearea' => 'thumb',
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $filename,
        'userid' => 0,
        'mimetype' => 'image/jpeg',
        'license' => 'allrightsreserved',
    ], $image);
}
$url = moodle_url::make_pluginfile_url($context->id, 'mod_superflipbook', 'thumb', 0, '/', $filename);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['url' => $url->out(false)]);
