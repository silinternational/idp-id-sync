<?php

namespace Sil\Idp\IdSync\Behat\Context;

use Exception;
use PHPUnit\Framework\Assert;
use Sil\Idp\IdSync\common\components\notify\FakeEmailNotifier;
use Sil\Idp\IdSync\common\models\User;

/**
 * Defines application features from the specific context.
 */
class NotificationContext extends SyncContext
{
    /** @var array */
    private $users;

    public function __construct()
    {
        parent::__construct();
        $this->notifier = new FakeEmailNotifier();
    }

    /**
     * @Given at least one user has no email address
     */
    public function atLeastOneUserHasNoEmailAddress()
    {
        $this->users[] = new User(['employee_id' => 1]);
    }

    /**
     * @When I call the sendMissingEmailNotice function
     */
    public function iCallTheSendmissingemailnoticeFunction()
    {
        $this->notifier->sendMissingEmailNotice($this->users);
    }

    /**
     * @Then an email is sent
     */
    public function anEmailIsSent()
    {
        Assert::assertNotEmpty($this->notifier->emailsSent);
    }

    /**
     * @Given a specific user exists in the ID Store without an email address
     */
    public function aSpecificUserExistsInTheIdStoreWithoutAnEmailAddress()
    {
        $tempIdStoreUserInfo = [
            'employeenumber' => '10001',
            'displayname' => 'Person One',
            'username' => 'person_one',
            'firstname' => 'Person',
            'lastname' => 'One',
        ];

        $this->makeFakeIdStoreWithUser($tempIdStoreUserInfo);
    }

    /**
     * @Then the email subject contains :subject
     */
    public function theEmailSubjectContains($subject)
    {
        Assert::assertNotEmpty($this->notifier->findEmailBySubject($subject));
    }

    /**
     * @Then an email is not sent
     */
    public function anEmailIsNotSent()
    {
        Assert::assertEmpty($this->notifier->emailsSent);
    }

    /**
     * @Then an email with subject :subject is not sent
     */
    public function anEmailWithSubjectIsNotSent($subject)
    {
        Assert::assertEmpty($this->notifier->findEmailBySubject($subject));
    }

    /**
     * @Then a :subject email is sent to the user's HR contact
     */
    public function aEmailIsSentToTheUsersHrContact($subject)
    {
        $email = $this->notifier->findEmailBySubject($subject);
        Assert::assertNotEmpty($email, "No email was found with the subject: " . $subject);

        $user = $this->idStore->getActiveUser($this->tempEmployeeId);
        Assert::assertStringContainsString(
            $user->getHRContactEmail(),
            $email['to_address'],
            "Email was not sent to " . $user->getHRContactEmail()
        );
    }

    /**
     * @Given new user email notifications are :enabledOrDisabled
     * @throws Exception
     */
    public function newUserEmailNotificationsAre($enabledOrDisabled)
    {
        if ($enabledOrDisabled === "enabled") {
            $this->enableNewUserNotifications = true;
        } elseif ($enabledOrDisabled === "disabled") {
            $this->enableNewUserNotifications = false;
        } else {
            throw new Exception("invalid option '$enabledOrDisabled' for email new user email notifications");
        }
    }
}
