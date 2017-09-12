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
require_once($CFG->dirroot.'/mod/questionnaire/classes/question/base.php'); // Needed for question type constants.

$id     = required_param('id', PARAM_INT);                 // Course module ID
$action = optional_param('action', 'main', PARAM_ALPHA);   // Screen.
$qid    = optional_param('qid', 0, PARAM_INT);             // Question id.
$moveq  = optional_param('moveq', 0, PARAM_INT);           // Question id to move.
$delq   = optional_param('delq', 0, PARAM_INT);             // Question id to delete
$qtype  = optional_param('type_id', 0, PARAM_INT);         // Question type.
$currentgroupid = optional_param('group', 0, PARAM_INT); // Group id.

if (! $cm = get_coursemodule_from_id('questionnaire', $id)) {
    print_error('invalidcoursemodule');
}

if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
    print_error('coursemisconf');
}

if (! $questionnaire = $DB->get_record("questionnaire", array("id" => $cm->instance))) {
    print_error('invalidcoursemodule');
}

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);

$url = new moodle_url($CFG->wwwroot.'/mod/questionnaire/questions.php');
$url->param('id', $id);
if ($qid) {
    $url->param('qid', $qid);
}

$PAGE->set_url($url);
$PAGE->set_context($context);

$questionnaire = new questionnaire(0, $questionnaire, $course, $cm);

// Add renderer and page objects to the questionnaire object for display use.
$questionnaire->add_renderer($PAGE->get_renderer('mod_questionnaire'));
$questionnaire->add_page(new \mod_questionnaire\output\questionspage());

if (!$questionnaire->capabilities->editquestions) {
    print_error('nopermissions', 'error', 'mod:questionnaire:edit');
}

$questionnairehasdependencies = questionnaire_has_dependencies($questionnaire->questions);
$haschildren = array();
if (!isset($SESSION->questionnaire)) {
    $SESSION->questionnaire = new stdClass();
}
$SESSION->questionnaire->current_tab = 'questions';
$reload = false;
$sid = $questionnaire->survey->id;
// Process form data.

