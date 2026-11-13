@block @block_mark_manager @mod_workshop @javascript
Feature: Mark Manager grading of workshops (E29 - E32)
  In order to evaluate workshop submissions from the course page
  As a teacher
  I need the workshop grading form to override both workshop grades and follow the workshop phases

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
    And the following "activities" exist:
      | activity | name       | course | idnumber  | submissiontypetext | submissiontypefile |
      | workshop | Workshop 1 | C1     | workshop1 | 2                  | 0                  |
    # The teacher prepares the assessment form and opens the submission phase.
    And I am on the "Course 1" course page logged in as teacher1
    And I edit assessment form in workshop "Workshop 1" as:
      | id_description__idx_0_editor | Aspect1 |
      | id_description__idx_1_editor | Aspect2 |
      | id_description__idx_2_editor |         |
    And I change phase in workshop "Workshop 1" to "Submission phase"
    # Student 1 and Student 2 submit; Student 3 does not.
    And I am on the "Course 1" course page logged in as student1
    And I add a submission in workshop "Workshop 1" as:
      | Title              | Essay 1         |
      | Submission content | Text of essay 1 |
    And I am on the "Course 1" course page logged in as student2
    And I add a submission in workshop "Workshop 1" as:
      | Title              | Essay 2         |
      | Submission content | Text of essay 2 |
    # The students review each other.
    And I am on the "Course 1" course page logged in as teacher1
    And I allocate submissions in workshop "Workshop 1" as:
      | Participant | Reviewer  |
      | Student 1   | Student 2 |
      | Student 2   | Student 1 |
    And I am on "Course 1" course homepage
    And I change phase in workshop "Workshop 1" to "Assessment phase"
    And I am on the "Course 1" course page logged in as student2
    And I assess submission "Essay 1" in workshop "Workshop 1" as:
      | grade__idx_0            | 6 / 10     |
      | peercomment__idx_0      |            |
      | grade__idx_1            | 7 / 10     |
      | peercomment__idx_1      |            |
      | Feedback for the author | Keep it up |
    And I am on the "Course 1" course page logged in as student1
    And I assess submission "Essay 2" in workshop "Workshop 1" as:
      | grade__idx_0            | 5 / 10    |
      | peercomment__idx_0      |           |
      | grade__idx_1            | 10 / 10   |
      | peercomment__idx_1      |           |
      | Feedback for the author | Good work |
    # The teacher adds the block.
    And I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    And I add the "Mark Manager" block
    And I turn editing mode off

  Scenario: E29 - Workshop grading form is read-only outside the grading evaluation phase
    Given I click on "Requires Grading" "link" in the "Mark Manager" "block"
    When I click on "Student 1" "text" in the "Grading" "dialogue"
    Then I should see "Essay 1" in the "Grading" "dialogue"
    And I should see "Grades can be overridden only in the grading evaluation phase" in the "Grading" "dialogue"
    And the "Override grade" "field" should be disabled
    And the "Save grade" "button" should be disabled

  Scenario: E30 - Teacher overrides both workshop grades and the form and counters are refreshed
    Given I change phase in workshop "Workshop 1" to "Grading evaluation phase"
    And I press "Re-calculate grades"
    And I am on "Course 1" course homepage
    And "//li[a[@data-status='ungraded']]/span[normalize-space(.)='2']" "xpath_element" should exist in the "Mark Manager" "block"
    And "//li[a[@data-status='graded']]/span[normalize-space(.)='0']" "xpath_element" should exist in the "Mark Manager" "block"
    And I click on "Requires Grading" "link" in the "Mark Manager" "block"
    And I click on "Student 1" "text" in the "Grading" "dialogue"
    And I should not see "Grades can be overridden only in the grading evaluation phase" in the "Grading" "dialogue"
    # 65 % from the peer assessment of Student 2 is 52 of 80.
    And I should see "52" in the "//p[contains(., 'Calculated grade from peer assessments')]/strong" "xpath_element"
    And I should see "Essay 2" in the "Grading" "dialogue"
    When I set the field "Override grade" to "70"
    And I set the field "Feedback" to "Well structured"
    And I set the field "Override grade for assessment of Essay 2" to "15"
    And I press "Save grade"
    Then I should see "Grade saved successfully."
    # The form is reloaded: the aggregated grade for assessment is recalculated from the override.
    And I should see "15" in the "//div[contains(@class, 'mm-workshop-assessments-block')]/p[contains(., 'Aggregated grade')]/strong" "xpath_element"
    And the field "Override grade" matches value "70"
    And the field "Feedback" matches value "Well structured"
    And the field "Override grade for assessment of Essay 2" matches value "15"
    # The counters of the block are refreshed without reloading the page.
    And "//li[a[@data-status='ungraded']]/span[normalize-space(.)='1']" "xpath_element" should exist in the "Mark Manager" "block"
    And "//li[a[@data-status='graded']]/span[normalize-space(.)='1']" "xpath_element" should exist in the "Mark Manager" "block"
    # As in core, the gradebook gets the grade only when the workshop is closed.
    And I am on "Course 1" course homepage
    And I navigate to "View > User report" in the course gradebook
    And I set the field "Select all or one user" to "Student 1"
    And I should not see "70.00" in the "Workshop 1 (submission)" "table_row"
    And I am on "Course 1" course homepage
    And I change phase in workshop "Workshop 1" to "Closed"
    And I navigate to "View > User report" in the course gradebook
    And I set the field "Select all or one user" to "Student 1"
    And I should see "70.00" in the "Workshop 1 (submission)" "table_row"
    And I should see "15.00" in the "Workshop 1 (assessment)" "table_row"

  Scenario: E31 - Saving an override outside the allowed range is refused
    Given I change phase in workshop "Workshop 1" to "Grading evaluation phase"
    And I press "Re-calculate grades"
    And I am on "Course 1" course homepage
    And I click on "Requires Grading" "link" in the "Mark Manager" "block"
    And I click on "Student 1" "text" in the "Grading" "dialogue"
    When I set the field "Override grade" to "90"
    And I press "Save grade"
    Then I should see "The grade must be between 0 and 80"
    And "//li[a[@data-status='graded']]/span[normalize-space(.)='0']" "xpath_element" should exist in the "Mark Manager" "block"

  Scenario: E32 - Warning is shown for a student without a workshop submission
    Given I click on "Not Submitted" "link" in the "Mark Manager" "block"
    When I click on "Student 3" "text" in the "Grading" "dialogue"
    Then I should see "Submission required" in the "Grading" "dialogue"
    And I should see "has not submitted any work to this workshop" in the "Grading" "dialogue"
