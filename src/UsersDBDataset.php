<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Database\SQLHelper;
use ByJG\AnyDataset\Exception\DatasetException;
use ByJG\AnyDataset\Repository\AnyDataset;
use ByJG\AnyDataset\Repository\DBDataset;
use ByJG\AnyDataset\Repository\IteratorFilter;
use ByJG\AnyDataset\Repository\IteratorInterface;
use ByJG\AnyDataset\Repository\SingleRow;
use ByJG\Authenticate\UserProperty;

class UsersDBDataset extends UsersBase
{
	/**
	* @var DBDataset
	*/
	protected $_DB;

	protected $_SQLHelper;

	protected $_cacheUserWork = array();
    protected $_cacheUserOriginal = array();


	/**
	  * DBDataset constructor
	  */
	public function __construct($dataBase)
	{
		$this->_DB = new DBDataset($dataBase);
		$this->_SQLHelper = new SQLHelper($this->_DB);
	}

	/**
	 *
	 * Save the current UsersAnyDataset
	 */
	public function save()
	{
        foreach ($this->_cacheUserOriginal as $key=>$value)
        {
            $srOri = $this->_cacheUserOriginal[$key];
            $srMod = $this->_cacheUserWork[$key];

			// Look for changes
            $changeUser = false;
            foreach ($srMod->getFieldNames() as $keyfld=>$fieldname)
            {
				$userField = ($fieldname == $this->getUserTable()->name
					|| $fieldname == $this->getUserTable()->email
					|| $fieldname == $this->getUserTable()->username
					|| $fieldname == $this->getUserTable()->password
					|| $fieldname == $this->getUserTable()->created
					|| $fieldname == $this->getUserTable()->admin
					|| $fieldname == $this->getUserTable()->id
				);
                if ($srOri->getField($fieldname) != $srMod->getField($fieldname))
                {
					// This change is in the Users table or is a Custom property?
					if ($userField)
					{
						$changeUser = true;
					}
					else
					{
						// Erase Old Custom Properties
						$sql = $this->_SQLHelper->createSafeSQL("DELETE FROM @@Table "
								. " WHERE @@Id = [[id]] "
								. "   AND @@Name = [[name]] "
								. "   AND @@Value = [[value]] ",
								array(
									"@@Table" => $this->getCustomTable()->table,
									"@@Id" => $this->getUserTable()->id,
									"@@Name" => $this->getCustomTable()->name,
									"@@Value" => $this->getCustomTable()->value
								)
						);

						$param = array(
							'id' => $srMod->getField($this->getUserTable()->id),
							'name' => $fieldname,
							'value' => $srOri->getField($fieldname)
						);
						$this->_DB->execSQL($sql, $param);

						// If new Value is_empty does not add
						if ($srMod->getField($fieldname) == "")
							continue;

						// Insert new Value
						$sql = $this->_SQLHelper->createSafeSQL("INSERT INTO @@Table "
								. "( @@Id, @@Name, @@Value ) "
								. " VALUES ( [[id]], [[name]], [[value]] ) ",
								array(
									"@@Table" => $this->getCustomTable()->table,
									"@@Id" => $this->getUserTable()->id,
									"@@Name" => $this->getCustomTable()->name,
									"@@Value" => $this->getCustomTable()->value
								)
						);

						$param = array();
						$param["id"] = $srMod->getField($this->getUserTable()->id);
						$param["name"] = $fieldname;
						$param["value"] = $srMod->getField($fieldname);

						$this->_DB->execSQL($sql, $param);

					}
                }
            }

            if($changeUser)
			{
				$sql = "UPDATE @@Table ";
				$sql .= " SET @@Name  = [[name]] ";
				$sql .= ", @@Email = [[email]] ";
				$sql .= ", @@Username = [[username]] ";
				$sql .= ", @@Password = [[password]] ";
				$sql .= ", @@Created = [[created]] ";
				$sql .= ", @@Admin = [[admin]] ";
				$sql .= " WHERE @@Id = [[id]]";

				$sql = $this->_SQLHelper->createSafeSQL($sql, array(
						'@@Table' => $this->getUserTable()->table,
						'@@Name' => $this->getUserTable()->name,
						'@@Email' => $this->getUserTable()->email,
						'@@Username' => $this->getUserTable()->username,
						'@@Password' => $this->getUserTable()->password,
						'@@Created' => $this->getUserTable()->created,
						'@@Admin' => $this->getUserTable()->admin,
						'@@Id' => $this->getUserTable()->id
					)	
				);

				$param = array();
				$param['name'] = $srMod->getField($this->getUserTable()->name);
				$param['email'] = $srMod->getField($this->getUserTable()->email);
				$param['username'] = $srMod->getField($this->getUserTable()->username);
				$param['password'] = $srMod->getField($this->getUserTable()->password);
				$param['created'] = $srMod->getField($this->getUserTable()->created);
				$param['admin'] = $srMod->getField($this->getUserTable()->admin);
				$param['id'] = $srMod->getField($this->getUserTable()->id);

				$this->_DB->execSQL($sql, $param);
			}
        }
        $this->_cacheUserOriginal = array();
        $this->_cacheUserWork = array();
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
		$sql = " INSERT INTO @@Table (@@Name, @@Email, @@Username, @@Password, @@Created ) ";
		$sql .=" VALUES ([[name]], [[email]], [[username]], [[password]], [[created]] ) ";

		$sql = $this->_SQLHelper->createSafeSQL($sql, array(
				'@@Table' => $this->getUserTable()->table,
				'@@Name' => $this->getUserTable()->name,
				'@@Email' => $this->getUserTable()->email,
				'@@Username' => $this->getUserTable()->username,
				'@@Password' => $this->getUserTable()->password,
				'@@Created' => $this->getUserTable()->created,
			)
		);

		$param = array();
		$param['name'] = $name;
		$param['email'] = strtolower($email);
		$param['username'] = preg_replace('/(?:([\w])|([\W]))/', '\1', strtolower($userName));
		$param['password'] = $this->getPasswordHash($password);
		$param['created'] = date("Y-m-d H:i:s");

		$this->_DB->execSQL($sql, $param);

		return true;
	}

