<?php

namespace Sil\Idp\IdSync\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Exception;
use PHPUnit\Framework\Assert;
use Psr\Log\LoggerInterface;
use Sil\Idp\IdSync\common\components\adapters\fakes\FakeIdBroker;
use Sil\Idp\IdSync\common\components\adapters\fakes\FakeIdStore;
use Sil\Idp\IdSync\common\components\notify\ConsoleNotifier;
use Sil\Idp\IdSync\common\interfaces\IdBrokerInterface;
use Sil\Idp\IdSync\common\interfaces\NotifierInterface;
use Sil\Idp\IdSync\common\models\User;
use Sil\Idp\IdSync\common\sync\Synchronizer;
use Sil\Psr3Adapters\Psr3EchoLogger;
use yii\helpers\Json;

/**
 * Defines application features from the specific context.
 */
class SyncContext implements Context
{
    /** @var Exception */
    private $exceptionThrown = null;

    /** @var IdBrokerInterface */
    private $idBroker;

    /** @var FakeIdStore */
    protected $idStore;

    /** @var LoggerInterface */
    protected $logger;

    /** @var NotifierInterface */
    protected $notifier;

    protected $tempEmployeeId;

    private $tempUserChanges = [];

    /** @var bool */
    protected $enableNewUserNotifications = false;

    public function __construct()
    {
        $this->logger = new Psr3EchoLogger();
        $this->notifier = new ConsoleNotifier();
    }

    /**
     * @param array $activeUsers
     * @return FakeIdStore
     */
    protected function getFakeIdStore(array $activeUsers = [])
    {
        return new FakeIdStore($activeUsers, $this->tempUserChanges);
    }

    /**
     * @Given a specific user exists in the ID Store (with an email address)
     */
    public function aSpecificUserExistsInTheIdStore()
    {
        $tempIdStoreUserInfo = [
            'employeenumber' => '10001',
            'displayname' => 'Person One',
            'username' => 'person_one',
            'firstname' => 'Person',
            'lastname' => 'One',
            'email' => 'person_one@example.com',
            'hrname' => 'HR Person',
            'hremail' => 'hr@example.com',
        ];

        $this->makeFakeIdStoreWithUser($tempIdStoreUserInfo);
    }

    protected function makeFakeIdStoreWithUser($user)
    {
        $this->tempEmployeeId = $user['employeenumber'];

        $this->idStore = $this->getFakeIdStore([
            $this->tempEmployeeId => $user,
        ]);
    }

    protected function createSynchronizer()
    {
        return new Synchronizer(
            $this->idStore,
            $this->idBroker,
            $this->logger,
            $this->notifier,
            Synchronizer::SAFETY_CUTOFF_DEFAULT,
            $this->enableNewUserNotifications
        );
    }

    /**
     * @Given the user exists in the ID Broker
     */
    public function theUserExistsInTheIdBroker()
    {
        $user = $this->idStore->getActiveUser($this->tempEmployeeId);

        $this->idBroker = new FakeIdBroker([
            $this->tempEmployeeId => $user->toArray(),
        ]);
    }

    /**
     * @When I get the user info from the ID Store and send it to the ID Broker
     */
    public function iGetTheUserInfoFromTheIdStoreAndSendItToTheIdBroker()
    {
        try {
            $synchronizer = $this->createSynchronizer();
            $synchronizer->syncUser($this->tempEmployeeId);
        } catch (Exception $e) {
            $this->exceptionThrown = $e;
        }
    }

    /**
     * @Then the user should exist in the ID Broker
     */
    public function theUserShouldExistInTheIdBroker()
    {
        Assert::assertNotNull($this->idBroker->getUser($this->tempEmployeeId));
    }

