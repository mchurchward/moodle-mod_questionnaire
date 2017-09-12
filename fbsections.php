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

require_once("../../config.php");
require_once($CFG->dirroot.'/mod/questionnaire/questionnaire.class.php');

$id     = optional_param('id', 0, PARAM_INT);
$sid    = optional_param('sid', 0, PARAM_INT);

if ($id) {
    if (! $cm = get_coursemodule_from_id('questionnaire', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
        print_error('coursemisconf');
    }
    if (! $questionnaire = $DB->get_record("questionnaire", array("id" => $cm->instance))) {
        print_error('invalidcoursemodule');
    }
}

// Check login and get context.
require_login($course->id, false, $cm);
$context = $cm ? context_module::instance($cm->id) : false;

$url = new moodle_url('/mod/questionnaire/fbsections.php');
if ($id !== 0) {
    $url->param('id', $id);
}
if ($sid) {
    $url->param('sid', $sid);
}
$questionnaire = new questionnaire(0, $questionnaire, $course, $cm);
$questions = $questionnaire->questions;
$sid = $questionnaire->survey->id;
$viewform = data_submitted($CFG->wwwroot."/mod/questionnaire/fbsections.php");
$feedbacksections = $questionnaire->survey->feedbacksections;
$errormsg = '';

// False -> original behavior, nothing changed
// True  -> allow question to be in multiple sections,
//       -> Checkboxes instead of RadioButtons
//       -> Input for weights
$advdependencies = False;
if ($questionnaire->navigate == 2) {
    $advdependencies = True;
}
// [qid][section] = weight for question (qid) in section
$scorecalculation_weights = array();

// Check if there are any feedbacks stored in database already to use them to check
// the radio buttons on select questions in sections page.
if ($fbsections = $DB->get_records('questionnaire_fb_sections',
    array('survey_id' => $sid)) ) {
    $scorecalculation = '';
    $questionsinsections = array();
    for ($section = 1; $section <= $feedbacksections; $section++) {
        // Retrieve the scorecalculation formula and the section heading only once.
        foreach ($fbsections as $fbsection) {
            if (isset($fbsection->scorecalculation) && $fbsection->section == $section) {
                $scorecalculation = unserialize($fbsection->scorecalculation);
                foreach ($scorecalculation as $qid => $key) {
                    if ($advdependencies) {
                        if (!is_array($questionsinsections[$qid])) {
                            $questionsinsections[$qid] = array();
                            $scorecalculation_weights[$qid] = array();
                        }
                        array_push($questionsinsections[$qid], $section);
                        // $key != null -> 0.0 - 1.0
                        $scorecalculation_weights[$qid][$section] = $key;
                    } else{
                        $questionsinsections[$qid] = $section;
                    }
                }
                break;
            }
        }
    }
    // If Global Feedback (only 1 section) and no questions have yet been put in section 1 check all questions.
    if (!empty($questionsinsections)) {
        $vf = $questionsinsections;
    }
}
if (data_submitted()) {
    $vf = (array)$viewform;
    if (isset($vf['savesettings'])) {
        $action = 'savesettings';
        unset($vf['savesettings']);
    }
    $scorecalculation = array();
    $submittedvf = array();
    $scorecalculation_weights = array();
    foreach($vf as $key => $value){
        $qid_section = explode("|", $key);
        if ($qid_section[0] !== "weight"){
            continue;
        }
        if (!is_array($scorecalculation_weights[$qid_section[0]])){
            $scorecalculation_weights[$qid_section[0]] = array();
        }
        // $qid_section[1] = qid;  $qid_section[2] = section
        $scorecalculation_weights[$qid_section[1]][$qid_section[2]] = $value;
    }
    foreach ($vf as $qs) {
        $sectionqid = explode("_", $qs);
        if ($sectionqid[0] != 0) {
            if ($advdependencies){
                if (isset($sectionqid[1])) {
                    // $scorecalculation[$sectionqid[0]][$sectionqid[1]] != null
                    $scorecalculation[$sectionqid[0]][$sectionqid[1]] = $scorecalculation_weights[$sectionqid[1]][$sectionqid[0]];
                }
                if(count($sectionqid) == 2) {
                    // [1] - id; [0] - section
                    $submittedvf[$sectionqid[1]] = $sectionqid[0];
                }

            } else {
                $scorecalculation[$sectionqid[0]][$sectionqid[1]] = null;
                $submittedvf[$sectionqid[1]] = $sectionqid[0];
            }
        }
    }
    $c = count($scorecalculation);
    if ($c < $feedbacksections) {
        $sectionsnotset = '';
        for ($section = 1; $section <= $feedbacksections; $section++) {
            if (!isset($scorecalculation[$section])) {
                $sectionsnotset .= $section.'&nbsp;';
            }
        }
        $errormsg = get_string('sectionsnotset', 'questionnaire', $sectionsnotset);
        $vf = $submittedvf;
    } else {
        for ($section = 1; $section <= $feedbacksections; $section++) {
            $fbcalculation[$section] = serialize($scorecalculation[$section]);
        }

        $sections = $DB->get_records('questionnaire_fb_sections',
            array('survey_id' => $questionnaire->survey->id), 'section DESC');
        // Delete former feedbacks if number of feedbacksections has been reduced.
        foreach ($sections as $section) {
            if ($section->section > $feedbacksections) {
                // Delete section record.
                $DB->delete_records('questionnaire_fb_sections', array('survey_id' => $sid, 'section' => $section->section));
                // Delete associated feedback records.
                $DB->delete_records('questionnaire_feedback', array('section_id' => $section->section));
            }
        }

        // Check if the number of feedback sections has been increased and insert new ones
        // must also insert section heading!
        for ($section = 1; $section <= $feedbacksections; $section++) {
            if ($existsection = $DB->get_record('questionnaire_fb_sections',
                array('survey_id' => $sid, 'section' => $section), '*', IGNORE_MULTIPLE) ) {
                $DB->set_field('questionnaire_fb_sections', 'scorecalculation', serialize($scorecalculation[$section]),
                    array('survey_id' => $sid, 'section' => $section));
            } else {
                $feedbacksection = new stdClass();
                $feedbacksection->survey_id = $sid;
                $feedbacksection->section = $section;
                $feedbacksection->scorecalculation = serialize($scorecalculation[$section]);
                $feedbacksection->id = $DB->insert_record('questionnaire_fb_sections', $feedbacksection);
            }
        }

        $currentsection = 1;
        $SESSION->questionnaire->currentfbsection = 1;
        redirect ($CFG->wwwroot.'/mod/questionnaire/fbsettings.php?id='.
            $questionnaire->cm->id.'&currentsection='.$currentsection, '', 0);
    }
}

$PAGE->set_url($url);
// Print the page header.
$PAGE->set_title(get_string('feedbackeditingsections', 'questionnaire'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('feedbackeditingsections', 'questionnaire'));

// Add renderer and page objects to the questionnaire object for display use.
$questionnaire->add_renderer($PAGE->get_renderer('mod_questionnaire'));
$questionnaire->add_page(new \mod_questionnaire\output\fbsectionspage());

$feedbacksections = $questionnaire->survey->feedbacksections + 1;

if ($errormsg != '') {
    $questionnaire->page->add_to_page('notifications', $questionnaire->renderer->notification($errormsg));
}
$n = 0;
// number of sectiontext questions
$fb = 0;
$bg = 'c0';

$questionnaire->page->add_to_page('formarea', $questionnaire->renderer->box_start());

$questionnaire->page->add_to_page('formarea', $questionnaire->renderer->help_icon('feedbacksectionsselect', 'questionnaire'));
$questionnaire->page->add_to_page('formarea', '<b>Sections:</b><br /><br />');
$formdata = new stdClass();
$descendantsdata = array();

foreach ($questionnaire->questions as $question) {
    $qtype = $question->type_id;
    $qname = $question->name;
    $qprecise = $question->precise;
    $required = $question->required;
    $qid = $question->id;

    // Questions to be included in feedback sections must be required, have a name
    // and must not be child of a parent question.
    // radio buttons need different names
    if ($qtype != QUESPAGEBREAK ){//&& $qtype != QUESSECTIONTEXT ) {
        $n++;
    }

    $cannotuse = false;
    $strcannotuse = '';
    if ($qtype != QUESSECTIONTEXT && $qtype != QUESPAGEBREAK
        && ($qtype != QUESYESNO && $qtype != QUESRADIO && $qtype != QUESRATE
            || $required != 'y' || $qname == '' || $question->dependquestion != 0)) {
        $cannotuse = true;
        $qn = '<strong>'.$n.'</strong>';
        if ($qname == '') {
            $strcannotuse = get_string('missingname', 'questionnaire', $qn);
        }
        if ($required != 'y') {
            if ($qname == '') {
                $strcannotuse = get_string('missingnameandrequired', 'questionnaire', $qn);
            } else {
                $strcannotuse = get_string('missingrequired', 'questionnaire', $qn);
            }
        }
        if ($question->dependquestion != 0) {
            continue;
        }
    }

    // QUESSECTIONTEXT (Label), cannotuse == true -> label in feedback sections
    if ($qtype == QUESSECTIONTEXT){
        $cannotuse = false;
        $fb++;
    }

    $qhasvalues = false;
    if (!$cannotuse) {
        if ($qtype == QUESRADIO || $qtype == QUESDROP) {
            if ($choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid = $question->id))) {
                foreach ($choices as $choice) {
                    if ($choice->value != null) {
                        $qhasvalues = true;
                        break;
                    }
                }
            }
        }

        // Valid questions in feedback sections can be of QUESNO type
        // or of QUESRATE "normal" option type (i.e. not N/A nor nodupes).
        if ($qtype == QUESYESNO || ($qtype == QUESRATE && ($qprecise == 0 || $qprecise == 3)) ) {
            $qhasvalues = true;
        }

        // QUESSECTIONTEXT (Label), show radio buttons -> select section for feedback ($filteredSections)
        if ($qtype == QUESSECTIONTEXT){
            $qhasvalues = true;
        }

        if ($qhasvalues) {
            $emptyisglobalfeedback = $questionnaire->survey->feedbacksections == 1 && empty($questionsinsections);
            $questionnaire->page->add_to_page('formarea', '<div style="margin-bottom:5px;">['.$qname.']</div>');
            for ($i = 0; $i < $feedbacksections; $i++) {
                $output = '<div style="float:left; padding-right:5px;">';
                if ($i != 0) {
                    if ($advdependencies) {
                        // RadioButton -> Checkbox
                        // onclick: Section > 0 selected? -> uncheck section 0
                        $output .= '<div class="' . $bg . '"><input type="checkbox" style="width: 60px;" name="' . $n . '_' . $i . '"' .
                            ' id="' . $qid . '_' . $i . '" value="' . $i . '_' . $qid . '" ' .
                            'onclick="document.getElementsByName(\''.$n.'_0\')[0].checked=false;"';
                    } else{
                        $output .= '<div class="' . $bg . '"><input type="radio" name="' . $n . '" id="' .
                            $qid . '_' . $i . '" value="' . $i . '_' . $qid . '"';
                    }
                } else {
                    if ($advdependencies) {
                        // section 0
                        // onclick: uncheck_boxes see below
                        $output .= '<div class="' . $bg . '">' .
                            '<input type="checkbox" style="width: 60px;" onclick="uncheck_boxes(\''.$n.'\');" name="' . $n . '_' . $i . '"' .
                            ' id="' . $i . '" value="' . $i . '"';
                    } else{
                        $output .= '<div class="' . $bg . '"><input type="radio" name="' . $n . '" id="' . $i . '" value="' . $i . '"';
                    }
                }

                if ($advdependencies){
                    if ($i == 0 && !isset($vf[$qid])) {
                        $output .= ' checked="checked"';
                    }
                    // Question already present in this section OR this is a Global feedback and questions are not set yet.
                    if ($emptyisglobalfeedback){
                        $output .= ' checked="checked"';
                    } else{
                        // check not only one checkbox per question
                        if (isset($vf[$qid])){
                            foreach ($vf[$qid] as $key => $value){
                                if ($i == $value){
                                    $output .= ' checked="checked"';
                                }
                            }
                        }
                    }
                    $output .= ' />';
                    // without last </div>, add inputfield for question in section
                    $output .= '<label for="' . $qid . '_' . $i . '">' . '<div style="padding-left: 2px;">' . $i . '</div>' . '</label></div>';
                    // $qtype != QUESSECTIONTEXT (Label) with feedback while anserwing the survey, 
                    // needs only the section number(s) without weights (section == 0 -> normal behavior as Label question)
                    if ($i > 0 && $qtype != QUESSECTIONTEXT) {
                        // add Input fields for weights per section
                        if ($scorecalculation_weights[$qid][$i]){
                            $output .= '<input type="number" style="width: 80px;" name="weight|' . $qid . '|' . $i . '" min="0.0" max="1.0" step="0.01" value="'. $scorecalculation_weights[$qid][$i] .'">';
                        } else{
                            $output .= '<input type="number" style="width: 80px;" name="weight|' . $qid . '|' . $i . '" min="0.0" max="1.0" step="0.01" value="0">';
                        }
                    }
                    // now close div-Tag
                    $output .= '</div>';

                } else { // $advdependencies == false
                    if ($i == 0) {
                        $output .= ' checked="checked"';
                    }
                    // Question already present in this section OR this is a Global feedback and questions are not set yet.
                    if ((isset($vf[$qid]) && $vf[$qid] == $i) || $emptyisglobalfeedback) {
                        $output .= ' checked="checked"';
                    }
                    $output .= ' />';
                    $output .= '<label for="' . $qid . '_' . $i . '">' . '<div style="padding-left: 2px;">' . $i . '</div>' . '</label></div></div>';
                }
                $questionnaire->page->add_to_page('formarea', $output);
                if ($bg == 'c0') {
                    $bg = 'c1';
                } else {
                    $bg = 'c0';
                }
            }
        }
        if ($qhasvalues || $qtype == QUESSECTIONTEXT) {
            // $n-$fb display sectiontext without a number and do not count them
            $questionnaire->page->add_to_page('formarea',
                $questionnaire->renderer->question_output($question, $formdata, '', $n-$fb, true));
        }
    } else {
        $questionnaire->page->add_to_page('formarea', '<div class="notifyproblem">');
        $questionnaire->page->add_to_page('formarea', $strcannotuse);
        $questionnaire->page->add_to_page('formarea', '</div>');
        $questionnaire->page->add_to_page('formarea', '<div class="qn-question">'.$question->content.'</div>');
    }
}
if ($advdependencies){
    // customized checkbox behavior
    // section 0 selected? -> uncheck all other
    $str_func = "\n<script>\n";
    $str_func .= ' function uncheck_boxes(name){
            var boxes = document.querySelectorAll("[name^=\'"+name+"_\']"); 
            for(var i=0;i<boxes.length; i++){
                if(boxes[i].name != name+"_0"){
                    boxes[i].checked=false;
                }
            } 
         }';
    //var boxes = document.querySelectorAll("[name^="+ name +"_"]); console.log(boxes);}';
    $str_func .= "\n</script>\n";
    $questionnaire->page->add_to_page('formarea', $str_func);
}
// Submit/Cancel buttons.
$url = $CFG->wwwroot.'/mod/questionnaire/view.php?id='.$cm->id;
$questionnaire->page->add_to_page('formarea', '<div><input type="submit" name="savesettings" value="'.
    get_string('feedbackeditmessages', 'questionnaire').'" /><a href="'.$url.'">'.get_string('cancel').'</a></div>');
$questionnaire->page->add_to_page('formarea', $questionnaire->renderer->box_end());
echo $questionnaire->renderer->header();
echo $questionnaire->renderer->render($questionnaire->page);
echo $questionnaire->renderer->footer($course);
