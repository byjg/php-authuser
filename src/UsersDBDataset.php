<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Dataset\IteratorFilterSqlFormatter;
use ByJG\AnyDataset\DbDriverInterface;
use ByJG\AnyDataset\Factory;
use ByJG\AnyDataset\Store\Helpers\SqlHelper;
use ByJG\AnyDataset\Dataset\AnyDataset;
use ByJG\AnyDataset\Dataset\IteratorFilter;
use ByJG\AnyDataset\IteratorInterface;
use ByJG\AnyDataset\Dataset\SingleRow;
use ByJG\Authenticate\Exception\UserExistsException;

class UsersDBDataset extends UsersBase
{

    /**
     * @var DbDriverInterface
     */
    protected $_db;
    protected $_sqlHelper;
    protected $_cacheUserWork = array();
    protected $_cacheUserOriginal = array();

    /**
     * UsersDBDataset constructor
     *
     * @param string $connectionString
     * @param UserTable $userTable
     * @param CustomTable $customTable
     */
    public function __construct($connectionString, UserTable $userTable = null, CustomTable $customTable = null)
    {
        $this->_db = Factory::getDbRelationalInstance($connectionString);
        $this->_sqlHelper = new SqlHelper($this->_db);
        $this->_userTable = $userTable;
        $this->_customTable = $customTable;
    }

    /**
     *
     * Save the current UsersAnyDataset
     */
    public function save()
    {
        foreach ($this->_cacheUserOriginal as $key => $value) {
            $srOri = $this->_cacheUserOriginal[$key];
            $srMod = $this->_cacheUserWork[$key];

            // Look for changes
            $changeUser = false;
            foreach ($srMod->getFieldNames() as $keyfld => $fieldname) {
                $userField = ($fieldname == $this->getUserTable()->name
                    || $fieldname == $this->getUserTable()->email
                    || $fieldname == $this->getUserTable()->username
                    || $fieldname == $this->getUserTable()->password
                    || $fieldname == $this->getUserTable()->created
                    || $fieldname == $this->getUserTable()->admin
                    || $fieldname == $this->getUserTable()->id
                );
                if ($srOri->getField($fieldname) != $srMod->getField($fieldname)) {
                    // This change is in the Users table or is a Custom property?
                    if ($userField) {
                        $changeUser = true;
                        continue;
                    }

                    // Erase Old Custom Properties
                    $this->removeProperty($srMod->getField($this->getUserTable()->id), $fieldname, $srOri->getField($fieldname));

                    // If new Value is_empty does not add
                    if ($srMod->getField($fieldname) == "") {
                        continue;
                    }

                    // Insert new Value
                    $this->addProperty($srMod->getField($this->getUserTable()->id), $fieldname, $srMod->getField($fieldname));
                }
            }

            if ($changeUser) {

                $this->updateUser(
                    $srMod->getField($this->getUserTable()->id),
                    $srMod->getField($this->getUserTable()->name),
                    $srMod->getField($this->getUserTable()->email),
                    $srMod->getField($this->getUserTable()->username),
                    $srMod->getField($this->getUserTable()->password),
                    $srMod->getField($this->getUserTable()->created),
                    $srMod->getField($this->getUserTable()->admin)
                );
            }
        }
        $this->_cacheUserOriginal = array();
        $this->_cacheUserWork = array();
    }

