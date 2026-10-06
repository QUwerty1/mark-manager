@block @block_mark_manager @javascript
Feature: Mark Manager saving of assignment grades (E12, E12.2, E15, E15.2)
  In order to record grades faster
  As a teacher
  I need to save assignment grades from the modal

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
    And the following "activity" exists:
      | activity                            | assign                  |
      | course                              | C1                      |
      | name                                | Assignment 1            |
      | intro                               | Submit your online text |
      | assignsubmission_onlinetext_enabled | 1                       |
      | submissiondrafts                    | 0                       |
    And the following "mod_assign > submissions" exist:
      | assign       | user     | onlinetext         |
      | Assignment 1 | student1 | Student submission |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "Mark Manager" block
    And I am on "Course 1" course homepage
    And I click on "Requires Grading" "link" in the "Mark Manager" "block"
    And I click on "Student 1" "text" in the "Grading" "dialogue"

  Scenario: E12 - Assignment grade is saved on Save button click
    When I set the field "Grade" in the "Grading" "dialogue" to "85"
    And I set the field "Feedback" in the "Grading" "dialogue" to "Good job"
    And I click on "Save grade" "button"
    Then I should see "Grade saved successfully."

  Scenario: E12.2 - Assignment grade is saved on Enter in the grade field
    When I set the field "Feedback" in the "Grading" "dialogue" to "Good job"
    And I set the field "Grade" in the "Grading" "dialogue" to "85"
    And I press the enter key
    Then I should see "Grade saved successfully."

  Scenario: E15 - Warning is shown when saving without a grade via the button
    When I set the field "Grade" in the "Grading" "dialogue" to ""
    And I click on "Save grade" "button"
    Then I should see "Please enter a grade before saving"

  Scenario: E15.2 - Warning is shown when saving without a grade via Enter
    When I set the field "Grade" in the "Grading" "dialogue" to ""
    And I press the enter key
    Then I should see "Please enter a grade before saving"
