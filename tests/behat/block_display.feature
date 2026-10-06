@block @block_mark_manager @javascript
Feature: Mark Manager block visibility and placement (E1, E2, E3, E24, E25)
  In order to control who can use the grading dashboard
  As a teacher
  I need the Mark Manager block to be visible only to authorised users

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
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Mark Manager" block
    And I log out

  Scenario: E1 - Teacher adds the Mark Manager block in editing mode
    Given the following config values are set as admin:
      | viewroles | 3 | block_mark_manager |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    Then "Mark Manager" "block" should exist
    And I should see "Requires Grading" in the "Mark Manager" "block"
    And I should see "Graded" in the "Mark Manager" "block"
    And I should see "Not Submitted" in the "Mark Manager" "block"
    And I should see "Progress Report" in the "Mark Manager" "block"
    And I should see "Student List" in the "Mark Manager" "block"

  Scenario: E2 - Mark Manager block is not offered to a student
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I open flat navigation drawer
    Then "Add a block" "link" should not exist in the "#nav-drawer" "css_element"
    And "Mark Manager" "link" should not exist in the "#nav-drawer" "css_element"

  Scenario: E3 - Block is hidden for a student without the view capability
    Given I log in as "student1"
    When I am on "Course 1" course homepage
    Then "Mark Manager" "block" should not exist

  Scenario: E24 - Report links lead to the grader report and the participants list
    Given the following config values are set as admin:
      | viewroles | 3 | block_mark_manager |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I click on "Progress Report" "link" in the "Mark Manager" "block"
    Then I should see "Grader report"
    When I am on "Course 1" course homepage
    And I click on "Student List" "link" in the "Mark Manager" "block"
    Then I should see "Participants"

  Scenario: E25 - A second instance of the block cannot be added to the same course
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    Then the add block selector should not contain "Mark Manager" block