    /**
     * @param int $id
     * @param string $name
     * @param string $email
     * @param string $username
     * @param string $password
     * @param string $created
     * @param string $admin
     */
    protected function updateUser($id, $name, $email, $username, $password, $created, $admin)
    {
        $sql = $this->sqlUpdateUser();

        $sql = $this->_sqlHelper->createSafeSQL($sql,
            array(
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
        $param['name'] = $name;
        $param['email'] = $email;
        $param['username'] = $username;
        $param['password'] = $password;
        $param['created'] = $created;
        $param['admin'] = $admin;
        $param['id'] = $id;

        $this->_db->execute($sql, $param);
    }

    protected function sqlUpdateUser()
    {
        return
            "UPDATE @@Table " .
            " SET @@Name  = [[name]] " .
            ", @@Email = [[email]] " .
            ", @@Username = [[username]] " .
            ", @@Password = [[password]] " .
            ", @@Created = [[created]] " .
            ", @@Admin = [[admin]] " .
            " WHERE @@Id = [[id]]";
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

        $sql = $this->_sqlHelper->createSafeSQL($this->sqlAdUser(),
            array(
                '@@Table' => $this->getUserTable()->table,
                '@@Name' => $this->getUserTable()->name,
                '@@Email' => $this->getUserTable()->email,
                '@@Username' => $this->getUserTable()->username,
                '@@Password' => $this->getUserTable()->password,
                '@@Created' => $this->getUserTable()->created,
                '@@UserId' => $this->getUserTable()->id
            )
        );

        $param = array();
        $param['name'] = $name;
        $param['email'] = strtolower($email);
        $param['username'] = preg_replace('/(?:([\w])|([\W]))/', '\1', strtolower($userName));
        $param['password'] = $this->getPasswordHash($password);
        $param['created'] = date("Y-m-d H:i:s");
        $param['userid'] = $this->generateUserId();

        $this->_db->execute($sql, $param);

        return true;
    }

    protected function sqlAdUser()
    {
        return
            " INSERT INTO @@Table ( @@UserId, @@Name, @@Email, @@Username, @@Password, @@Created ) "
            . " VALUES ( [[userid]], [[name]], [[email]], [[username]], [[password]], [[created]] ) ";
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
        if (is_null($filter)) {
            $filter = new IteratorFilter();
        }
        $sql = $filter->format(
            new IteratorFilterSqlFormatter(),
            $this->getUserTable()->table,
            $param
        );
        return $this->_db->getIterator($sql, $param);
    }

    /**
     * Get the user based on a filter.
     * Return SingleRow if user was found; null, otherwise
     *
     * @param IteratorFilter $filter Filter to find user
     * @return SingleRow
     * */
    public function getUser($filter)
    {
        $it = $this->getIterator($filter);
        if (!$it->hasNext()) {
            return null;
        }

        // Get the Requested User
        $sr = $it->moveNext();
        $this->setCustomFieldsInUser($sr);

        // Clone the User Properties
        $anyOri = new AnyDataset();
        $anyOri->appendRow();
        foreach ($sr->getFieldNames() as $key => $fieldName) {
            $anyOri->addField($fieldName, $sr->getField($fieldName));
        }
        $itOri = $anyOri->getIterator();
        $srOri = $itOri->moveNext();

        // Store and return to the user the proper single row.
        $this->_cacheUserOriginal[$sr->getField($this->getUserTable()->id)] = $srOri;
        $this->_cacheUserWork[$sr->getField($this->getUserTable()->id)] = $sr;

        return $this->_cacheUserWork[$sr->getField($this->getUserTable()->id)];
    }

    /**
     * Remove the user based on his user login.
     *
     * @param string $login
     * @return bool
     * */
    public function removeUserName($login)
    {
        $user = $this->getByUsername($login);

        return $this->removeUserById($user->getField($this->getUserTable()->id));
    }

    /**
     * Remove the user based on his user id.
     *
     * @param int $userId
     * @return bool
     * */
    public function removeUserById($userId)
    {
        $baseSql = $this->sqlRemoveUserById();
        $param = array("id" => $userId);
        if ($this->getCustomTable()->table != "") {
            $sql = $this->_sqlHelper->createSafeSQL($baseSql,
                array(
                '@@Table' => $this->getCustomTable()->table,
                '@@Id' => $this->getUserTable()->id
            ));
            $this->_db->execute($sql, $param);
        }
        $sql = $this->_sqlHelper->createSafeSQL($baseSql,
            array(
            '@@Table' => $this->getUserTable()->table,
            '@@Id' => $this->getUserTable()->id
        ));
        $this->_db->execute($sql, $param);
        return true;
    }

    protected function sqlRemoveUserById()
    {
        return "DELETE FROM @@Table WHERE @@Id = [[id]]" ;
    }

    /**
     *
     * @param int $userId
     * @param string $propertyName
     * @param string $value
     * @return bool
     */
    public function addProperty($userId, $propertyName, $value)
    {
        //anydataset.SingleRow
        $user = $this->getById($userId);
        if (empty($user)) {
            return false;
        }

        if (!$this->hasProperty($userId, $propertyName, $value)) {

            $sql = $this->_sqlHelper->createSafeSQL(
                $this->sqlAddProperty(),
                [
                    "@@Table" => $this->getCustomTable()->table,
                    "@@Id" => $this->getUserTable()->id,
                    "@@Name" => $this->getCustomTable()->name,
                    "@@Value" => $this->getCustomTable()->value
                ]
            );

            $param = array();
            $param["id"] = $userId;
            $param["name"] = $propertyName;
            $param["value"] = $value;

            $this->_db->execute($sql, $param);
        }
        return true;
    }

    protected function sqlAddProperty()
    {
        return
            " INSERT INTO @@Table ( @@Id, @@Name, @@Value ) "
            . " VALUES ( [[id]], [[name]], [[value]] ) ";
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
    public function removeProperty($userId, $propertyName, $value)
    {
        $user = $this->getById($userId);
        if ($user !== null) {
            $param = array();
            $param["id"] = $userId;
            $param["name"] = $propertyName;
            $param["value"] = $value;

            $sql = $this->_sqlHelper->createSafeSQL($this->sqlRemoveProperty(!is_null($value)),
                array(
                    '@@Table' => $this->getCustomTable()->table,
                    '@@Name' => $this->getCustomTable()->name,
                    '@@Id' => $this->getUserTable()->id,
                    '@@Value' => $this->getCustomTable()->value
                )
            );

            $this->_db->execute($sql, $param);
            return true;
        }

        return false;
    }

    protected function sqlRemoveProperty($withValue = false)
    {
        return
            "DELETE FROM @@Table "
            . " WHERE @@Id = [[id]] AND @@Name = [[name]] "
            . ($withValue ? " AND @@Value = [[value]] " : '');
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

        $sql = $this->_sqlHelper->createSafeSQL($this->sqlRemoveAllProperties(),
            array(
            "@@Table" => $this->getCustomTable()->table,
            "@@Name" => $this->getCustomTable()->name,
            "@@Value" => $this->getCustomTable()->value
        ));

        $this->_db->execute($sql, $param);

        return true;
    }

    protected function sqlRemoveAllProperties()
    {
        return "DELETE FROM @@Table WHERE @@Name = [[name]] AND @@Value = [[value]] ";
    }

    /**
     * Return all custom's fields from this user
     *
     * @param SingleRow $userRow
     */
    protected function setCustomFieldsInUser($userRow)
    {
        if ($this->getCustomTable()->table == "") {
            return;
        }

        $userId = $userRow->getField($this->getUserTable()->id);

        $sql = $this->_sqlHelper->createSafeSQL($this->sqlSetCustomFieldsInUser(),
            array(
            "@@Table" => $this->getCustomTable()->table,
            "@@Id" => $this->getUserTable()->id
        ));

        $param = array('id' => $userId);
        $it = $this->_db->getIterator($sql, $param);
        while ($it->hasNext()) {
            $sr = $it->moveNext();
            $userRow->addField($sr->getField($this->getCustomTable()->name),
                $sr->getField($this->getCustomTable()->value));
        }
    }

    protected function sqlSetCustomFieldsInUser()
    {
        return "select * from @@Table where @@Id = [[id]]";
    }
}
