@block @block_mark_manager @javascript
Feature: Mark Manager modal window, filters, grouping and pagination (E4, E5, E6, E7, E8, E9, E10, E23)
  In order to find submissions quickly
  As a teacher
  I need the modal list to filter, group and paginate works

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Ivan      | Ivanov   | student1@example.com |
      | student2 | Petr      | Petrov   | student2@example.com |
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
    And the following "activity" exists:
      | activity                            | assign                  |
      | course                              | C1                      |
      | name                                | Assignment 2            |
      | intro                               | Submit your online text |
      | assignsubmission_onlinetext_enabled | 1                       |
      | submissiondrafts                    | 0                       |
    And the following "mod_assign > submissions" exist:
      | assign         | user     | onlinetext                 |
      | Assignment 1   | student1 | Ivanov first submission    |
      | Assignment 1   | student2 | Petrov first submission    |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "Mark Manager" block
    And I am on "Course 1" course homepage

  Scenario: E4 - Modal opens with the ungraded list and a prompt in the grading pane
    When I click on "Requires Grading" "link" in the "Mark Manager" "block"
    Then I should see "Submissions" in the "Grading" "dialogue"
    And I should see "Select a submission from the list to grade it." in the "Grading" "dialogue"
    And I should see "Ivan Ivanov" in the "Grading" "dialogue"

  Scenario: E5 - Modal opens with the graded filter preselected
    When I click on "Graded" "link" in the "Mark Manager" "block"
    Then I should see "Submissions" in the "Grading" "dialogue"
    And the field "Status" in the "Grading" "dialogue" matches value "graded"

  Scenario: E6 - Works list is filtered by student name from the search box
    Given I click on "Requires Grading" "link" in the "Mark Manager" "block"
    When I set the field "Student name" in the "Grading" "dialogue" to "Ivan"
    Then I should see "Ivan Ivanov" in the "Grading" "dialogue"
    And I should not see "Petr Petrov" in the "Grading" "dialogue"

  Scenario: E7 - Works list is filtered by status from the status select
    Given I click on "Requires Grading" "link" in the "Mark Manager" "block"
    When I set the field "Status" in the "Grading" "dialogue" to "unsubmitted"
    Then I should see "Assignment 2" in the "Grading" "dialogue"
    And I should not see "Assignment 1" in the "Grading" "dialogue"

  Scenario: E8 - Works are grouped by assignment
    Given I click on "Requires Grading" "link" in the "Mark Manager" "block"
    When I set the field "Group by" in the "Grading" "dialogue" to "assignment"
    Then I should see "Assignment 1" in the "Grading" "dialogue"

  Scenario: E9 - Works are grouped by course group with ungrouped users last
    Given the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | GA       |
    And the following "group members" exist:
      | group | user     |
      | GA    | student1 |
    And I am on "Course 1" course homepage
    And I click on "Requires Grading" "link" in the "Mark Manager" "block"
    When I set the field "Group by" in the "Grading" "dialogue" to "group"
    Then I should see "Group A" in the "Grading" "dialogue"
    And I should see "No group" in the "Grading" "dialogue"

  Scenario: E10 - Works list paginates forward
    Given 25 "users" exist with the following data:
      | username  | pagedstudent[count]          |
      | firstname | Paged                   |
      | lastname  | Student[count]          |
      | email     | pagedstudent[count]@example.com |
    And 25 "course enrolments" exist with the following data:
      | user    | pagedstudent[count] |
      | course  | C1             |
      | role    | student        |
    And 25 "mod_assign > submissions" exist with the following data:
      | assign       | Assignment 1            |
      | user         | pagedstudent[count]      |
      | onlinetext   | Paged submission [count] |
    And I am on "Course 1" course homepage
    When I click on "Requires Grading" "link" in the "Mark Manager" "block"
    Then I should see "Next" in the "Grading" "dialogue"
    When I click on "Next" "button" in the "Grading" "dialogue"
    Then I should see "Page 2 of 2" in the "Grading" "dialogue"

  Scenario: E23 - Filters are reset when the modal is reopened
    Given I click on "Requires Grading" "link" in the "Mark Manager" "block"
    And I set the field "Student name" in the "Grading" "dialogue" to "Ivan"
    And I click on "Close" "button"
    When I click on "Requires Grading" "link" in the "Mark Manager" "block"
    Then the field "Student name" in the "Grading" "dialogue" matches value ""
    And the field "Group by" in the "Grading" "dialogue" matches value "none"