	/**
	 * Get the users database information based on a filter.
	 *
	 * @param IteratorFilter $filter Filter to find user
	 * @param array $param
	 * @return IteratorInterface
	 */
	public function getIterator(IteratorFilter $filter = null, $param = array())
	{
		$sql = "";
		$param = array();
		if (is_null($filter))
		{
			$filter = new IteratorFilter();
		}
		$sql = $filter->getSql($this->getUserTable()->table, $param);
		return $this->_DB->getIterator($sql, $param);
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
		$it = $this->getIterator($filter);
		if ($it->hasNext())
		{
			// Get the Requested User
			$sr = $it->moveNext();
			$this->getCustomFields($sr);

                // Clone the User Properties
                $anyOri = new AnyDataset();
                $anyOri->appendRow();
                foreach ($sr->getFieldNames() as $key=>$fieldName)
                {
                    $anyOri->addField($fieldName, $sr->getField($fieldName));
                }
                $itOri = $anyOri->getIterator();
                $srOri = $itOri->moveNext();

                // Store and return to the user the proper single row.
                $this->_cacheUserOriginal[$sr->getField($this->getUserTable()->id)] = $srOri;
                $this->_cacheUserWork[$sr->getField($this->getUserTable()->id)] = $sr;
                return $this->_cacheUserWork[$sr->getField($this->getUserTable()->id)];
		}
		else
		{
			return null;
		}
	}