    /**
     * @Then the user info in the ID Broker and the ID Store should match
     */
    public function theUserInfoInTheIdBrokerAndTheIdStoreShouldMatch()
    {
        $userFromIdBroker = $this->idBroker->getUser($this->tempEmployeeId);
        $userInfoFromIdBroker = $userFromIdBroker->toArray();
        $userFromIdStore = $this->idStore->getActiveUser($this->tempEmployeeId);
        $userInfoFromIdStore = $userFromIdStore->toArray();

        foreach ($userInfoFromIdStore as $attribute => $value) {
            Assert::assertSame($value, $userInfoFromIdBroker[$attribute], sprintf(
                "Expected the ID Broker data...\n%s\n... to match the ID Store data...\n%s",
                var_export($userInfoFromIdBroker, true),
                var_export($userInfoFromIdStore, true)
            ));
        }
    }

    /**
     * @Given the user does not exist in the ID Broker
     */
    public function theUserDoesNotExistInTheIdBroker()
    {
        $this->idBroker = new FakeIdBroker();
    }

    /**
     * @Given the user does not exist in the ID Store
     */
    public function theUserDoesNotExistInTheIdStore()
    {
        $this->idStore = $this->getFakeIdStore([]);
    }

    /**
     * @When I learn the user does not exist in the ID Store and I tell the ID Broker
     */
    public function iLearnTheUserDoesNotExistInTheIdStoreAndITellTheIdBroker()
    {
        try {
            $synchronizer = $this->createSynchronizer();
            $synchronizer->syncUser($this->tempEmployeeId);
        } catch (Exception $e) {
            $this->exceptionThrown = $e;
        }
    }

    /**
     * @Then the user should be inactive in the ID Broker
     */
    public function theUserShouldBeInactiveInTheIdBroker()
    {
        $idBrokerUser = $this->idBroker->getUser($this->tempEmployeeId);
        Assert::assertSame('no', $idBrokerUser->getActive());
    }

    /**
     * @Then the user should not exist in the ID Broker
     */
    public function theUserShouldNotExistInTheIdBroker()
    {
        Assert::assertNull($this->idBroker->getUser($this->tempEmployeeId));
    }

    /**
     * @Given the user info in the ID Broker does not match the user info in the ID Store
     */
    public function theUserInfoInTheIdBrokerDoesNotMatchTheUserInfoInTheIdStore()
    {
        $userFromIdStore = $this->idStore->getActiveUser($this->tempEmployeeId);
        $this->idBroker->updateUser([
            'employee_id' => $userFromIdStore->getEmployeeId(),
            'display_name' => $userFromIdStore->getDisplayName() . ' Jr.',
        ]);
    }

    /**
     * @Given ONLY the following users are active in the ID Store:
     */
    public function onlyTheFollowingUsersAreActiveInTheIdStore(TableNode $table)
    {
        $idStoreActiveUsers = [];
        foreach ($table as $row) {
            // Ensure all required fields have a value.
            $row['email'] = $row['email'] ?? $row['username'] . '@example.com';

            // Note: This should use the ID Store field name.
            $idStoreActiveUsers[$row['employeenumber']] = $row;
        }
        $this->idStore = $this->getFakeIdStore($idStoreActiveUsers);
    }

    /**
     * @Given ONLY the following users exist in the ID Broker:
     */
    public function onlyTheFollowingUsersExistInTheIdBroker(TableNode $table)
    {
        $idBrokerUsers = [];
        foreach ($table as $row) {
            $idBrokerUsers[$row['employee_id']] = $row;
        }
        $this->idBroker = new FakeIdBroker($idBrokerUsers);
    }

    /**
     * @When I sync all the users from the ID Store to the ID Broker
     */
    public function iSyncAllTheUsersFromTheIdStoreToTheIdBroker()
    {
        try {
            $synchronizer = $this->createSynchronizer();
            $synchronizer->syncAll();
        } catch (Exception $e) {
            $this->exceptionThrown = $e;
        }
    }

    /**
     * @Then ONLY the following users should exist in the ID Broker:
     */
    public function onlyTheFollowingUsersShouldExistInTheIdBroker(TableNode $table)
    {
        $desiredFields = null;
        foreach ($table as $row) {
            $desiredFields = array_keys($row);
            break;
        }

        $actualUsers = $this->getIdBrokerUsers($desiredFields);
        Assert::assertJsonStringEqualsJsonString(
            Json::encode($table, JSON_PRETTY_PRINT),
            Json::encode(
                array_map(function ($user) {
                    return $user->toArray();
                }, $actualUsers),
                JSON_PRETTY_PRINT
            ),
            "---\nTo debug this, see if any errors were logged (above) in the test output.\n---"
        );
    }

