<?php

namespace ByJG\Authenticate;

/**
 * Authentication constants.
 */
define('AUTH_PASSWORD_NOT_CACHED', 'not cached'); // String used in password field when password is not stored.


use ByJG\Authenticate\Exception\NotImplementedException;
use ByJG\Authenticate\UserProperty;
use ErrorException;

class UsersMoodleDataset extends UsersDBDataset
{

	/**
	 * @var string
	 */
	protected $_siteSalt = "";

	/**
	  * DBDataset constructor
	  */
	public function __construct($dataBase, $siteSalt = "")
	{
		parent::__construct($dataBase);

		$this->_siteSalt = $siteSalt;
	}

	/**
	 *
	 * Save the current UsersAnyDataset
	 */
	public function save()
	{
		throw new NotImplementedException('Save user is not implemented');
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
		throw new NotImplementedException('Add new user is not implemented');
	}

	protected function password_is_legacy_hash($password)
	{
		return (bool) preg_match('/^[0-9a-f]{32}$/', $password);
	}

	public function validateUserName($userName, $password)
	{
		$user = $this->getUserName($userName);
		if ($user == null)
        {
            return null;
        }

        $savedPassword = $user->getField($this->getUserTable()->password);
		$validatedUser = null;

		if ($savedPassword === AUTH_PASSWORD_NOT_CACHED)
        {
            return null;
        }

        if ($this->password_is_legacy_hash($savedPassword))
		{
			if ($savedPassword === md5($password . $this->_siteSalt)
				|| $savedPassword === md5($password)
				|| $savedPassword === md5(addslashes($password) . $this->_siteSalt)
				|| $savedPassword === md5(addslashes($password))
				)
            {
				$validatedUser = $user;
            }
		}
		else
		{
			if (!function_exists('crypt'))
			{
				throw new ErrorException("Crypt must be loaded for password_verify to function");
			}

			$ret = crypt($password, $savedPassword);
			if (!is_string($ret) || strlen($ret) != strlen($savedPassword) || strlen($ret) <= 13)
			{
				return null;
			}

			$status = 0;
			for ($i = 0; $i < strlen($ret); $i++) {
				$status |= (ord($ret[$i]) ^ ord($savedPassword[$i]));
			}

			if ($status === 0)
            {
                $validatedUser = $user;
            }
        }

		return $validatedUser;
	}

	public function getUser($filter)
	{
		$user = parent::getUser($filter);

		if ($user != null)
		{
			// Get the user's roles from moodle
			$sqlRoles = 'SELECT shortname
						 FROM
							mdl_role AS r
						INNER JOIN
							mdl_role_assignments AS ra
								ON ra.roleid = r.id
						INNER JOIN mdl_user  AS u
								ON u.id = ra.userid
						WHERE userid = [[id]]
						group by shortname';
			$param = array("id" => $user->getField($this->getUserTable()->id));
			$it = $this->_DB->getIterator($sqlRoles, $param);
			foreach ($it as $sr)
			{
				$user->AddField("roles", $sr->getField('shortname'));
			}

			// Find the moodle site admin (super user)
			$user->setField($this->getUserTable()->admin, 'no');
			$sqlAdmin = "select value from mdl_config where name = 'siteadmins'";
			$it = $this->_DB->getIterator($sqlAdmin);
			if ($it->hasNext())
			{
				$sr = $it->moveNext();
				$siteAdmin = ',' . $sr->getField('value') . ',';
				$isAdmin = (strpos($siteAdmin, ",{$user->getField($this->getUserTable()->id)},") !== false);
				$user->setField($this->getUserTable()->admin, $isAdmin ? 'yes' : 'no');
			}
		}

		return $user;
	}


	/**
	* Remove the user based on his user login.
	*
	* @param string $login
	* @return bool
	* */
	public function removeUserName( $login )
	{
		throw new NotImplementedException('Remove user is not implemented');
	}

	/**
	* Remove the user based on his user id.
	*
	* @param int $userId
	* @return bool
	* */
	public function removeUserById( $userId )
	{
		throw new NotImplementedException('Remove user by Id is not implemented');
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
		throw new NotImplementedException('Remove property value from all users is not implemented');
	}


	/**
	 * Add a public role into a site
	 *
	 * @param string $site
	 * @param string $role
	 */
	public function addRolePublic($site, $role)
	{
		throw new NotImplementedException('Add role public is not implemented');
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
		throw new NotImplementedException('Edit role public is not implemented');
	}

	public function getUserTable()
	{
		if ($this->_userTable == null)
		{
			parent::getUserTable();
			$this->_userTable->table = "mdl_user";
			$this->_userTable->id = "id";
			$this->_userTable->name = "concat(firstname, ' ', lastname)";  // This disable update data
			$this->_userTable->email= "email";
			$this->_userTable->username = "username";
			$this->_userTable->password = "password";
			$this->_userTable->created = "created";
			$this->_userTable->admin = "auth";							// This disable update data
		}
		return $this->_userTable;
	}

	public function getCustomTable()
	{
		if ($this->_customTable == null)
		{
			parent::getCustomTable();
			$this->_customTable->table = "mdl_user_info_data";
			$this->_customTable->id = "id";
			$this->_customTable->name = "fieldid";
			$this->_customTable->value = "data";
		}
		return $this->_customTable;
	}

	public function getRolesTable()
	{
		if ($this->_rolesTable == null)
		{
			parent::getRolesTable();
			$this->_rolesTable->table = "mdl_role";
			$this->_rolesTable->site  = "'_all'";
			$this->_rolesTable->role = "shortname";
		}
		return $this->_rolesTable;
	}
}