	/**
	* Remove the user based on his user login.
	*
	* @param string $login
	* @return bool
	* */
	public function removeUserName( $login )
	{
		$baseSql = "DELETE FROM @@Table WHERE @@Username = [[login]] ";
		$param = array( "login" => $login );
		if ($this->getCustomTable()->table != "")
		{
			$sql = $this->_SQLHelper->createSafeSQL($baseSql, array(
				'@@Table' => $this->getCustomTable()->table,
				'@@Username' => $this->getUserTable()->username
			));
			$this->_DB->execSQL($sql, $param);
		}
		$sql = $this->_SQLHelper->createSafeSQL($baseSql, array(
			'@@Table' => $this->getUserTable()->table,
			'@@Username' => $this->getUserTable()->username
		));
		$this->_DB->execSQL($sql, $param);
		return true;
	}

	/**
	* Remove the user based on his user id.
	*
	* @param int $userId
	* @return bool
	* */
	public function removeUserById( $userId )
	{
		$baseSql = "DELETE FROM @@Table WHERE @@Id = [[login]] ";
		$param = array("id"=>$userId);
		if ($this->getCustomTable()->table != "")
		{
			$sql = $this->_SQLHelper->createSafeSQL($baseSql, array(
				'@@Table' => $this->getCustomTable()->table,
				'@@Id' => $this->getUserTable()->id
			));
			$this->_DB->execSQL($sql, $param);
		}
		$sql = $this->_SQLHelper->createSafeSQL($baseSql, array(
			'@@Table' => $this->getUserTable()->table,
			'@@Id' => $this->getUserTable()->id
		));
		$this->_DB->execSQL($sql, $param);
		return true;
	}

