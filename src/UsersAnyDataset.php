<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Enum\Relation;
use ByJG\AnyDataset\Exception\DatasetException;
use ByJG\AnyDataset\Repository\AnyDataset;
use ByJG\AnyDataset\Repository\IteratorFilter;
use ByJG\AnyDataset\Repository\IteratorInterface;
use ByJG\AnyDataset\Repository\SingleRow;
use ByJG\Authenticate\UserProperty;

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
	public function __construct($file)
	{
		$this->_usersFile = $file;
		$this->_anyDataSet = new AnyDataset($this->_usersFile);
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
		if ($this->getUserEMail($email) != null)
		{
			return false;
		}
		if ($this->getUserName($userName) != null)
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
		$user = $this->getUserName( $username );
		if  ($user != null)
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
	* Add a specific site to user
	* Return True or false
	*
	* @param string $userName User login
	* @param string $propValue Property value with a site
	* @param UserProperty $userProp Property name
	* @return bool
	* */
	public function addPropertyValueToUser( $userName, $propValue, $userProp )
	{
		//anydataset.SingleRow
		$user = $this->getUserName( $userName );
		if ($user != null)
		{
			if(!$this->checkUserProperty($user->getField($this->getUserTable()->id), $propValue, $userProp ))
			{
				$user->AddField(UserProperty::getPropertyNodeName($userProp), $propValue );
			}
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Remove a specific site from user
	* Return True or false
	*
	* @param string $userName User login
	* @param string $propValue Property value with a site
	* @param UserProperty $userProp Property name
	* @return bool
	* */
	public function removePropertyValueFromUser( $userName, $propValue, $userProp )
	{
		$user = $this->getUserName( $userName );
		if ($user != null)
		{
			$user->removeFieldNameValue(UserProperty::getPropertyNodeName($userProp), $propValue);
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
	* @param string $propValue Property value with a site
	* @param UserProperty $userProp Property name
	* @return bool
	* */
	public function removePropertyValueFromAllUsers($propValue, $userProp)
	{
		$it = $this->getIterator(null);
		while ($it->hasNext())
		{
			//anydataset.SingleRow
			$user = $it->moveNext();
			$this->removePropertyValueFromUser($user->getField($this->getUserTable()->username), $propValue, $userProp);
		}
	}

	/**
	 * Enter description here...
	 *
	 * @return AnyDataset
	 */
	protected function getRoleAnydataSet()
	{
		$fileRole = basename($this->_usersFile) . '.roles.' . pathinfo($this->_usersFile, PATHINFO_EXTENSION);
		$roleDataSet = new AnyDataset($fileRole);
		return $roleDataSet;
	}

	/**
	 * Get all roles
	 *
	 * @param string $site
	 * @param string $role
	 * @return IteratorInterface
	 */
	public function getRolesIterator($site, $role = "")
	{
		$itf = new IteratorFilter();
		if ($role != "")
		{
			$itf->addRelation($this->getRolesTable()->role,  Relation::EQUAL, $role);
		}
		$itf->startGroup();
		$itf->addRelation($this->getRolesTable()->site,  Relation::EQUAL, $site);
		$itf->addRelationOr($this->getRolesTable()->site,  Relation::EQUAL, "_all");
		$itf->endGroup();

		$roleDataSet = $this->getRoleAnydataSet();
		return $roleDataSet->getIterator($itf);
	}

	/**
	 * Add a public role into a site
	 *
	 * @param string $site
	 * @param string $role
	 */
	public function addRolePublic($site, $role)
	{
		$dataset = $this->getRoleAnydataSet();
		$dataFilter = new IteratorFilter();
		$dataFilter->addRelation($this->getRolesTable()->site,  Relation::EQUAL, $site);
		$iterator = $dataset->getIterator($dataFilter);
		if(!$iterator->hasNext())
		{
			$dataset->appendRow();
			$dataset->addField($this->getRolesTable()->site, $site);
			$dataset->addField($this->getRolesTable()->role, $role);
		}
		else
		{
			$dataFilter->addRelation($this->getRolesTable()->role,  Relation::EQUAL, $role);
			$iteratorCheckDupRole = $dataset->getIterator($dataFilter);
			if (!$iteratorCheckDupRole->hasNext())
			{
				$sr = $iterator->moveNext();
				$sr->AddField($this->getRolesTable()->role, $role);
			}
			else
			{
				throw new DatasetException("Role exists");
			}
		}
		$dataset->save();
	}

	/**
	 * Edit a public role into a site. If new Value == null, remove the role)
	 *
	 * @param string $site
	 * @param string $role
	 * @param string $newValue Null remove the value
	 */
	public function editRolePublic($site, $role, $newValue = null)
	{
		if ($newValue != null)
		{
			$this->addRolePublic($site, $newValue);
		}

		$roleDataSet = $this->getRoleAnydataSet();
		$dataFilter = new IteratorFilter();
		$dataFilter->addRelation($this->getRolesTable()->site,  Relation::EQUAL, $site);
		$dataFilter->addRelation($this->getRolesTable()->role,  Relation::EQUAL, $role);
		$it = $roleDataSet->getIterator($dataFilter);
		if ($it->hasNext()) {
			$sr = $it->moveNext();
			$sr->removeFieldName($role);
		}
		$roleDataSet->save();
	}

	public function getUserTable()
	{
		if ($this->_userTable == null)
		{
			parent::getUserTable();
			$this->_userTable->id = $this->_userTable->username;
		}
		return $this->_userTable;
	}

}
