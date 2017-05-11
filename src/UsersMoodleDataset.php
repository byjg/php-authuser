<?php

namespace ByJG\Authenticate;

/**
 * Authentication constants.
 */
define('AUTH_PASSWORD_NOT_CACHED', 'not cached'); // String used in password field when password is not stored.

use ByJG\Authenticate\Definition\UserPropertiesDefinition;
use ByJG\Authenticate\Definition\UserDefinition;
use ByJG\Authenticate\Exception\NotImplementedException;
use ByJG\Authenticate\Model\UserPropertiesModel;
use ByJG\Authenticate\Model\UserModel;
use ErrorException;

class UsersMoodleDataset extends UsersDBDataset
{

    /**
     * @var string
     */
    protected $_siteSalt = "";

    /**
     * DBDataset constructor
     *
     * @param string $connectionString
     * @param string $siteSalt
     */
    public function __construct($connectionString, $siteSalt = "")
    {
        parent::__construct($connectionString);

        $this->_siteSalt = $siteSalt;
    }

    /**
     * Save the current UsersAnyDataset
     *
     * @param \ByJG\Authenticate\Model\UserModel $model
     * @throws \ByJG\Authenticate\Exception\NotImplementedException
     */
    public function save(UserModel $model)
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
     * @throws NotImplementedException
     */
    public function addUser($name, $userName, $email, $password)
    {
        throw new NotImplementedException('Add new user is not implemented');
    }

    protected function passwordIsLegacyHash($password)
    {
        return (bool) preg_match('/^[0-9a-f]{32}$/', $password);
    }

    public function isValidUser($userName, $password)
    {
        $user = $this->getByLoginField($userName);
        if (is_null($user)) {
            return null;
        }

        $savedPassword = $user->get($this->getUserDefinition()->getPassword());
        $validatedUser = null;

        if ($savedPassword === AUTH_PASSWORD_NOT_CACHED) {
            return null;
        }

        if ($this->passwordIsLegacyHash($savedPassword)) {
            if ($savedPassword === md5($password . $this->_siteSalt) || $savedPassword === md5($password) || $savedPassword
                === md5(addslashes($password) . $this->_siteSalt) || $savedPassword === md5(addslashes($password))
            ) {
                $validatedUser = $user;
            }
        } else {
            if (!function_exists('crypt')) {
                throw new ErrorException("Crypt must be loaded for password_verify to function");
            }

            $ret = crypt($password, $savedPassword);
            if (!is_string($ret) || strlen($ret) != strlen($savedPassword) || strlen($ret) <= 13) {
                return null;
            }

            $status = 0;
            $lenRet = strlen($ret);
            for ($i = 0; $i < $lenRet; $i++) {
                $status |= (ord($ret[$i]) ^ ord($savedPassword[$i]));
            }

            if ($status === 0) {
                $validatedUser = $user;
            }
        }

        return $validatedUser;
    }

    public function getUser($filter)
    {
        $user = parent::getUser($filter);

        if (!is_null($user)) {
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
            $param = array("id" => $user->get($this->getUserDefinition()->getUserid()));
            $it = $this->_provider->getIterator($sqlRoles, $param);
            foreach ($it as $sr) {
                $user->addProperty(new UserPropertiesModel("roles", $sr->get('shortname')));
            }

            // Find the moodle site admin (super user)
            $user->set($this->getUserDefinition()->getAdmin(), 'no');
            $sqlAdmin = "select value from mdl_config where name = 'siteadmins'";
            $it = $this->_provider->getIterator($sqlAdmin);
            if ($it->hasNext()) {
                $sr = $it->moveNext();
                $siteAdmin = ',' . $sr->get('value') . ',';
                $isAdmin = (strpos($siteAdmin, ",{$user->get($this->getUserDefinition()->getUserid())},") !== false);
                $user->setAdmin($isAdmin ? 'yes' : 'no');
            }
        }

        return $user;
    }

    /**
     * Remove the user based on his user login.
     *
     * @param string $login
     * @return bool
     * @throws NotImplementedException
     */
    public function removeByLoginField($login)
    {
        throw new NotImplementedException('Remove user is not implemented');
    }

    /**
     * Remove the user based on his user id.
     *
     * @param int $userId
     * @return bool
     * @throws NotImplementedException
     */
    public function removeUserById($userId)
    {
        throw new NotImplementedException('Remove user by Id is not implemented');
    }

    /**
     * Remove a specific site from all users
     * Return True or false
     *
     * @param string $propertyName Property name
     * @param string $value Property value with a site
     * @return bool
     * @throws NotImplementedException
     */
    public function removeAllProperties($propertyName, $value = null)
    {
        throw new NotImplementedException('Remove property value from all users is not implemented');
    }

    public function getUserDefinition()
    {
        if (is_null($this->_userTable)) {
            $this->_userTable = new UserDefinition(
                "mdl_user",
                "id",
                "concat(firstname, ' ', lastname)",  // This disable update data
                "email",
                "username",
                "password",
                "created",
                "auth"                            // This disable update data
            );
        }
        return $this->_userTable;
    }

    public function getUserPropertiesDefinition()
    {
        if (is_null($this->_propertiesTable)) {
            $this->_propertiesTable = new UserPropertiesDefinition("mdl_user_info_data", "id", "fieldid", "data");
        }
        return $this->_propertiesTable;
    }
}