    /**
     * @param array $desiredFields
     * @return User[]
     */
    protected function getIdBrokerUsers($desiredFields = null)
    {
        return $this->idBroker->listUsers($desiredFields);
    }

    /**
     * @Given a specific user exists in the ID Broker
     */
    public function aSpecificUserExistsInTheIdBroker()
    {
        $userInfo = [
            'employee_id' => '10001',
            'display_name' => 'Person One',
            'username' => 'person_one',
        ];
        $this->tempEmployeeId = $userInfo['employee_id'];
        $this->idBroker = new FakeIdBroker([
            $this->tempEmployeeId => $userInfo,
        ]);
    }

    /**
     * @Given a specific user does not exist in the ID Store
     */
    public function aSpecificUserDoesNotExistInTheIdStore()
    {
        $this->tempEmployeeId = '10005';
        $this->idStore = $this->getFakeIdStore([]);
    }

    /**
     * @Given the ID Store has the following log of when users were changed:
     */
    public function theIdStoreHasTheFollowingLogOfWhenUsersWereChanged(TableNode $table)
    {
        foreach ($table as $row) {
            $this->tempUserChanges[] = [
                'changedat' => $row['changedat'],
                // Note: This should use the ID Store field name.
                'employeenumber' => $row['employeenumber'],
            ];
        }
    }

    /**
     * @When I ask the ID Store for the list of users changed since :timestamp and sync them
     */
    public function iAskTheIdStoreForTheListOfUsersChangedSinceAndSyncThem($timestamp)
    {
        try {
            $synchronizer = $this->createSynchronizer();
            $synchronizer->syncUsersChangedSince($timestamp);
        } catch (Exception $e) {
            $this->exceptionThrown = $e;
        }
    }

    /**
     * @Given (only) :number users are active in the ID Store
     */
    public function usersAreActiveInTheIdStore($number)
    {
        $activeIdStoreUsers = [];
        for ($i = 1; $i <= $number; $i++) {
            $tempEmployeeId = 10000 + $i;
            $activeIdStoreUsers[$tempEmployeeId] = [
                'employeenumber' => (string)$tempEmployeeId,
                'displayname' => 'Person ' . $i,
                'username' => 'person_' . $i,
                'firstname' => 'Person',
                'lastname' => (string)$i,
                'email' => 'person_' . $i . '@example.com',
            ];
        }
        $this->idStore = $this->getFakeIdStore($activeIdStoreUsers);
    }

    /**
     * @Given user :number in the list from ID Store will be rejected by the ID Broker
     */
    public function userInTheListFromIdStoreWillBeRejectedByTheIdBroker($number)
    {
        /* @var $idStore FakeIdStore */
        $idStore = $this->idStore;
        if (! $idStore instanceof FakeIdStore) {
            Assert::fail('This test requires a FakeIdStore adapter.');
        }
        $employeeId = 10000 + $number;
        $idStore->changeFakeRecord($employeeId, [
            'email' => '',
        ]);
    }

    /**
     * @Then the ID Broker should now have :number active users.
     */
    public function theIdBrokerShouldNowHaveActiveUsers($number)
    {
        if (! is_numeric($number)) {
            Assert::fail('Not given a number.');
        }
        $numActiveUsers = 0;
        $idBrokerUsers = $this->idBroker->listUsers();
        foreach ($idBrokerUsers as $user) {
            if ($user->getActive() === 'yes') {
                $numActiveUsers += 1;
            }
        }
        Assert::assertSame((int)$number, $numActiveUsers, sprintf(
            'Did not expect all of these users to be active: [%s]',
            join(", ", $idBrokerUsers)
        ));
    }

    /**
     * @Given NO users exist in the ID Broker
     */
    public function noUsersExistInTheIdBroker()
    {
        $this->idBroker = new FakeIdBroker();
    }

