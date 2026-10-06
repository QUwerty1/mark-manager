@block @block_mark_manager @javascript
Feature: Mark Manager individual access management (E19, E20, E21, E22)
  In order to delegate grading without changing roles
  As a teacher
  I need to grant and revoke individual access to the block

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Petr      | Petrov   | student1@example.com |
      | student2 | Ivan      | Ivanov   | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I turn editing mode on
    And I add the "Mark Manager" block

  Scenario: E19 - User is added to the individual access list
    Given I configure the "Mark Manager" block
    When I follow "Manage individual access"
    And I set the field "search" to "Petr"
    And I press "Search"
    And I click on "Add" "link" in the "Petr Petrov" "table_row"
    Then I should see "Access granted successfully."
    And I should see "Petr Petrov" in the "Users with individual access" "table"

  Scenario: E20 - User is removed from the individual access list
    Given I configure the "Mark Manager" block
    And I follow "Manage individual access"
    And I set the field "search" to "Petr"
    And I press "Search"
    And I click on "Add" "link" in the "Petr Petrov" "table_row"
    When I click on "Remove" "link" in the "Petr Petrov" "table_row"
    And I press "Continue"
    Then I should see "Access revoked successfully."

  Scenario: E21 - Block becomes visible after individual access is granted
    Given I configure the "Mark Manager" block
    And I follow "Manage individual access"
    And I set the field "search" to "Petr"
    And I press "Search"
    And I click on "Add" "link" in the "Petr Petrov" "table_row"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then "Mark Manager" "block" should exist

  Scenario: E22 - Block is hidden after individual access is revoked
    Given I configure the "Mark Manager" block
    And I follow "Manage individual access"
    And I set the field "search" to "Petr"
    And I press "Search"
    And I click on "Add" "link" in the "Petr Petrov" "table_row"
    And I click on "Remove" "link" in the "Petr Petrov" "table_row"
    And I press "Continue"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then "Mark Manager" "block" should not exist
