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
 * Activity configuration and completion rules.
 *
 * @package mod_superflipbook
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');

/** Module form. */
class mod_superflipbook_mod_form extends moodleform_mod {
    /** Build the form. */
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();
        $mform->addElement('filemanager', 'pdf', get_string('pdfcontent', 'mod_superflipbook'), null,
            ['maxfiles' => 1, 'accepted_types' => ['.pdf'], 'subdirs' => false]);
        $mform->addRule('pdf', null, 'required');
        $mform->addElement('header', 'readingrules', get_string('readingrules', 'mod_superflipbook'));
        $mform->addElement('text', 'minpercentage', get_string('minpercentage', 'mod_superflipbook'));
        $mform->setType('minpercentage', PARAM_INT);
        $mform->setDefault('minpercentage', 100);
        $mform->addElement('text', 'minseconds', get_string('minseconds', 'mod_superflipbook'));
        $mform->setType('minseconds', PARAM_INT);
        $mform->setDefault('minseconds', 0);
        $mform->addElement('advcheckbox', 'requirelastpage', get_string('requirelastpage', 'mod_superflipbook'));
        $mform->addElement('text', 'requiredpages', get_string('requiredpages', 'mod_superflipbook'), ['size' => 40]);
        $mform->setType('requiredpages', PARAM_TEXT);
        $mform->addHelpButton('requiredpages', 'requiredpages', 'mod_superflipbook');
        $mform->addElement('advcheckbox', 'requireack', get_string('requireack', 'mod_superflipbook'));
        $mform->addElement('textarea', 'ackstatement', get_string('ackstatement', 'mod_superflipbook'),
            ['rows' => 3, 'cols' => 60]);
        $mform->setType('ackstatement', PARAM_TEXT);
        $mform->hideIf('ackstatement', 'requireack', 'notchecked');
        $mform->addElement('header', 'privacysection', get_string('privacysection', 'mod_superflipbook'));
        foreach (['allowdownload', 'allowprint', 'watermark', 'recordip'] as $field) {
            $mform->addElement('advcheckbox', $field, get_string($field, 'mod_superflipbook'));
        }
        $mform->setDefault('watermark', 1);
        $mform->addElement('text', 'grade', get_string('grade'), ['size' => 8]);
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 100);
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /** Register a custom completion condition for Moodle completion UI. */
    public function add_completion_rules() {
        $mform = $this->_form;
        $mform->addElement('advcheckbox', 'completionreading' . $this->get_suffix(),
            get_string('completionreading', 'mod_superflipbook'));
        $mform->setDefault('completionreading' . $this->get_suffix(), 1);
        return ['completionreading' . $this->get_suffix()];
    }

    /** Whether the reading condition is enabled for this activity. */
    public function completion_rule_enabled($data) {
        return !empty($data['completionreading' . $this->get_suffix()]);
    }

    /** Descriptions of active rules. */
    public function get_completion_rule_descriptions() {
        return ['completionreading' => get_string('completionreading', 'mod_superflipbook')];
    }

    /** File draft preprocessing. */
    public function data_preprocessing(&$defaultvalues) {
        if (!empty($this->current->instance)) {
            $draft = file_get_submitted_draft_itemid('pdf');
            file_prepare_draft_area($draft, $this->context->id, 'mod_superflipbook', 'content', 0,
                ['maxfiles' => 1, 'accepted_types' => ['.pdf']]);
            $defaultvalues['pdf'] = $draft;
        }
    }

    /** Validate custom controls. */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if ((int)$data['minpercentage'] < 0 || (int)$data['minpercentage'] > 100) {
            $errors['minpercentage'] = get_string('percentageerror', 'mod_superflipbook');
        }
        if ((int)$data['minseconds'] < 0) {
            $errors['minseconds'] = get_string('positiveerror', 'mod_superflipbook');
        }
        if ((int)$data['grade'] < 0 || (int)$data['grade'] > 100) {
            $errors['grade'] = get_string('percentageerror', 'mod_superflipbook');
        }
        if (!empty($data['requiredpages']) && !preg_match('/^\d+(\s*,\s*\d+)*$/', $data['requiredpages'])) {
            $errors['requiredpages'] = get_string('requiredpageserror', 'mod_superflipbook');
        }
        if (!empty($data['requireack']) && trim($data['ackstatement']) === '') {
            $errors['ackstatement'] = get_string('required');
        }
        return $errors;
    }
}
