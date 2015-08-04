<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Enum\Relation;
use ByJG\AnyDataset\Repository\IteratorFilter;
use ByJG\AnyDataset\Repository\SingleRow;
use ByJG\Authenticate\CustomTable;
use ByJG\Authenticate\Exception\NotAuthenticatedException;
use ByJG\Authenticate\Exception\UserNotFoundException;
use ByJG\Authenticate\UserTable;

/**
 * Base implementation to search and handle users in XMLNuke.
 */
abstract class UsersBase implements UsersInterface
{
	/**
	 * @var UserTable
	 */
	protected  $_userTable;

	/**
	 * @var CustomTable
	 */
	protected $_customTable;

	/**
	 *
	 * @return UserTable
	 */
	public function getUserTable()
	{
		if ($this->_userTable == null)
		{
			$this->_userTable = new UserTable();
			$this->_userTable->table = "user";
			$this->_userTable->id = "userid";
			$this->_userTable->name = "name";
			$this->_userTable->email= "email";
			$this->_userTable->username = "username";
			$this->_userTable->password = "password";
			$this->_userTable->created = "created";
			$this->_userTable->admin = "admin";
		}
		return $this->_userTable;
	}

	/**
	 *
	 * @return CustomTable
	 */
	public function getCustomTable()
	{
		if ($this->_customTable == null)
		{
			$this->_customTable = new CustomTable();
			$this->_customTable->table = "custom";
			$this->_customTable->id = "customid";
			$this->_customTable->name = "name";
			$this->_customTable->value = "value";
			// Table "CUSTOM" must have [$this->_UserTable->Id = "userid"].
		}
		return $this->_customTable;
	}

	/**
	* Save the current UsersAnyDataset
	* */
	public function save()
	{
	}

	/**
	 * Add new user in database
	 *
	 * @param string $name
	 * @param string $userName
	 * @param string $email
	 * @param string $password
	 * @return bool
	 */
	public function addUser( $name, $userName, $email, $password )
	{
	}

	/**
	* Get the user based on a filter.
	* Return SingleRow if user was found; null, otherwise
	*
	* @param IteratorFilter $filter Filter to find user
	* @return SingleRow
	**/
	public function getUser( $filter )
	{
	}

	/**
	* Get the user based on his email.
	* Return SingleRow if user was found; null, otherwise
	*
	* @param string $email
	* @return SingleRow
	* */
	public function getByEmail( $email )
	{
		$filter = new IteratorFilter();
		$filter->addRelation($this->getUserTable()->email,  Relation::EQUAL , strtolower($email));
		return $this->getUser($filter);
	}

	/**
	* Get the user based on his login.
	* Return SingleRow if user was found; null, otherwise
	*
	* @param string $username
	* @return SingleRow
	* */
	public function getByUsername( $username )
	{
		$filter = new IteratorFilter();
		$filter->addRelation($this->getUserTable()->username,  Relation::EQUAL , strtolower($username) );
		return $this->getUser($filter);
	}

	/**
	* Get the user based on his id.
	* Return SingleRow if user was found; null, otherwise
	*
	* @param string $id
	* @return SingleRow
	* */
	public function getById( $id )
	{
		$filter = new IteratorFilter();
		$filter->addRelation($this->getUserTable()->id,  Relation::EQUAL , $id );
		return $this->getUser($filter);
	}

	/**
	* Remove the user based on his login.
	*
	* @param string $username
	* @return bool
	* */
	public function removeUserName( $username )
	{
	}

	/**
	* Get the SHA1 string from user password
	*
	* @param string $password Plain password
	* @return string
	* */
	public function getPasswordHash( $password )
	{
		return strtoupper(sha1($password));
	}

	/**
	* Validate if the user and password exists in the file
	* Return SingleRow if user exists; null, otherwise
	*
	* @param string $userName User login
	* @param string $password Plain text password
	* @return SingleRow
	* */
	public function isValidUser( $userName, $password )
	{
		$filter = new IteratorFilter();
		$filter->addRelation($this->getUserTable()->username,  Relation::EQUAL , strtolower($userName));
		$filter->addRelation($this->getUserTable()->password,  Relation::EQUAL , $this->getPasswordHash($password));
		return $this->getUser($filter);
	}

	/**
 	 * Check if the user have a property and it have a specific value.
	 * Return True if have rights; false, otherwise
	 *
	 * @param mixed $userId User identification
     * @param string $propertyName
	 * @param string $value Property value
	 * @return bool
	 *
     */
	public function hasProperty($userId, $propertyName, $value)
	{
		//anydataset.SingleRow
		$user = $this->getById( $userId );

		if ($user != null)
		{
			if ($this->isAdmin($userId))
			{
				return true;
			}
			else
			{
				$values = $user->getFieldArray($propertyName);
				return ($values != null ? in_array($value, $values) : false);
			}
		}
		else
		{
			return false;
		}
	}

	/**
	* Return all sites from a specific user
	* Return String vector with all sites
	*
	* @param string $userId User ID
	* @param string $propertyName Property name
	* @return array
	* */
	public function getProperty( $userId, $propertyName )
	{
		//anydataset.SingleRow
		$user = $this->getById( $userId );
		if ($user != null)
		{
			$values = $user->getFieldArray($propertyName);

			if ($this->isAdmin($userId))
			{
				return array("admin" => "admin");
			}

            return $values;
		}

        return null;
	}

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     */
	public function addProperty($userId, $propertyName, $value)
	{
	}

	/**
	* Remove a specific site from user
	* Return True or false
	*
	* @param int $userId User login
	* @param string $propertyName Property name
	* @param string $value Property value with a site
	* @return bool
	* */
	public function removeProperty( $userId, $propertyName, $value )
	{
	}

	/**
	* Remove a specific site from all users
	* Return True or false
	*
	* @param string $propertyName Property name
	* @param string $value Property value with a site
	* @return bool
	* */
	public function removeAllProperties($propertyName, $value)
	{
	}

	/**
	 *
	 * @param int $userId
	 * @return bool
	 */
	public function isAdmin($userId = null)
	{
		if (is_null($userId))
		{
			$currentUser = UserContext::getInstance()->userInfo();
			if ($currentUser === false) {
				throw new NotAuthenticatedException();
            }
            $userId = $currentUser[$this->getUserTable()->id];
		}
		
		$user = $this->getById($userId);

		if (!is_null($user)) {
            return (
                ($user->getField($this->getUserTable()->admin) == "yes") ||
                ($user->getField($this->getUserTable()->admin) == "y") ||
                ($user->getField($this->getUserTable()->admin) == "true") ||
                ($user->getField($this->getUserTable()->admin) == "t") ||
                ($user->getField($this->getUserTable()->admin) == "1")
            );
        }
		else {
			throw new UserNotFoundException("Cannot find the user");
        }
	}
}

