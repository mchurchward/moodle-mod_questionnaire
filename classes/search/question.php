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
 * Contains class mod_questionnaire\search\question
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\search;

defined('MOODLE_INTERNAL') || die();

/**
 * Search area for mod_questionnaire questions.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question extends \core_search\base_mod {

    /**
     * Returns recordset containing required data for indexing activities.
     *
     * @param int $modifiedfrom timestamp
     * @return \moodle_recordset
     */
    public function get_recordset_by_timestamp($modifiedfrom = 0) {
        global $DB;

        $sql = 'SELECT qq.id, q.id AS questionnaireid, q.timemodified, q.course AS courseid, q.introformat, '.
            'qq.name, qq.content ' .
            'FROM {questionnaire} q ' .
            'INNER JOIN {questionnaire_question} qq ON q.sid = qq.survey_id AND qq.deleted = \'n\' ' .
            'WHERE q.timemodified >= ? ' .
            'ORDER BY q.timemodified ASC';

        return $DB->get_recordset_sql($sql, [$modifiedfrom]);
    }

    /**
     * Returns the document for a particular question.
     *
     * @param \stdClass $record
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($record, $options = []) {
        try {
            $cm = $this->get_cm('questionnaire', $record->questionnaireid, $record->courseid);
            $context = \context_module::instance($cm->id);
        } catch (\dml_missing_record_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($record->name, false));
        $doc->set('content', content_to_text($record->content, $record->introformat));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $record->courseid);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);

        return $doc;
    }

    /**
     * Can the current user see the document.
     *
     * @param int $id The internal search area entity id.
     * @return bool True if the user can see it, false otherwise
     */
    public function check_access($id) {
        global $DB;

        try {
            // Questions are in surveys and surveys can be used in multiple questionnaires.
            $question = $DB->get_record('questionnaire_question', ['id' => $id], '*', MUST_EXIST);
            $questionnaires = $DB->get_records('questionnaire', ['sid' => $question->survey_id]);
            if (empty($questionnaires)) {
                return \core_search\manager::ACCESS_DELETED;
            }
            $cmsinfo = [];
            foreach ($questionnaires as $questionnaire) {
                $cmsinfo[] = $this->get_cm('questionnaire', $questionnaire->id, $questionnaire->course);
            }
        } catch (\dml_missing_record_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        } catch (\dml_exception $ex) {
            return \core_search\manager::ACCESS_DENIED;
        }

        // Recheck uservisible although it should have already been checked in core_search.
        // Use the first one that is visible. Otherwise exit.
        $cmvisible = false;
        foreach ($cmsinfo as $cminfo) {
            if ($cminfo->uservisible !== false) {
                $cmvisible = $cminfo;
                break;
            }
        }
        if ($cmvisible === false) {
            return \core_search\manager::ACCESS_DENIED;
        }

        $context = \context_module::instance($cminfo->id);

        if (!has_capability('mod/questionnaire:view', $context)) {
            return \core_search\manager::ACCESS_DENIED;
        }

        return \core_search\manager::ACCESS_GRANTED;
    }

    /**
     * Link to the module instance.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {;
        return $this->get_context_url($doc);
    }

    /**
     * Link to the module instance.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        $context = \context::instance_by_id($doc->get('contextid'));
        return new \moodle_url('/mod/questionnaire/view.php', ['id' => $context->instanceid]);
    }
}
