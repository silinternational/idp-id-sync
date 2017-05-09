<?php
namespace Sil\Idp\IdSync\common\models;

use InvalidArgumentException;

class User
{
    const ACTIVE = 'active';
    const DISPLAY_NAME = 'display_name';
    const EMAIL = 'email';
    const EMPLOYEE_ID = 'employee_id';
    const FIRST_NAME = 'first_name';
    const LAST_NAME = 'last_name';
    const LOCKED = 'locked';
    const USERNAME = 'username';
    
    /** @var string */
    public $employeeId;
    
    /** @var string|null */
    public $firstName;
    
    /** @var string|null */
    public $lastName;
    
    /** @var string|null */
    public $displayName;
    
    /** @var string|null */
    public $username;
    
    /** @var string|null */
    public $email;
    
    /** @var string|null */
    public $active;
    
    /** @var string|null */
    public $locked;
    
    /**
     * Create a new User model from the given user info, which must be an
     * associative array with keys matching this class's constants and which
     * must contain at least an `employee_id`.
     *
     * @param array $userInfo The user info for populating this User object.
     */
    public function __construct($userInfo = [])
    {
        $this->employeeId = $userInfo[self::EMPLOYEE_ID];
        $this->firstName = $userInfo[self::FIRST_NAME] ?? null;
        $this->lastName = $userInfo[self::LAST_NAME] ?? null;
        $this->displayName = $userInfo[self::DISPLAY_NAME] ?? null;
        $this->username = $userInfo[self::USERNAME] ?? null;
        $this->email = $userInfo[self::EMAIL] ?? null;
        $this->active = $userInfo[self::ACTIVE] ?? null;
        $this->locked = $userInfo[self::LOCKED] ?? null;
        
        if (empty($this->employeeId)) {
            throw new InvalidArgumentException('Employee ID cannot be empty.', 1493733219);
        }
    }
    
    public function __toString()
    {
        return \json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
    
    /**
     * Get this User object's data as an associative array.
     *
     * NOTE: Only fields with non-null values will be included in the array.
     *
     * @return array
     */
    public function toArray()
    {
        $userInfo = [];
        $userInfo[self::EMPLOYEE_ID] = $this->employeeId;
        
        $possibleFields = [
            self::FIRST_NAME => $this->firstName,
            self::LAST_NAME => $this->lastName,
            self::DISPLAY_NAME => $this->displayName,
            self::USERNAME => $this->username,
            self::EMAIL => $this->email,
            self::ACTIVE => $this->active,
            self::LOCKED => $this->locked,
        ];

        foreach ($possibleFields as $fieldName => $value) {
            if ($value !== null) {
                $userInfo[$fieldName] = $value;
            }
        }
        
        return $userInfo;
    }
}