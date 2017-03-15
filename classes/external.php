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
     * Describes the parameters for view_questionnaire.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.1
     */
    public static function view_questionnaire_parameters() {
        return new external_function_parameters (
            array(
                'questionnaireid' => new external_value(PARAM_INT, 'questionnaire instance id'),
            )
        );
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

        $params = self::validate_parameters(self::view_questionnaire_parameters(), array('questionnaireid' => $questionnaireid));
        $warnings = array();

        // Request and permission validation.
        $questionnaire = $DB->get_record('questionnaire', array('id' => $params['questionnaireid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($questionnaire, 'questionnaire');

        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/questionnaire:view', $context);
        $event = \mod_questionnaire\event\course_module_viewed::create(array(
            'objectid' => $questionnaire->id,
            'context' => $context,
        ));
        $event->add_record_snapshot('course', $course);
        $event->add_record_snapshot('questionnaire', $questionnaire);
        $event->trigger();

        $completion = new completion_info($course);
        $completion->set_module_viewed($cm);

        $result = array();
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
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }
}