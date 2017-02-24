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
 * @authors Mike Churchward & Joseph Rézeau
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questionnaire
 */
require_once($CFG->libdir . '/formslib.php');

class mod_questionnaire_edit_question_form extends moodleform {

    public function definition() {
        global $questionnaire, $question, $SESSION;

        // The 'sticky' required response value for further new questions.
        if (isset($SESSION->questionnaire->required) && !isset($question->qid)) {
            $question->required = $SESSION->questionnaire->required;
        }
        if (!isset($question->type_id)) {
            print_error('undefinedquestiontype', 'questionnaire');
        }

        $mform =& $this->_form;

        //START advnavigation

        // Each question can provide its own form elements to the provided form, or use the default ones.
        //Splitting the formcreation into two parts, to fit the repeatarea in between
        if (!$question->edit_form_pre_dependencies($mform, $questionnaire, $this->_customdata['modcontext'])) {
            print_error("Question type had an unknown error in the edit_form method.");
        }
        
        //Create a new area for multiple dependencies
        //FIXME Has to be here(?), because it requires moodleform. Would be more consistent to place it in base.php
        //Checking for $questionnaire->navigate == 1 for the original branching is still in base.php
        if ($questionnaire->navigate == 2) {
        	$position = ($question->position !== 0) ? $question->position : count($questionnaire->questions) + 1;
        	
        	//The Dropdown to select from is in here:
        	$dependencies = questionnaire_get_dependencies($questionnaire->questions, $position);
        	$canchangeparent = true;
        	
        	$mform->addElement('header', 'advdependencies_hdr', 'Dependencies');
        	$mform->setExpanded('advdependencies_hdr');
        	
        	//If this question has children, you may not change it's parent
        	if (count($dependencies) > 1) {
	        		if (isset($question->qid)) {
	        			$haschildren = questionnaire_get_descendants ($questionnaire->questions, $question->qid);
	        			if (count($haschildren) !== 0) {
	        				$canchangeparent = false;
	        				$parent = questionnaire_get_parent ($question);
	        				
	        				//TODO - Change to list for all parents
	        				$fixeddependency = $parent [$question->id]['parent'];
	        			}
	        		}
	        		
	        		if ($canchangeparent) {
	        			
	        			//TODO get adv_dependencies from DB and initialize the form properly
	        			//$question->advdependencies = isset($question->dependquestion) ? $question->dependquestion.','.$question->dependchoice : '0,0';
	 
	        			$select = $mform->createElement('select', 'advdependencies_condition', 'Condition', array('Not this answer given', 'This answer given'));
	        			$select->setSelected('1');
	        			
	        			$groupitems = array();
	        			$groupitems[] =& $mform->createElement('selectgroups', 'advdependencies_select', 'Parent', $dependencies);
	        			$groupitems[] =& $select;
	        			$group = $mform->createElement('group', 'selectdependency', get_string('dependquestion', 'questionnaire'), $groupitems, ' ', false);
	
	        			$this->repeat_elements(array($group), 1, array(), 'numradios', 'addradios',2);
	        			
	        		} else {
	        			$mform->addElement('static', 'selectdependency', get_string('dependquestion', 'questionnaire'),
	        					'<div class="dimmed_text">'.$fixeddependency.'</div>');
	        	}
	        	$mform->addElement('header', 'qst_and_choices_hdr', 'Questiontext and answers');
	        	 
        	}
        }
        
        // Each question can provide its own form elements to the provided form, or use the default ones.
        if (!$question->edit_form_post_dependencies($mform, $questionnaire, $this->_customdata['modcontext'])) {
        	print_error("Question type had an unknown error in the edit_form method.");
        }
        // END advnavigation
        
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // If this is a rate question.
        if ($data['type_id'] == QUESRATE) {
            if ($data['length'] < 2) {
                $errors["length"] = get_string('notenoughscaleitems', 'questionnaire');
            }
            // If this is a rate question with no duplicates option.
            if ($data['precise'] == 2 ) {
                $allchoices = $data['allchoices'];
                $allchoices = explode("\n", $allchoices);
                $nbvalues = 0;
                foreach ($allchoices as $choice) {
                    if ($choice && !preg_match("/^[0-9]{1,3}=/", $choice)) {
                            $nbvalues++;
                    }
                }
                if ($nbvalues < 2) {
                    $errors["allchoices"] = get_string('noduplicateschoiceserror', 'questionnaire');
                }
            }
        }

        return $errors;
    }
}
