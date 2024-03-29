
Feature: Standardizing user info

  Scenario Outline: Sanitizing input values
    Given I create a User with a <field> value of <input>
    When I get the info from that User
    Then the <field> value should be <output>

    Examples:
      | field       | input   | output |
      | locked      | 'no'    | 'no'   |
      | locked      | '0'     | 'no'   |
      | locked      | 0       | 'no'   |
      | locked      | 'false' | 'no'   |
      | locked      | 'False' | 'no'   |
      | locked      | 'FALSE' | 'no'   |
      | locked      | false   | 'no'   |
      | locked      | 'other' | 'no'   |
      | locked      | 'yes'   | 'yes'  |
      | locked      | '1'     | 'yes'  |
      | locked      | 1       | 'yes'  |
      | locked      | 'true'  | 'yes'  |
      | locked      | 'True'  | 'yes'  |
      | locked      | 'TRUE'  | 'yes'  |
      | locked      | true    | 'yes'  |
      | locked      | null    | null   |
      | require_mfa | 'no'    | 'no'   |
      | require_mfa | '0'     | 'no'   |
      | require_mfa | 0       | 'no'   |
      | require_mfa | 'false' | 'no'   |
      | require_mfa | 'False' | 'no'   |
      | require_mfa | 'FALSE' | 'no'   |
      | require_mfa | false   | 'no'   |
      | require_mfa | 'other' | 'no'   |
      | require_mfa | 'yes'   | 'yes'  |
      | require_mfa | '1'     | 'yes'  |
      | require_mfa | 1       | 'yes'  |
      | require_mfa | 'true'  | 'yes'  |
      | require_mfa | 'True'  | 'yes'  |
      | require_mfa | 'TRUE'  | 'yes'  |
      | require_mfa | true    | 'yes'  |
      | require_mfa | null    | null   |

  Scenario Outline: When getting User info, include (only) the provided fields
    Given I create a User with a <field> value of <input> and an Employee ID
    When I get the info from that User
    Then the result should ONLY contain <field> and an Employee ID

    Examples:
      | field               | input                      |
      | active              | 'yes'                      |
      | active              | 'no'                       |
      | active              | null                       |
      | display_name        | 'First Last'               |
      | display_name        | null                       |
      | email               | 'first_last@example.com'   |
      | email               | null                       |
      | first_name          | 'First'                    |
      | first_name          | null                       |
      | groups              | 'group1,group2'            |
      | groups              | null                       |
      | hr_contact_name     | 'Toby Flenderson'          |
      | hr_contact_name     | null                       |
      | hr_contact_email    | 'tobyf@office.example.com' |
      | hr_contact_email    | null                       |
      | last_name           | 'Last'                     |
      | last_name           | null                       |
      | locked              | 'yes'                      |
      | locked              | 'no'                       |
      | locked              | null                       |
      | manager_email       | 'michaels@example.com'     |
      | manager_email       | null                       |
      | personal_email      | 'personal@example.com'     |
      | personal_email      | null                       |
      | require_mfa         | 'yes'                      |
      | require_mfa         | 'no'                       |
      | require_mfa         | null                       |
      | username            | 'first_last'               |
      | username            | null                       |