    /**
     * @Given :number users are active in the ID Store and are inactive in the ID Broker
     */
    public function usersAreActiveInTheIdStoreAndAreInactiveInTheIdBroker($number)
    {
        $this->usersAreActiveInTheIdStore($number);

        $idBrokerUsers = [];
        foreach ($this->idStore->getAllActiveUsers() as $user) {
            $userInfo = $user->toArray();
            $userInfo[User::ACTIVE] = 'no';
            $idBrokerUsers[$user->getEmployeeId()] = $userInfo;
        }
        $this->idBroker = new FakeIdBroker($idBrokerUsers);
    }

    /**
     * @Then an exception should NOT have been thrown
     */
    public function anExceptionShouldNotHaveBeenThrown()
    {
        $possibleException = $this->exceptionThrown ?? new Exception();
        Assert::assertNotInstanceOf(Exception::class, $this->exceptionThrown, sprintf(
            'Unexpected exception (%s): %s',
            $possibleException->getCode(),
            $possibleException->getMessage()
        ));
    }

    /**
     * @Given the user has a manager email address in the ID Broker
     * @throws Exception
     */
    public function theUserHasAManagerEmailAddressInTheIdBroker()
    {
        $this->idBroker->updateUser([
            User::EMPLOYEE_ID => $this->tempEmployeeId,
            User::MANAGER_EMAIL => 'manager@example.com',
        ]);
    }

    /**
     * @Given the user does not have a manager email address in the ID Store
     */
    public function theUserDoesNotHaveAManagerEmailAddressInTheIdStore()
    {
        $this->idStore->changeFakeRecord($this->tempEmployeeId, [
            'supervisoremail' => null,
        ]);
    }

    /**
     * @Then the user should not have a manager email address in the ID Broker
     * @throws Exception
     */
    public function theUserShouldNotHaveAManagerEmailAddressInTheIdBroker()
    {
        $userFromIdBroker = $this->idBroker->getUser($this->tempEmployeeId);
        Assert::assertEmpty($userFromIdBroker->getManagerEmail());
    }

    /**
     * @Then we should have tried to update the ID Store's last-synced date for that user
     */
    public function weShouldHaveTriedToUpdateTheIdStoresLastSyncedDateForThatUser()
    {
        Assert::assertTrue(
            $this->idStore->wasSyncDateUpdatedFor($this->tempEmployeeId)
        );
    }

    /**
     * @Then we tried to update the last-synced date in the ID Store for:
     */
    public function weTriedToUpdateTheLastSyncedDateInTheIdStoreFor(TableNode $table)
    {
        foreach ($table as $row) {
            Assert::assertTrue(
                $this->idStore->wasSyncDateUpdatedFor($row['employeenumber']),
                'Failed to update sync date for ' . $row['employeenumber']
            );
        }
    }

    /**
     * @Then we tried to update the last-synced date in the ID Store for all :total users EXCEPT user :excluded
     */
    public function weTriedToUpdateTheLastSyncedDateInTheIdStoreForAllUsersExceptUser($total, $excluded)
    {
        for ($i = 1; $i <= $total; $i++) {
            $employeeId = 10000 + $i;
            if ($i == $excluded) {
                Assert::assertFalse(
                    $this->idStore->wasSyncDateUpdatedFor($employeeId),
                    'Incorrectly updated sync date for user ' . $i
                );
            } else {
                Assert::assertTrue(
                    $this->idStore->wasSyncDateUpdatedFor($employeeId),
                    'Failed to update sync date for user ' . $i
                );
            }
        }
    }

    /**
     * @Then we ONLY tried to update the last-synced date in the ID Store for the following:
     */
    public function weOnlyTriedToUpdateTheLastSyncedDateInTheIdStoreForTheFollowing(TableNode $table)
    {
        $expected = [];
        foreach ($table as $row) {
            $expected[] = $row['employeenumber'];
        }
        $actual = $this->idStore->listEmployeeIdsWithUpdatedSyncDate();

        sort($expected);
        sort($actual);

        Assert::assertEquals($expected, $actual);
    }
}
