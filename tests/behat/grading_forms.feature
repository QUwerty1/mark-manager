@block @block_mark_manager @javascript
Feature: Mark Manager grading forms in the modal (E11, E16, E17, E18)
  In order to grade without leaving the course page
  As a teacher
  I need each work to open its grading form

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activity" exists:
      | activity                            | assign                  |
      | course                              | C1                      |
      | name                                | Assignment 1            |
      | intro                               | Submit your online text |
      | assignsubmission_onlinetext_enabled | 1                       |
      | submissiondrafts                    | 0                       |
    And the following "mod_assign > submissions" exist:
      | assign       | user     | onlinetext              |
      | Assignment 1 | student1 | First student submission |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "Mark Manager" block
    And I am on "Course 1" course homepage
    And I click on "Requires Grading" "link" in the "Mark Manager" "block"

  Scenario: E11 - Grading form loads when a work is clicked in the list
    When I click on "Student 1" "text" in the "Grading" "dialogue"
    Then I should see "Grade" in the "Grading" "dialogue"
    And I should see "Student 1" in the "Grading" "dialogue"
    And I should see "Assignment 1" in the "Grading" "dialogue"

  Scenario: E16 - Warning is shown for an unsubmitted work
    Given I set the field "Status" in the "Grading" "dialogue" to "unsubmitted"
    When I click on "Student 2" "text" in the "Grading" "dialogue"
    Then I should see "Submission required" in the "Grading" "dialogue"

  Scenario: E17 - Warning is shown for a quiz without a finished attempt
    Given the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | C1     | QUIZ1    |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name       | questiontext          |
      | Test questions   | essay | Essay Q1   | Write a short essay   |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | Essay Q1 | 1    |
    And I am on "Course 1" course homepage
    And I click on "Not Submitted" "link" in the "Mark Manager" "block"
    When I click on "Student 1" "text" in the "Grading" "dialogue"
    Then I should see "Attempt required" in the "Grading" "dialogue"

  Scenario: E18 - Full grading page opens in a new tab
    When I click on "Student 1" "text" in the "Grading" "dialogue"
    Then "Open full grading page" "link" should exist in the "Grading" "dialogue"