// Delete question button has been pressed in questions_form AND deletion has been confirmed on the confirmation page.
if ($delq) {
    $qid = $delq;
    $sid = $questionnaire->survey->id;
    $questionnaireid = $questionnaire->id;

    // Does the question to be deleted have any child questions?
    if ($questionnairehasdependencies) {
    	if ($questionnaire->navigate != 2) {
    		$haschildren  = questionnaire_get_descendants ($questionnaire->questions, $qid);
    	} else {
    		//TODO refactor into questionnaire_get_descendants in locallib?
    		$haschildren = questionnaire_get_advdescendants ($questionnaire->questions, $qid);
    	}
    }

    // Need to reload questions before setting deleted question to 'y'.
    $questions = $DB->get_records('questionnaire_question', array('survey_id' => $sid, 'deleted' => 'n'), 'id');
    $DB->set_field('questionnaire_question', 'deleted', 'y', array('id' => $qid, 'survey_id' => $sid));

    // Just in case the page is refreshed (F5) after a question has been deleted.
    if (isset($questions[$qid])) {
        $select = 'survey_id = '.$sid.' AND deleted = \'n\' AND position > '.
                        $questions[$qid]->position;
    } else {
        redirect($CFG->wwwroot.'/mod/questionnaire/questions.php?id='.$questionnaire->cm->id);
    }

    if ($records = $DB->get_records_select('questionnaire_question', $select, null, 'position ASC')) {
        foreach ($records as $record) {
            $DB->set_field('questionnaire_question', 'position', $record->position - 1, array('id' => $record->id));
        }
    }
    // Delete section breaks without asking for confirmation.
    $qtype = $questionnaire->questions[$qid]->type_id;
    // No need to delete responses to those "question types" which are not real questions.
    if ($qtype == QUESPAGEBREAK || $qtype == QUESSECTIONTEXT) {
        $reload = true;
    } else {
        // Delete responses to that deleted question.
        questionnaire_delete_responses($qid);
        
        //Delete advdependencies pointing at the deleted question (parents to the deleted question)
        $DB->delete_records('questionnaire_dependencies', array('question_id' => $qid));
        
        // The deleted question was a parent, so now we must delete its child question(s).
        if (count($haschildren) !== 0) {
            foreach ($haschildren as $qid => $child) {
                // Need to reload questions first.
            	$questions = $DB->get_records('questionnaire_question', array('survey_id' => $sid, 'deleted' => 'n'), 'id');
            	
            	if ($questionnaire->navigate != 2){
            		$DB->set_field('questionnaire_question', 'deleted', 'y', array('id' => $qid, 'survey_id' => $sid));
            		$select = 'survey_id = '.$sid.' AND deleted = \'n\' AND position > '.
              		$questions[$qid]->position;
              		if ($records = $DB->get_records_select('questionnaire_question', $select, null, 'position ASC')) {
              			foreach ($records as $record) {
              				$DB->set_field('questionnaire_question', 'position', $record->position - 1, array('id' => $record->id));
              			}
              		}
              		// Delete responses to that deleted question.
              		questionnaire_delete_responses($qid);
            	} else {
            		/* 
            		 * Dependencies to the deleted question are listed and the direct ones removed - but not the childs themselfes.
            		 * 
            		 * It would be painful for users to force deletion of all dependend questions in advdependency-mode.
            		 * 1. childs can have multiple parents
            		 * 2. removing a single question can possibly delete large parts of the questionnaire
            		 * 3. one has to remove all conflicting dependencies by hand to avoid cascading deletion
            		 */
            		//TODO  this might be placed in locallib, see "questionnaire_get_descendants"
            		foreach ($questionnaire->questions as $questionlistitem) {
            			if (isset($questionlistitem->advdependencies)) {
            				foreach ($questionlistitem->advdependencies as $key => $outeradvdependencies) {
            					if ($outeradvdependencies->adv_dependquestion == $delq) {
            						$advchildren[$key] = $outeradvdependencies;
            					}
            				}
            			}
            		}
            		foreach ($advchildren as $key => $value){
            			$DB->delete_records('questionnaire_dependencies', array('id' => $key));
            		}
            	}
            }
        }

        // If no questions left in this questionnaire, remove all attempts and responses.
        if (!$questions = $DB->get_records('questionnaire_question', array('survey_id' => $sid, 'deleted' => 'n'), 'id') ) {
            $DB->delete_records('questionnaire_response', array('survey_id' => $sid));
            $DB->delete_records('questionnaire_attempts', array('qid' => $questionnaireid));
        }
    }

    // Log question deleted event.
    $context = context_module::instance($questionnaire->cm->id);
    $questiontype = \mod_questionnaire\question\base::qtypename($qtype);
    $params = array(
                    'context' => $context,
                    'courseid' => $questionnaire->course->id,
                    'other' => array('questiontype' => $questiontype)
    );
    $event = \mod_questionnaire\event\question_deleted::create($params);
    $event->trigger();

    if ($questionnairehasdependencies) {
        $SESSION->questionnaire->validateresults = questionnaire_check_page_breaks($questionnaire);
    }
    $reload = true;
}

