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

$function = array(
    'mod_questionnaire_view_questionnaire' => array(
        'classname'     => 'mod_questionnaire_external',
        'methodname'    => 'view_questionnaire',
        'description'   => 'Trigger the course module viewed event and update the module completion status.',
        'type'          => 'write',
        'capabilities'  => 'mod/questionnaire:view',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);