	/**
	* Add a specific site to user
	* Return True or false
	*
	* @param int $userId User Id
	* @param string $propValue Property value with a site
	* @param UserProperty $userProp Property name
	* @return bool
	* */
	public function addPropertyValueToUser( $userId, $propValue, $userProp )
	{
		//anydataset.SingleRow
		$user = $this->getUserId( $userId );
		if ($user != null)
		{
			if(!$this->checkUserProperty($userId, $propValue, $userProp))
			{
				$sql = " INSERT INTO @@Table ( @@Id, @@Name, @@Value ) ";
				$sql .=" VALUES ( [[id]], [[name]], [[value]] ) ";

				$sql = $this->_SQLHelper->createSafeSQL($sql, array(
					"@@Table" => $this->getCustomTable()->table,
					"@@Id" => $this->getUserTable()->id,
					"@@Name" => $this->getCustomTable()->name,
					"@@Value" => $this->getCustomTable()->value
				));

				$param = array();
				$param["id"] = $userId;
				$param["name"] = UserProperty::getPropertyNodeName($userProp);
				$param["value"] = $propValue;

				$this->_DB->execSQL($sql, $param);
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
	* @param int $userId User Id
	* @param string $propValue Property value with a site
	* @param UserProperty $userProp Property name
	* @return bool
	* */
	public function removePropertyValueFromUser( $userId, $propValue, $userProp )
	{
		$user = $this->getUserId( $userId );
		if ($user != null)
		{
			$param = array();
			$param["id"] = $userId;
			$param["name"] = UserProperty::getPropertyNodeName($userProp);

			$sql =  "DELETE FROM @@Table ";
			$sql .= " WHERE @@Id = [[id]] AND @@Name = [[name]] ";
			if(!is_null($propValue))
			{
				$sql .= " AND @@Value = [[value]] ";
				$param["value"] = $propValue;
			}
			$sql = $this->_SQLHelper->createSafeSQL($sql, array(
					'@@Table' => $this->getCustomTable()->table,
					'@@Name' => $this->getCustomTable()->name,
					'@@Id' => $this->getUserTable()->id,
					'@@Value' => $this->getCustomTable()->value
				)
			);

			$this->_DB->execSQL($sql, $param);
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
		$param = array();
		$param["name"] = UserProperty::getPropertyNodeName($userProp);
		$param["value"] = $propValue;

		$sql = "DELETE FROM @@Table WHERE @@Name = [[name]] AND @@Value = [[value]] ";

		$sql = $this->_SQLHelper->createSafeSQL($sql, array(
			"@@Table" => $this->getCustomTable()->table,
			"@@Name" => $this->getCustomTable()->name,
			"@@Value" => $this->getCustomTable()->value
		));

		$this->_DB->execSQL($sql, $param);
	}


	/**
	 * Return all custom's fields from this user
	 *
	 * @param unknown_type $userRow
	 * @return unknown
	 */
	protected function getCustomFields($userRow)
	{
		if ($this->getCustomTable()->table == "")
		{
			return null;
		}

		$userId = $userRow->getField($this->getUserTable()->id);
		$sql = "select * from @@Table where @@Id = [[id]]";

		$sql = $this->_SQLHelper->createSafeSQL($sql, array(
			"@@Table" => $this->getCustomTable()->table,
			"@@Id" => $this->getUserTable()->id
		));
				
		$param = array('id' => $userId);
		$it = $this->_DB->getIterator($sql, $param);
		while ($it->hasNext())
		{
			$sr = $it->moveNext();
			$userRow->AddField($sr->getField($this->getCustomTable()->name), $sr->getField($this->getCustomTable()->value));
		}
	}

	/**
	 * Get all roles
	 *
	 * @param string $site
	 * @param string $role
	 * @return IteratorInterface
	 */
	public function getRolesIterator($site = "_all", $role = "")
	{
		$param = array();
		$param["site"] = $site;

		$sql = "select * from @@Table " .
			" where (@@Site = [[site]] or @@Site = '_all' ) ";

		if ($role != "")
		{
			$sql .= " and  @@Role = [[role]] ";
			$param["role"] = $role;
		}

		$sql = $this->_SQLHelper->createSafeSQL($sql, array(
			"@@Table" => $this->getRolesTable()->table,
			"@@Site" => $this->getRolesTable()->site,
			"@@Role" => $this->getRolesTable()->role
		));

		return $this->_DB->getIterator($sql, $param);
	}


	/**
	 * Add a public role into a site
	 *
	 * @param string $site
	 * @param string $role
	 */
	public function addRolePublic($site, $role)
	{
		$it = $this->getRolesIterator($site, $role);
		if ($it->hasNext())
		{
			throw new DatasetException("Role exists.");
		}

		$sql = "insert into @@Table ( @@Site, @@Role ) values ( [[site]], [[role]] )";

		$sql = $this->_SQLHelper->createSafeSQL($sql, array(
			"@@Table" => $this->getRolesTable()->table,
			"@@Site" => $this->getRolesTable()->site
		));

		$param = array("site"=>$site, "role"=>$role);

		$this->_DB->execSQL($sql, $param);
	}

	/**
	 * Edit a public role into a site. If new Value == null, remove the role)
	 *
	 * @param string $site
	 * @param string $role
	 * @param string $newValue
	 */
	public function editRolePublic($site, $role, $newValue = null)
	{
		if (!is_null($newValue))
		{
			$this->addRolePublic($site, $newValue);
		}

		$sql = "DELETE FROM @@Table " .
			" WHERE @@Site = [[site]] " .
			" AND @@Role = [[role]] ";

		$sql = $this->_SQLHelper->createSafeSQL($sql, array(
			"@@Table" => $this->getRolesTable()->table,
			"@@Site" => $this->getRolesTable()->site,
			"@@Role" => $this->getRolesTable()->role
		));

		$param = array("site"=>$site, "role"=>$role);

		$this->_DB->execSQL($sql, $param);
	}

	public function getUserTable()
	{
		if ($this->_userTable == null)
		{
			parent::getUserTable();
			$this->_userTable->table = "xmlnuke_users";
		}
		return $this->_userTable;
	}

	public function getCustomTable()
	{
		if ($this->_customTable == null)
		{
			parent::getCustomTable();
			$this->_customTable->table = "xmlnuke_custom";
		}
		return $this->_customTable;
	}

	public function getRolesTable()
	{
		if ($this->_rolesTable == null)
		{
			parent::getRolesTable();
			$this->_rolesTable->table = "xmlnuke_roles";
		}
		return $this->_rolesTable;
	}

}
