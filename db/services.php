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
 * Questionnaire external functions and service definitions.
 *
 * @package    mod
 * @subpackage questionnaire
 * @category   external
 * @copyright  2017 Mike Churchward <mike.churchward@poetgroup.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = [
    'mod_questionnaire_get_questionnaires_by_courses' => [
        'classname'     => 'mod_questionnaire_external',
        'methodname'    => 'get_questionnaires_by_courses',
        'description'   => 'Returns a list of questionnaires in a provided list of courses, if no list is provided all questionnaires that
                            the user can view will be returned.',
        'type'          => 'read',
        'capabilities'  => 'mod/questionnaire:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'mod_questionnaire_get_questionnaire_access_information' => [
        'classname'     => 'mod_questionnaire_external',
        'methodname'    => 'get_questionnaire_access_information',
        'description'   => 'Return access information for a given questionnaire.',
        'type'          => 'read',
        'capabilities'  => 'mod/questionnaire:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
        'mod_questionnaire_view_questionnaire' => [
        'classname'     => 'mod_questionnaire_external',
        'methodname'    => 'view_questionnaire',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/questionnaire:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];