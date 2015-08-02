<?php

namespace ByJG\Authenticate;


/**
 * IUsersBase is a Interface to Store and Retrive USERS from an AnyDataset or a DBDataset structure.
 * @package xmlnuke
 */
interface UsersInterface
{

	/**
	 * @desc Save the current DataSet
	 */
	function save();

	/**
	 * @desc Add a new user
	 * @param string $name
	 * @param string $userName
	 * @param string $email
	 * @param string $password
	 * @return bool
	 */
	function addUser($name, $userName, $email, $password);

	/**
	 * @desc Get the user based on a filter
	 * @param IteratorFilter $filter
	 * @return anydataset.SingleRow if user was found; null, otherwise
	 */
	public function getUser($filter);

	/**
	 * @desc Get the user based on his email.
	 * @param emailEmail to find
	 * @return anydataset.SingleRow if user was found; null, otherwise
	 */
	function getUserEMail($email);

	/**
	 * @desc Get the user based on his login
	 * @param string $username
	 * @return anydataset.SingleRow if user was found; null, otherwise
	 */
	function getUserName($username);

	/**
	 * @desc Remove the user based on his login.
	 * @param string $username
	 * @return bool
	 */
	function removeUserName($username);

	/**
	 * @desc Get the SHA1 string from user password
	 * @param string $password
	 * @return string SHA1 encripted passwordstring
	 */
	function getPasswordHash($password);

	/**
	 * @desc Validate if the user and password exists in the file
	 * @param string $userName
	 * @param string $password
	 * @return anydataset.SingleRow if user was found; null, otherwise
	 */
	function validateUserName($userName, $password);

	/**
	 * @desc Check if the user have rights to edit specific site.
	 * @param string $userName
	 * @param string $propValue
	 * @param UserProperty $userProp
	 * @return True if have rights; false, otherwisebool
	 */
	public function checkUserProperty($userName, $propValue, $userProp);

	/**
	 * @desc Return all sites from a specific user
	 * @param string $userName
	 * @param UserProperty $userProp
	 * @return string[] String vector with all sites
	 */
	function returnUserProperty($userName, $userProp);

	/**
	 * @desc Add a specific site to user
	 * @param string $userName
	 * @param string $propValue
	 * @param UserProperty $userProp
	 * @return bool
	 */
	public function addPropertyValueToUser($userName, $propValue, $userProp);

	/**
	 * @desc Remove a specific site from user
	 * @param string $userName
	 * @param string $propValue
	 * @param UserProperty $userProp
	 * @return bool
	 */
	public function removePropertyValueFromUser($userName, $propValue, $userProp);

	/**
	 * @desc Remove a specific site from all users
	 * @param string $propValue
	 * @param UserProperty $userProp
	 * @return bool
	 */
	public function removePropertyValueFromAllUsers($propValue, $userProp);

	/**
	 * Enter description here...
	 *
	 * @param int $id
	 * @return SingleRow
	 */
	public function getUserId($id);

	/**
	 * Get all roles
	 *
	 * @param string $site
	 * @param string $role
	 * @return IteratorInterface
	 */
	public function getRolesIterator($site, $role = "");

	/**
	 * Add a public role into a site
	 *
	 * @param string $site
	 * @param string $role
	 */
	public function addRolePublic($site, $role);

	/**
	 * Edit a public role into a site. If new Value == null, remove the role)
	 *
	 * @param string $site
	 * @param string $role
	 * @param string $newValue
	 */
	public function editRolePublic($site, $role, $newValue = null);

	/**
	 *
	 * @param int $userId
	 * @return bool
	 */
	public function userIsAdmin($userId = "");

	/**
	 *
	 * @param string $role
	 * @param int $userId
	 * @return bool
	 */
	public function userHasRole($role, $userId = "");

	/**
	 * @return UserTable Description
	 */
	public function getUserTable();

	/**
	 * @return CustomTable Description
	 */
	public function getCustomTable();

	/**
	 * @return RolesTable Description
	 */
	public function getRolesTable();
}

