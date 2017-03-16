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
 * Certificate module external API
 *
 * @package    mod
 * @subpackage questionnaire
 * @category   external
 * @copyright  2017 Mike Churchward <mike.churchward@poetgroup.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');

/**
 * Questionnaire module external functions
 *
 * @package    mod
 * @subpackage questionnaire
 * @category   external
 * @copyright  2017 Mike Churchward <mike.churchward@poetgroup.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_questionnaire_external extends \external_api {

    /**
     * Describes the parameters for get_questionnaires_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_questionnaires_by_courses_parameters() {
        return new external_function_parameters (
            ['courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course id'), 'Array of course ids', VALUE_DEFAULT, array()),
            ]
        );
    }
    /**
     * Returns a list of questionnaires in a provided list of courses.
     * If no list is provided all questionnaires that the user can view will be returned.
     *
     * @param array $courseids course ids
     * @return array of warnings and questionnaires
     * @since Moodle 3.3
     */
    public static function get_questionnaires_by_courses($courseids = []) {
        global $CFG;

        $returnedquestionnaires = [];
        $warnings = [];

        $params = self::validate_parameters(self::get_questionnaires_by_courses_parameters(), ['courseids' => $courseids]);

        if (empty($params['courseids'])) {
            $params['courseids'] = array_keys(enrol_get_my_courses());
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {
            list($courses, $warnings) = external_util::validate_courses($params['courseids']);

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

                // Check additional permissions for returning optional private settings.
                if (has_capability('moodle/course:manageactivities', $context)) {
                    $additionalfields = ['timecreated', 'timemodified', 'section', 'visible', 'groupmode', 'groupingid'];
                    $viewablefields = array_merge($viewablefields, $additionalfields);
                }

                foreach ($viewablefields as $field) {
                    $module[$field] = $questionnaire->{$field};
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
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_questionnaires_by_courses_returns() {
        return new external_single_structure(
            ['questionnaires' => new external_multiple_structure(
                new external_single_structure(
                    ['id' => new external_value(PARAM_INT, 'Questionnaire id'),
                     'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                     'course' => new external_value(PARAM_INT, 'Course id'),
                     'name' => new external_value(PARAM_RAW, 'Questionnaire name'),
                     'intro' => new external_value(PARAM_RAW, 'The Questionnaire intro', VALUE_OPTIONAL),
                     'introformat' => new external_format_value('intro', VALUE_OPTIONAL),
                     'timecreated' => new external_value(PARAM_INT, 'Time created', VALUE_OPTIONAL),
                     'timemodified' => new external_value(PARAM_INT, 'Time modified', VALUE_OPTIONAL),
                     'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                     'visible' => new external_value(PARAM_INT, 'visible', VALUE_OPTIONAL),
                     'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                     'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                    ],
                    'Tool'
                )
             ),
             'warnings' => new external_warnings(),
            ]
        );
    }

    /**
     * Describes the parameters for get_questionnaire_access_information.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_questionnaire_access_information_parameters() {
        return new external_function_parameters (
            ['questionnaireid' => new external_value(PARAM_INT, 'Questionnaire instance id.')]
        );
    }
    /**
     * Return access information for a given questionnaire.
     *
     * @param int $questionnaireid questionnaire instance id
     * @return array of warnings and the access information
     * @since Moodle 3.3
     * @throws  moodle_exception
     */
    public static function get_questionnaire_access_information($questionnaireid) {
        global $PAGE;
        $params = ['questionnaireid' => $questionnaireid];
        $params = self::validate_parameters(self::get_questionnaire_access_information_parameters(), $params);
        list($questionnaire, $course, $cm, $context) = self::validate_questionnaire($params['questionnaireid']);
        $result = [];
        // Capabilities first.
        $result['cancomplete'] = true;
        $result['warnings'] = [];
        return $result;
    }
    /**
     * Describes the get_questionnaire_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_questionnaire_access_information_returns() {
        return new external_single_structure(
            ['cancomplete' => new external_value(PARAM_BOOL, 'Whether the user can complete the questionnaire or not.'),
             'warnings' => new external_warnings(),
            ]
        );
    }

    /**
     * Describes the parameters for view_questionnaire.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function view_questionnaire_parameters() {
        return new external_function_parameters (['questionnaireid' => new external_value(PARAM_INT, 'questionnaire instance id')]);
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $questionnaireid questionnaire instance id
     * @return array of warnings and status result
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function view_questionnaire($questionnaireid) {
        global $DB;

        $params = self::validate_parameters(self::view_questionnaire_parameters(), ['questionnaireid' => $questionnaireid]);
        $warnings = [];

        // Request and permission validation.
        $questionnaire = $DB->get_record('questionnaire', ['id' => $params['questionnaireid']], '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($questionnaire, 'questionnaire');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/questionnaire:view', $context);
        $event = \mod_questionnaire\event\course_module_viewed::create([
            'objectid' => $questionnaire->id,
            'context' => $context,
        ]);
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('questionnaire', $questionnaire);
        $event->trigger();

        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $result = [];
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_questionnaire return value.
     *
     * @return external_single_structure
     * @since Moodle 3.1
     */
    public static function view_questionnaire_returns() {
        return new external_single_structure(
            ['status' => new external_value(PARAM_BOOL, 'status: true if success'),
             'warnings' => new external_warnings(),
            ]
        );
    }
}