if ($action == 'main') {
    $questionsform = new mod_questionnaire_questions_form('questions.php', $moveq);
    $sdata = clone($questionnaire->survey);
    $sdata->sid = $questionnaire->survey->id;
    $sdata->id = $cm->id;
    if (!empty($questionnaire->questions)) {
        $pos = 1;
        foreach ($questionnaire->questions as $qidx => $question) {
            $sdata->{'pos_'.$qidx} = $pos;
            $pos++;
        }
    }
    $questionsform->set_data($sdata);
    if ($questionsform->is_cancelled()) {
        // Switch to main screen.
        $action = 'main';
        redirect($CFG->wwwroot.'/mod/questionnaire/questions.php?id='.$questionnaire->cm->id);
        $reload = true;
    }
    if ($qformdata = $questionsform->get_data()) {
        // Quickforms doesn't return values for 'image' input types using 'exportValue', so we need to grab
        // it from the raw submitted data.
        $exformdata = data_submitted();

        if (isset($exformdata->movebutton)) {
            $qformdata->movebutton = $exformdata->movebutton;
        } else if (isset($exformdata->moveherebutton)) {
            $qformdata->moveherebutton = $exformdata->moveherebutton;
        } else if (isset($exformdata->editbutton)) {
            $qformdata->editbutton = $exformdata->editbutton;
        } else if (isset($exformdata->removebutton)) {
            $qformdata->removebutton = $exformdata->removebutton;
        } else if (isset($exformdata->requiredbutton)) {
            $qformdata->requiredbutton = $exformdata->requiredbutton;
        }

        // Insert a section break.
        if (isset($qformdata->removebutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.
            $qid = key($qformdata->removebutton);
            $qtype = $questionnaire->questions[$qid]->type_id;

            // Delete section breaks without asking for confirmation.
            if ($qtype == QUESPAGEBREAK) {
                redirect($CFG->wwwroot.'/mod/questionnaire/questions.php?id='.$questionnaire->cm->id.'&amp;delq='.$qid);
            }
            if ($questionnairehasdependencies) {
            	if ($questionnaire->navigate != 2) {
            		$haschildren  = questionnaire_get_descendants ($questionnaire->questions, $qid);
            	} else {
            		//TODO refactor into questionnaire_get_descendants in locallib?
            		//Important: due to possibly multiple parents per question
            		//Just remove the advdependency and inform the user about it.
            		$haschildren = questionnaire_get_advdescendants ($questionnaire->questions, $qid);
            	}
            }
            if (count($haschildren) != 0) {
                $action = "confirmdelquestionparent";
            } else {
                $action = "confirmdelquestion";
            }

        } else if (isset($qformdata->editbutton)) {
            // Switch to edit question screen.
            $action = 'question';
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.
            $qid = key($qformdata->editbutton);
            $reload = true;

        } else if (isset($qformdata->requiredbutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.

            $qid = key($qformdata->requiredbutton);
            if ($questionnaire->questions[$qid]->required == 'y') {
                $questionnaire->questions[$qid]->set_required(false);

            } else {
                $questionnaire->questions[$qid]->set_required(true);
            }

            $reload = true;

        } else if (isset($qformdata->addqbutton)) {
            if ($qformdata->type_id == QUESPAGEBREAK) { // Adding section break is handled right away....
                $questionrec = new stdClass();
                $questionrec->survey_id = $qformdata->sid;
                $questionrec->type_id = QUESPAGEBREAK;
                $questionrec->content = 'break';
                $question = \mod_questionnaire\question\base::question_builder(QUESPAGEBREAK);
                $question->add($questionrec);
                $reload = true;
            } else {
                // Switch to edit question screen.
                $action = 'question';
                $qtype = $qformdata->type_id;
                $qid = 0;
                $reload = true;
            }

        } else if (isset($qformdata->movebutton)) {
            // Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
            redirect($CFG->wwwroot.'/mod/questionnaire/questions.php?id='.$questionnaire->cm->id.
                     '&moveq='.key($qformdata->movebutton));
            $reload = true;



        } else if (isset($qformdata->moveherebutton)) {
            // Need to use the key, since IE returns the image position as the value rather than the specified
            // value in the <input> tag.

            // No need to move question if new position = old position!
            $qpos = key($qformdata->moveherebutton);
            if ($qformdata->moveq != $qpos) {
                $questionnaire->move_question($qformdata->moveq, $qpos);
            }
            if ($questionnairehasdependencies) {
                $SESSION->questionnaire->validateresults = questionnaire_check_page_breaks($questionnaire);
            }
            // Nothing I do will seem to reload the form with new data, except for moving away from the page, so...
            redirect($CFG->wwwroot.'/mod/questionnaire/questions.php?id='.$questionnaire->cm->id);
            $reload = true;

        } else if (isset($qformdata->validate)) {
            // Validates page breaks for depend questions.
            $SESSION->questionnaire->validateresults = questionnaire_check_page_breaks($questionnaire);
            $reload = true;
        }
    }


} else if ($action == 'question') {
    $question = questionnaire_prep_for_questionform($questionnaire, $qid, $qtype);
    $questionsform = new mod_questionnaire_edit_question_form('questions.php');
    $questionsform->set_data($question);
    if ($questionsform->is_cancelled()) {
        // Switch to main screen.
        $action = 'main';
        $reload = true;

    } else if ($qformdata = $questionsform->get_data()) {
        // Saving question data.
        if (isset($qformdata->makecopy)) {
            $qformdata->qid = 0;
        }

        $question->form_update($qformdata, $questionnaire);

        // Make these field values 'sticky' for further new questions.
        if (!isset($qformdata->required)) {
            $qformdata->required = 'n';
        }
        
        //just check it, following lines not needed anymore
        questionnaire_check_page_breaks($questionnaire);
        // Need to reload questions.
        /*$questions = $DB->get_records('questionnaire_question', array('survey_id' => $sid, 'deleted' => 'n'), 'id');
        $questionnairehasdependencies = questionnaire_has_dependencies($questions);
        if (questionnaire_has_dependencies($questions)) {
            questionnaire_check_page_breaks($questionnaire);
        }*/
        $SESSION->questionnaire->required = $qformdata->required;
        $SESSION->questionnaire->type_id = $qformdata->type_id;
        // Switch to main screen.
        $action = 'main';
        $reload = true;
    }

    // Log question created event.
    if (isset($qformdata)) {
        $context = context_module::instance($questionnaire->cm->id);
        $questiontype = \mod_questionnaire\question\base::qtypename($qformdata->type_id);
        $params = array(
                        'context' => $context,
                        'courseid' => $questionnaire->course->id,
                        'other' => array('questiontype' => $questiontype)
        );
        $event = \mod_questionnaire\event\question_created::create($params);
        $event->trigger();
    }

    $questionsform->set_data($question);
}

// Reload the form data if called for...
if ($reload) {
    unset($questionsform);
    $questionnaire = new questionnaire($questionnaire->id, null, $course, $cm);
    // Add renderer and page objects to the questionnaire object for display use.
    $questionnaire->add_renderer($PAGE->get_renderer('mod_questionnaire'));
    $questionnaire->add_page(new \mod_questionnaire\output\questionspage());
    if ($action == 'main') {
        $questionsform = new mod_questionnaire_questions_form('questions.php', $moveq);
        $sdata = clone($questionnaire->survey);
        $sdata->sid = $questionnaire->survey->id;
        $sdata->id = $cm->id;
        if (!empty($questionnaire->questions)) {
            $pos = 1;
            foreach ($questionnaire->questions as $qidx => $question) {
                $sdata->{'pos_'.$qidx} = $pos;
                $pos++;
            }
        }
        $questionsform->set_data($sdata);
    } else if ($action == 'question') {
        $question = questionnaire_prep_for_questionform($questionnaire, $qid, $qtype);
        $questionsform = new mod_questionnaire_edit_question_form('questions.php');
        $questionsform->set_data($question);
    }
}

// Print the page header.
if ($action == 'question') {
    if (isset($question->qid)) {
        $streditquestion = get_string('editquestion', 'questionnaire', questionnaire_get_type($question->type_id));
    } else {
        $streditquestion = get_string('addnewquestion', 'questionnaire', questionnaire_get_type($question->type_id));
    }
} else {
    $streditquestion = get_string('managequestions', 'questionnaire');
}

$PAGE->set_title($streditquestion);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($streditquestion);
echo $questionnaire->renderer->header();
require('tabs.php');

if ($action == "confirmdelquestion" || $action == "confirmdelquestionparent") {

    $qid = key($qformdata->removebutton);
    $question = $questionnaire->questions[$qid];
    $qtype = $question->type_id;

    // Count responses already saved for that question.
    $countresps = 0;
    if ($qtype != QUESSECTIONTEXT) {
        $responsetable = $DB->get_field('questionnaire_question_type', 'response_table', array('typeid' => $qtype));
        if (!empty($responsetable)) {
            $countresps = $DB->count_records('questionnaire_'.$responsetable, array('question_id' => $qid));
        }
    }

    // Needed to print potential media in question text.

    // If question text is "empty", i.e. 2 non-breaking spaces were inserted, do not display any question text.

    if ($question->content == '<p>  </p>') {
        $question->content = '';
    }

    $qname = '';
    if ($question->name) {
        $qname = ' ('.$question->name.')';
    }

    $num = get_string('position', 'questionnaire');
    $pos = $question->position.$qname;

    $msg = '<div class="warning centerpara"><p>'.get_string('confirmdelquestion', 'questionnaire', $pos).'</p>';
    if ($countresps !== 0) {
        $msg .= '<p>'.get_string('confirmdelquestionresps', 'questionnaire', $countresps).'</p>';
    }
    $msg .= '</div>';
    $msg .= '<div class = "qn-container">'.$num.' '.$pos.'<div class="qn-question">'.$question->content.'</div></div>';
    $args = "id={$questionnaire->cm->id}";
    $urlno = new moodle_url("/mod/questionnaire/questions.php?{$args}");
    $args .= "&delq={$qid}";
    $urlyes = new moodle_url("/mod/questionnaire/questions.php?{$args}");
    $buttonyes = new single_button($urlyes, get_string('yes'));
    $buttonno = new single_button($urlno, get_string('no'));
    if ($action == "confirmdelquestionparent") {
    	$strnum = get_string('position', 'questionnaire');
    	$qid = key($qformdata->removebutton);
    	if ($questionnaire->navigate != 2) {
    		$msg .= '<div class="warning">'.get_string('confirmdelchildren', 'questionnaire').'</div><br />';
    		foreach ($haschildren as $child) {
    			$childname = '';
    			if ($child['name']) {
    				$childname = ' ('.$child['name'].')';
    			}
    			$msg .= '<div class = "qn-container">'.$strnum.' '.$child['position'].$childname.'<span class="qdepend"><strong>'.
      			get_string('dependquestion', 'questionnaire').'</strong>'.
      			' ('.$strnum.' '.$child['parentposition'].') '.
      			'&nbsp;:&nbsp;'.$child['parent'].'</span>'.
      			'<div class="qn-question">'.
      			$child['content'].
      			'</div></div>';
    		}
    	} else {
    		//Show the advdependencies and inform about the advdependencies to be removed
    		
    		//Split dependencies in direct and indirect ones to separate for the confirm-dialogue. Only direct ones will be deleted.
    		$directchildren = array();
    		$indirectchildren = array();
    		foreach ($haschildren as $key => $child) {
    			foreach ($child as $subchild){
    				if ($subchild['qdependquestion'] == 'q'.$question->id) {
    					$directchildren[$key][] = $subchild;
    				} else {
    					$indirectchildren[$key][] = $subchild;
    				}
    			}
    		}
    		
    		//List direct dependencies
    		//TODO replace static string
    		$msg .= '<div class="warning">Direct dependencies to this question will be removed. This will affect:</div><br />';
    		foreach ($directchildren as $child) {
    			$loopindicator = array();
    			foreach ($child as $subchild) {
    				$childname = '';
    				if ($subchild['name']) {
    					$childname = ' ('.$subchild['name'].')';
    				}
    				
    				//Add conditions
    				switch ($subchild['adv_dependlogic']) {
    					case 0:
    						$logic = ' not set';
    						break;
    					case 1:
    						$logic = " set";
    						break;
    					default:
    						$logic = "";
    				}
    				
    				//Different colouring for and/or
    				switch ($subchild['adv_depend_and_or']) {
    					case 'or':
    						$color = 'qdepend-or';
    						break;
    					case 'and':
    						$color= "qdepend";
    						break;
    					default:
    						$color= "";
    				}
    				
    				if (!in_array($subchild['qdependquestion'], $loopindicator)) {
    					$msg .= '<div class = "qn-container">'.$strnum.' '.$subchild['position'].$childname.'<br/><span class="'.$color.'"><strong>'.
    					get_string('dependquestion', 'questionnaire').'</strong>'.
      					' ('.$strnum.' '.$subchild['parentposition'].') '.
      					'&nbsp;:&nbsp;'.$subchild['parent'].' '.$logic.'</span>';
    				} else {
    					$msg .= '<br/><span class="'.$color.'"><strong>'.
      					get_string('dependquestion', 'questionnaire').'</strong>'.
      					' ('.$strnum.' '.$subchild['parentposition'].') '.
      					'&nbsp;:&nbsp;'.$subchild['parent'].' '.$logic.'</span>';
    				}
      				$loopindicator[] = $subchild['qdependquestion'];
    			}
    			$msg.=  '<div class="qn-question">'.
      			$subchild['content'].
      			'</div></div>';
    		}

    		//List indirect dependencies
    		//TODO replace static string
    		$msg .= '<div class="warning">This list shows the indirect dependent questions and the remaining dependencies for direct dependent questions:</div><br />';
    		foreach ($indirectchildren as $child) {
    			$loopindicator = array();
    			foreach ($child as $subchild) {
    				$childname = '';
    				if ($subchild['name']) {
    					$childname = ' ('.$subchild['name'].')';
    				}
    				
    				//Add conditions
    				switch ($subchild['adv_dependlogic']) {
    					case 0:
    						$logic = ' not set';
    						break;
    					case 1:
    						$logic = " set";
    						break;
    					default:
    						$logic = "";
    				}
    				
    				//Different colouring for and/or
    				switch ($subchild['adv_depend_and_or']) {
    					case 'or':
    						$color = 'qdepend-or';
    						break;
    					case 'and':
    						$color= "qdepend";
    						break;
    					default:
    						$color= "";
    				}
    				
    				if (!in_array($subchild['qdependquestion'], $loopindicator)) {
    					$msg .= '<div class = "qn-container">'.$strnum.' '.$subchild['position'].$childname.'<br/><span class="'.$color.'"><strong>'.
      					get_string('dependquestion', 'questionnaire').'</strong>'.
      					' ('.$strnum.' '.$subchild['parentposition'].') '.
      					'&nbsp;:&nbsp;'.$subchild['parent'].' '.$logic.'</span>';
    				} else {
    					$msg .= '<br/><span class="'.$color.'"><strong>'.
      					get_string('dependquestion', 'questionnaire').'</strong>'.
      					' ('.$strnum.' '.$subchild['parentposition'].') '.
      					'&nbsp;:&nbsp;'.$subchild['parent'].' '.$logic.'</span>';
    				}
    				$loopindicator[] = $subchild['qdependquestion'];
    			}
    			$msg.=  '<div class="qn-question">'.
      			$subchild['content'].
      			'</div></div>';
    		}
    	}

    }
    $questionnaire->page->add_to_page('formarea', $questionnaire->renderer->confirm($msg, $buttonyes, $buttonno));

} else {
    $questionnaire->page->add_to_page('formarea', $questionsform->render());
}
echo $questionnaire->renderer->render($questionnaire->page);
echo $questionnaire->renderer->footer();