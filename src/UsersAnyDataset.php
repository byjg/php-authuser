<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Repository\AnyDataset;
use ByJG\AnyDataset\Repository\IteratorFilter;
use ByJG\AnyDataset\Repository\IteratorInterface;
use ByJG\AnyDataset\Repository\SingleRow;

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
	 */
	public function addUser( $name, $userName, $email, $password )
	{
		if ($this->getByEmail($email) !== null)
		{
			return false;
		}
		if ($this->getByUsername($userName) !== null)
		{
			return false;
		}
		$this->_anyDataSet->appendRow();

		$this->_anyDataSet->addField( $this->getUserTable()->name, $name );
		$this->_anyDataSet->addField( $this->getUserTable()->username, preg_replace('/(?:([\w])|([\W]))/', '\1', strtolower($userName)));
		$this->_anyDataSet->addField( $this->getUserTable()->email, strtolower($email));
		$this->_anyDataSet->addField( $this->getUserTable()->password, $this->getPasswordHash($password) );
		$this->_anyDataSet->addField( $this->getUserTable()->admin, "" );
		$this->_anyDataSet->addField( $this->getUserTable()->created, date("Y-m-d H:i:s") );
		return true;
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
		$it = $this->_anyDataSet->getIterator($filter);
		if (!$it->hasNext())
		{
			return null;
		}
		else
		{
			return $it->moveNext();
		}
	}

	/**
	* Get the user based on his login.
	* Return SingleRow if user was found; null, otherwise
	*
	* @param string $username
	* @return SingleRow
	* */
	public function removeUserName( $username )
	{
		//anydataset.SingleRow
		$user = $this->getByUsername( $username );
		if  ($user !== null)
		{
			$this->_anyDataSet->removeRow( $user );
			return true;
		}
		else
		{
			return false;
		}
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
     */
	public function addProperty($userId, $propertyName, $value)
	{
		//anydataset.SingleRow
		$user = $this->getById( $userId );
		if ($user !== null)
		{
			if(!$this->hasProperty($user->getField($this->getUserTable()->id), $propertyName, $value ))
			{
				$user->addField($propertyName, $value);
                $this->save();
			}
			return true;
		}
		else
		{
			return false;
		}
	}

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return boolean
     */
	public function removeProperty( $userId, $propertyName, $value )
	{
		$user = $this->getById( $userId );
		if ($user !== null)
		{
			$user->removeFieldNameValue($propertyName, $value);
			$this->save();
			return true;
		}
		else
		{
			return false;
		}
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
		$it = $this->getIterator(null);
		while ($it->hasNext())
		{
			//anydataset.SingleRow
			$user = $it->moveNext();
			$this->removeProperty($user->getField($this->getUserTable()->username), $propertyName, $value);
		}
	}
}
