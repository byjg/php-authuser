<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Database\SQLHelper;
use ByJG\AnyDataset\Repository\AnyDataset;
use ByJG\AnyDataset\Repository\DBDataset;
use ByJG\AnyDataset\Repository\IteratorFilter;
use ByJG\AnyDataset\Repository\IteratorInterface;
use ByJG\AnyDataset\Repository\SingleRow;

class UsersDBDataset extends UsersBase
{
	/**
	* @var DBDataset
	*/
	protected $_db;

	protected $_sqlHelper;

	protected $_cacheUserWork = array();
    protected $_cacheUserOriginal = array();


	/**
	  * DBDataset constructor
	  */
	public function __construct($dataBase, UserTable $userTable = null, CustomTable $customTable = null)
	{
		$this->_db = new DBDataset($dataBase);
		$this->_sqlHelper = new SQLHelper($this->_db);
        $this->_userTable = $userTable;
        $this->_customTable = $customTable;
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
						$sql = $this->_sqlHelper->createSafeSQL("DELETE FROM @@Table "
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
						$this->_db->execSQL($sql, $param);

						// If new Value is_empty does not add
						if ($srMod->getField($fieldname) == "")
							continue;

						// Insert new Value
						$sql = $this->_sqlHelper->createSafeSQL("INSERT INTO @@Table "
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

						$this->_db->execSQL($sql, $param);

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

				$sql = $this->_sqlHelper->createSafeSQL($sql, array(
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

				$this->_db->execSQL($sql, $param);
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
		if ($this->getByEmail($email) != null)
		{
			return false;
		}
		if ($this->getByUsername($userName) != null)
		{
			return false;
		}
		$sql = " INSERT INTO @@Table (@@Name, @@Email, @@Username, @@Password, @@Created ) ";
		$sql .=" VALUES ([[name]], [[email]], [[username]], [[password]], [[created]] ) ";

		$sql = $this->_sqlHelper->createSafeSQL($sql, array(
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

		$this->_db->execSQL($sql, $param);

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
		return $this->_db->getIterator($sql, $param);
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
			$sql = $this->_sqlHelper->createSafeSQL($baseSql, array(
				'@@Table' => $this->getCustomTable()->table,
				'@@Username' => $this->getUserTable()->username
			));
			$this->_db->execSQL($sql, $param);
		}
		$sql = $this->_sqlHelper->createSafeSQL($baseSql, array(
			'@@Table' => $this->getUserTable()->table,
			'@@Username' => $this->getUserTable()->username
		));
		$this->_db->execSQL($sql, $param);
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
			$sql = $this->_sqlHelper->createSafeSQL($baseSql, array(
				'@@Table' => $this->getCustomTable()->table,
				'@@Id' => $this->getUserTable()->id
			));
			$this->_db->execSQL($sql, $param);
		}
		$sql = $this->_sqlHelper->createSafeSQL($baseSql, array(
			'@@Table' => $this->getUserTable()->table,
			'@@Id' => $this->getUserTable()->id
		));
		$this->_db->execSQL($sql, $param);
		return true;
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
		if ($user != null)
		{
			if(!$this->hasProperty($userId, $propertyName, $value))
			{
				$sql = " INSERT INTO @@Table ( @@Id, @@Name, @@Value ) ";
				$sql .=" VALUES ( [[id]], [[name]], [[value]] ) ";

				$sql = $this->_sqlHelper->createSafeSQL($sql, array(
					"@@Table" => $this->getCustomTable()->table,
					"@@Id" => $this->getUserTable()->id,
					"@@Name" => $this->getCustomTable()->name,
					"@@Value" => $this->getCustomTable()->value
				));

				$param = array();
				$param["id"] = $userId;
				$param["name"] = $propertyName;
				$param["value"] = $value;

				$this->_db->execSQL($sql, $param);
			}
			return true;
		}

        return false;
	}

	/**
	* Remove a specific site from user
	* Return True or false
	*
	* @param int $userId User Id
	* @param string $propertyName Property name
	* @param string $value Property value with a site
	* @return bool
	* */
	public function removeProperty( $userId, $propertyName, $value )
	{
		$user = $this->getById( $userId );
		if ($user != null)
		{
			$param = array();
			$param["id"] = $userId;
			$param["name"] = $propertyName;

			$sql =  "DELETE FROM @@Table ";
			$sql .= " WHERE @@Id = [[id]] AND @@Name = [[name]] ";
			if(!is_null($value))
			{
				$sql .= " AND @@Value = [[value]] ";
				$param["value"] = $value;
			}
			$sql = $this->_sqlHelper->createSafeSQL($sql, array(
					'@@Table' => $this->getCustomTable()->table,
					'@@Name' => $this->getCustomTable()->name,
					'@@Id' => $this->getUserTable()->id,
					'@@Value' => $this->getCustomTable()->value
				)
			);

			$this->_db->execSQL($sql, $param);
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
		$param = array();
		$param["name"] = $propertyName;
		$param["value"] = $value;

		$sql = "DELETE FROM @@Table WHERE @@Name = [[name]] AND @@Value = [[value]] ";

		$sql = $this->_sqlHelper->createSafeSQL($sql, array(
			"@@Table" => $this->getCustomTable()->table,
			"@@Name" => $this->getCustomTable()->name,
			"@@Value" => $this->getCustomTable()->value
		));

		$this->_db->execSQL($sql, $param);
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

		$sql = $this->_sqlHelper->createSafeSQL($sql, array(
			"@@Table" => $this->getCustomTable()->table,
			"@@Id" => $this->getUserTable()->id
		));

		$param = array('id' => $userId);
		$it = $this->_db->getIterator($sql, $param);
		while ($it->hasNext())
		{
			$sr = $it->moveNext();
			$userRow->addField($sr->getField($this->getCustomTable()->name), $sr->getField($this->getCustomTable()->value));
		}
	}
}
