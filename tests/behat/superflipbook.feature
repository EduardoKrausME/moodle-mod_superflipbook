@mod @mod_superflipbook
Feature: Super Flipbook access control and display
  In order to read a course document
  As a learner
  I need an activity with the appropriate capabilities

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Compliance | COMPLIANCE | 0 |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | reader1 | Reader | One | reader1@example.org |
    And the following "course enrolments" exist:
      | user | course | role |
      | reader1 | COMPLIANCE | student |

  Scenario: A learner can enter the course as a reader
    Given I log in as "reader1"
    When I am on "Compliance" course homepage
    Then I should see "Compliance"
