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
 * @package mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author Mike Churchward & Joseph Rézeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

namespace mod_questionnaire;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

/**
 * Questionnaire external functions
 *
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class external extends \external_api {

    /**
     * Describes the parameters for get_questionnaires_by_courses.
     *
     * @return \external_function_parameters
     */
    public static function get_questionnaires_by_courses_parameters() {
        return new \external_function_parameters (
            ['courseids' => new \external_multiple_structure(
                new \external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, []),
            ]
        );
    }

    /**
     * Returns a list of questionnaires in a provided list of courses,
     * if no list is provided all questionnaires that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array the questionnaire details
     */
    public static function get_questionnaires_by_courses($courseids = array()) {
        global $CFG;
        $returnedquestionnaires = [];
        $warnings = [];
        $params = self::validate_parameters(self::get_questionnaires_by_courses_parameters(), ['courseids' => $courseids]);
        if (empty($params['courseids'])) {
            $params['courseids'] = array_keys(enrol_get_my_courses());
        }
        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {
            list($courses, $warnings) = \external_util::validate_courses($params['courseids']);
            // Get the questionnaires in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $questionnaires = get_all_instances_in_courses("questionnaire", $courses);
            foreach ($questionnaires as $questionnaire) {
                $context = context_module::instance($questionnaire->coursemodule);
                // Entry to return.
                $module = [];
                // First, we return information that any user can see in (or can deduce from) the web interface.
                $module['id'] = $questionnaire->id;
                $module['coursemodule'] = $questionnaire->coursemodule;
                $module['course'] = $questionnaire->course;
                $module['name']  = external_format_string($questionnaire->name, $context->id);
                $viewablefields = [];
                if (has_capability('mod/questionnaire:view', $context)) {
                    list($module['intro'], $module['introformat']) =
                        external_format_text($questionnaire->intro, $questionnaire->introformat, $context->id,
                                                'mod_questionnaire', 'intro', $questionnaire->id);
                }
                $returnedquestionnaires[] = $module;
            }
        }
        $result = [];
        $result['questionnaires'] = $returnedquestionnaires;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_questionnaires_by_courses return value.
     *
     * @return \external_single_structure
     */
    public static function get_questionnaires_by_courses_returns() {
        return new \external_single_structure(
            ['questionnaires' => new \external_multiple_structure(
                new \external_single_structure(
                    ['id' => new \external_value(PARAM_INT, 'questionnaire id'),
                     'coursemodule' => new \external_value(PARAM_INT, 'Course module id'),
                     'course' => new \external_value(PARAM_INT, 'Course id'),
                     'name' => new \external_value(PARAM_RAW, 'questionnaire name'),
                     'intro' => new \external_value(PARAM_RAW, 'The questionnaire intro', VALUE_OPTIONAL),
                     'introformat' => new \external_format_value('intro', VALUE_OPTIONAL),
                    ]
                )),
             'warnings' => new \external_warnings(),
            ]
        );
    }

    /**
     * Describes the parameters for get_user_responses.
     *
     * @return external_external_function_parameters
     */
    public static function get_user_responses_parameters() {
        return new \external_function_parameters (
            ['questionnaireid' => new \external_value(PARAM_INT, 'questionnaire instance id'),
             'userid' => new \external_value(PARAM_INT, 'user id, empty for current user', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * Return a list of responses for the given questionnaire and user.
     *
     * @param int $questionnaireid questionnaire instance id
     * @param int $userid user id
     * @return array of warnings and the list of responses
     * @throws invalid_parameter_exception
     */
    public static function get_user_responses($questionnaireid, $userid = 0) {
        global $DB, $USER;

        $warnings = [];

        $params = ['questionnaireid' => $questionnaireid, 'userid' => $userid];
        $params = self::validate_parameters(self::get_user_responses_parameters(), $params);

        $result = [];
        $result['responses'] = $DB->count_records('questionnaire_attempts', ['qid' => $questionnaireid, 'userid' => $userid]);
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_user_responses return value.
     *
     * @return external_single_structure
     */
    public static function get_user_responses_returns() {
        return new \external_single_structure(
            ['responses' => new \external_value(PARAM_INT, 'number of responses'),
             'warnings' => new \external_warnings(),
            ]
        );
    }
}