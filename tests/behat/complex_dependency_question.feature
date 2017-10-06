@mod @mod_questionnaire
Feature: Questions can be defined to be dependent on answers to previous questions
  In order to define a dependency
  As a teacher
  I must specify that branching questions are allowed and then create question dependencies

  Background: Add a text box question that is dependent on a yes answer to a yes/no question.
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity | name | description | course | idnumber | resume | navigate |
      | questionnaire | Test questionnaire | Test questionnaire description | C1 | questionnaire0 | 1 | 1 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Questions" in current page administration
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q1 |
      | Yes | y |
      | Question Text | Are you still in School? |
    Then I should see "[Yes/No] (Q1)"
    And I add a "Check Boxes" question and I fill the form with:
      | Question Name | Y.1 |
      | No | n |
      | Min. forced responses | 0 |
      | Max. forced responses | 0 |
      | Question Text | Are you taking: |
      | Possible answers | Math,Physics,Art,Music |
      | id_dependquestions_and_0  | Q1->Yes |
    Then I should see "[Check Boxes] (Y.1)"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | MP.1 |
      | Yes | y |
      | Question Text | Do you plan to take a B.Sc.? |
      | id_dependquestions_and_0  | Q1->Yes |
      | id_dependquestions_or_0  | Y.1->Math |
      | id_dependquestions_or_1  | Y.1->Physics |
    Then I should see "[Yes/No] (MP.1)"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | AM.1 |
      | Yes | y |
      | Question Text | Do you plan to take a B.A.? |
      | id_dependquestions_and_0  | Q1->Yes |
      | id_dependquestions_or_0  | Y.1->Art |
      | id_dependquestions_or_1  | Y.1->Music |
    Then I should see "[Yes/No] (AM.1)"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | N.1 |
      | Yes | y |
      | Question Text | Are you taking an apprenticeship? |
      | id_dependquestions_and_0  | Q1->No |
    Then I should see "[Yes/No] (N.1)"
    And I add a "Yes/No" question and I fill the form with:
      | Question Name | Q2 |
      | Yes | y |
      | Question Text | Are you happy? |
    Then I should see "[Yes/No] (Q2)"
    And I log out

@javascript
  Scenario: Student should only be asked questions on school if they have answered yes to question 1.
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test questionnaire"
    And I navigate to "Answer the questions..." in current page administration
    Then I should see "Are you still in School?"
    And I click on "Yes" "radio"
    And I press "Next Page >>"
    Then I should see "Are you taking:"
    And I set the field "Math" to "checked"
    And I press "Next Page >>"
    Then I should see "Do you plan to take a B.Sc.?"
    And I click on "Yes" "radio"
    And I press "Next Page >>"
    Then I should see "Are you happy?"
    And I press "<< Previous Page"
    And I press "<< Previous Page"
    And I set the field "Art" to "checked"
    And I press "Next Page >>"
    Then I should see "Do you plan to take a B.Sc.?"
    And I press "Next Page >>"
    Then I should see "Do you plan to take a B.A.?"
    And I click on "No" "radio"
    And I press "Next Page >>"
    Then I should see "Are you happy?"
    And I press "<< Previous Page"
    And I press "<< Previous Page"
    And I press "<< Previous Page"
    And I click on "Math" "checkbox"
    And I press "Next Page >>"
    Then I should see "Do you plan to take a B.A.?"
    And I press "<< Previous Page"
    And I click on "Art" "checkbox"
    And I press "Next Page >>"
    Then I should see "Are you happy?"
    And I press "<< Previous Page"
    And I press "<< Previous Page"
    Then I should see "Are you still in School?"
    And I click on "No" "radio"
    And I press "Next Page >>"
    Then I should see "Are you taking an apprenticeship?"
    And I click on "No" "radio"
    And I press "Next Page >>"
    Then I should see "Are you happy?"