@block @block_mark_manager @javascript
Feature: Mark Manager settings and error handling (E26, E28)
  In order to run the block in any language and noticing failures
  As an administrator
  I need settings to apply, strings to translate and errors to surface

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
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "Mark Manager" block
    And I log out

  Scenario: E26 - Global role settings are saved and applied
    Given I log in as "admin"
    And the following config values are set as admin:
      | viewroles | 3 | block_mark_manager |
    When I am on "Course 1" course homepage
    Then "Mark Manager" "block" should exist

  Scenario: E28 - Error notification is shown when saving fails
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I click on "Requires Grading" "link" in the "Mark Manager" "block"
    And I click on "Student 1" "text" in the "Grading" "dialogue"
    When I set the field "Grade" in the "Grading" "dialogue" to "not-a-number"
    And I click on "Save grade" "button"
    Then I should see "Please enter a grade before saving"
