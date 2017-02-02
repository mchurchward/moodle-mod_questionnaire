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
 * Contains class mod_questionnaire\search\activity
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_questionnaire\search;

defined('MOODLE_INTERNAL') || die();

/**
 * Search area for mod_questionnaire activities.
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity extends \core_search\base_activity {

    /**
     * Returns recordset containing required data for indexing activities.
     *
     * @param int $modifiedfrom timestamp
     * @return \moodle_recordset
     */
    public function get_recordset_by_timestamp($modifiedfrom = 0) {
        global $DB;

        $sql = 'SELECT q.*, s.subtitle, s.info ' .
            'FROM {questionnaire} q ' .
            'INNER JOIN {questionnaire_survey} s ON q.sid = s.id ' .
            'WHERE q.timemodified >= ? ' .
            'ORDER BY q.timemodified ASC';

        return $DB->get_recordset_sql($sql, [$modifiedfrom]);
    }

    /**
     * Returns the document associated with this activity.
     *
     * The default implementation for activities sets the activity name to title and the activity intro to
     * content. This override takes that data and adds new fields to it for indexing.
     *
     * @param stdClass $record
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($record, $options = []) {
        global $DB;

        // Get the default implementation.
        $doc = parent::get_document($record, $options);

        // Add the subtitle and additional info fields.
        $doc->set('description1', content_to_text($record->subtitle . "\n" . $record->info, $record->introformat));

        // Because there is no database agnostic way to combine all of the possible question content data into one record in
        // get_recordset_by_timestamp, I need to grab it all now and add it to the document.
        $recordset = $DB->get_recordset('questionnaire_question', ['survey_id' => $record->sid, 'deleted' => 'n'],
            'id', 'id,content');
        $qcontent = '';
        foreach ($recordset as $question) {
            $qcontent .= $question->content . "\n";
        }
        $recordset->close();
        $doc->set('description2', content_to_text($qcontent, $record->introformat));

        return $doc;
    }
}
