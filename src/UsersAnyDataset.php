<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Dataset\AnyDataset;
use ByJG\AnyDataset\Dataset\IteratorFilter;
use ByJG\AnyDataset\IteratorInterface;
use ByJG\AnyDataset\Dataset\Row;
use ByJG\Authenticate\Exception\UserExistsException;

class UsersAnyDataset extends UsersBase
{

    /**
     * Internal AnyDataset structure to store the Users
     * @var AnyDataset
     */
    protected $_anyDataSet;

    /**
     * Internal Users file name
     *
     * @var string
     */
    protected $_usersFile;

    /**
     * AnyDataset constructor
     * @param string $file
     * @param UserTable $userTable
     * @param CustomTable $customTable
     */
    public function __construct($file, UserTable $userTable = null, CustomTable $customTable = null)
    {
        $this->_usersFile = $file;
        $this->_anyDataSet = new AnyDataset($this->_usersFile);
        $this->_userTable = $userTable;
        $this->_customTable = $customTable;
    }

    /**
     * Save the current UsersAnyDataset
     */
    public function save()
    {
        $this->_anyDataSet->save($this->_usersFile);
    }

    /**
     * Add new user in database
     *
     * @param string $name
     * @param string $userName
     * @param string $email
     * @param string $password
     * @return bool
     * @throws UserExistsException
     */
    public function addUser($name, $userName, $email, $password)
    {
        if ($this->getByEmail($email) !== null) {
            throw new UserExistsException('Email already exists');
        }
        if ($this->getByUsername($userName) !== null) {
            throw new UserExistsException('Username already exists');
        }
        
        $userId = $this->generateUserId();
        $fixedUsername = preg_replace('/(?:([\w])|([\W]))/', '\1', strtolower($userName)); 
        if (is_null($userId)) {
            $userId = $fixedUsername;
        }
        
        $this->_anyDataSet->appendRow();

        $this->_anyDataSet->addField($this->getUserTable()->id, $userId);
        $this->_anyDataSet->addField($this->getUserTable()->username, $fixedUsername);
        $this->_anyDataSet->addField($this->getUserTable()->name, $name);
        $this->_anyDataSet->addField($this->getUserTable()->email, strtolower($email));
        $this->_anyDataSet->addField($this->getUserTable()->password, $this->getPasswordHash($password));
        $this->_anyDataSet->addField($this->getUserTable()->admin, "");
        $this->_anyDataSet->addField($this->getUserTable()->created, date("Y-m-d H:i:s"));

        return true;
    }

    /**
     * Get the user based on a filter.
     * Return Row if user was found; null, otherwise
     *
     * @param IteratorFilter $filter Filter to find user
     * @return Row
     * */
    public function getUser($filter)
    {
        $it = $this->_anyDataSet->getIterator($filter);
        if (!$it->hasNext()) {
            return null;
        }

        return $it->moveNext();
    }

    /**
     * Get the user based on his login.
     * Return Row if user was found; null, otherwise
     *
     * @param string $username
     * @return boolean
     * */
    public function removeUserName($username)
    {
        //anydataset.Row
        $user = $this->getByUsername($username);
        if (!empty($user)) {
            $this->_anyDataSet->removeRow($user);
            return true;
        }

        return false;
    }

    /**
     * Get an Iterator based on a filter
     *
     * @param IteratorFilter $filter
     * @return IteratorInterface
     */
    public function getIterator($filter = null)
    {
        return $this->_anyDataSet->getIterator($filter);
    }

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return boolean
     */
    public function addProperty($userId, $propertyName, $value)
    {
        //anydataset.Row
        $user = $this->getById($userId);
        if ($user !== null) {
            if (!$this->hasProperty($user->get($this->getUserTable()->id), $propertyName, $value)) {
                $user->addField($propertyName, $value);
                $this->save();
            }
            return true;
        }

        return false;
    }

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return boolean
     */
    public function removeProperty($userId, $propertyName, $value)
    {
        $user = $this->getById($userId);
        if (!empty($user)) {
            $user->removeValue($propertyName, $value);
            $this->save();
            return true;
        }

        return false;
    }

    /**
     * Remove a specific site from all users
     * Return True or false
     *
     * @param string $propertyName Property name
     * @param string $value Property value with a site
     * @return bool|void
     */
    public function removeAllProperties($propertyName, $value)
    {
        $it = $this->getIterator(null);
        while ($it->hasNext()) {
            //anydataset.Row
            $user = $it->moveNext();
            $this->removeProperty($user->get($this->getUserTable()->username), $propertyName, $value);
        }
    }
}
