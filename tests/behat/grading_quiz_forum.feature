@block @block_mark_manager @javascript
Feature: Mark Manager saving of essay and forum grades (E13, E13.2, E14, E14.2)
  In order to record grades faster
  As a teacher
  I need to save essay and forum grades from the modal

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | QUIZ1    |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name     | questiontext        | defaultmark |
      | Test questions   | essay | Essay Q1 | Write a short essay | 10          |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | Essay Q1 | 1    |
    # The student finishes a quiz attempt so the essay appears under "Requires Grading".
    And I log in as "student1"
    And I am on the "Quiz 1" "mod_quiz > View" page
    And I press "Attempt quiz now"
    And I follow "Finish attempt ..."
    And I press "Submit all and finish"
    And I click on "Submit all and finish" "button" in the "Confirmation" "dialogue"
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "Mark Manager" block
    And I am on "Course 1" course homepage
    And I click on "Requires Grading" "link" in the "Mark Manager" "block"
    And I click on "Student 1" "text" in the "Grading" "dialogue"

  Scenario: E13 - Essay grade is saved on Save button click
    When I set the field "Grade" in the "Grading" "dialogue" to "7.5"
    And I click on "Save grade" "button"
    Then I should see "Grade saved successfully."

  Scenario: E13.2 - Essay grade is saved on Enter in the mark field
    When I set the field "Grade" in the "Grading" "dialogue" to "7.5"
    And I press the enter key
    Then I should see "Grade saved successfully."

  Scenario: E14 - Forum grade is saved on Save button click
    Given the following "activity" exists:
      | activity | forum   |
      | course   | C1      |
      | name     | Forum 1 |
      | type     | general |
      | assessed | 1       |
      | scale    | 10      |
    And the following "mod_forum > discussions" exist:
      | forum   | course | user     | name         | message            |
      | Forum 1 | C1     | student1 | Discussion 1 | Student forum post |
    And I am on "Course 1" course homepage
    And I click on "Requires Grading" "link" in the "Mark Manager" "block"
    And I click on "Student 1" "text" in the "Grading" "dialogue"
    When I set the field "Grade" in the "Grading" "dialogue" to "4"
    And I click on "Save grade" "button"
    Then I should see "Grade saved successfully."

  Scenario: E14.2 - Forum grade is saved on Enter in the grade field
    Given the following "activity" exists:
      | activity | forum   |
      | course   | C1      |
      | name     | Forum 1 |
      | type     | general |
      | assessed | 1       |
      | scale    | 10      |
    And the following "mod_forum > discussions" exist:
      | forum   | course | user     | name         | message            |
      | Forum 1 | C1     | student1 | Discussion 1 | Student forum post |
    And I am on "Course 1" course homepage
    And I click on "Requires Grading" "link" in the "Mark Manager" "block"
    And I click on "Student 1" "text" in the "Grading" "dialogue"
    When I set the field "Grade" in the "Grading" "dialogue" to "4"
    And I press the enter key
    Then I should see "Grade saved successfully."
