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

defined('MOODLE_INTERNAL') || die();

/**
 * @package    mod_questionnaire
 * @copyright  2016 Mike Churchward (mike.churchward@poetgroup.org)
 * @author     Mike Churchward
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = [
    'mod_questionnaire_get_questionnaires_by_courses' => [
        'classname'     => 'mod_questionnaire\external',
        'methodname'    => 'get_questionnaires_by_courses',
        'description'   => 'Returns a list of questionnaire instances in a provided set of courses, if
                            no courses are provided then all the questionnaire instances the user has access to will be returned.',
        'type'          => 'read',
        'capabilities'  => 'mod/questionnaire:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'],
    ],
    'mod_questionnaire_get_user_responses' => [
        'classname'     => 'mod_questionnaire\external',
        'methodname'    => 'get_user_responses',
        'description'   => 'Returns a count of the current user responses.',
        'type'          => 'read',
        'capabilities'  => 'mod/questionnaire:view',
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE, 'local_mobile'],
    ],
];