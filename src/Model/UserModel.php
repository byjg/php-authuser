<?php

namespace ByJG\Authenticate\Model;

class UserModel
{
    protected $userid;
    protected $name;
    protected $email;
    protected $username;
    protected $password;
    protected $created;
    protected $admin;

    protected $propertyList = [];

    /**
     * UserModel constructor.
     *
     * @param $name
     * @param $email
     * @param $username
     * @param $password
     * @param $admin
     */
    public function __construct($name = "", $email = "", $username = "", $password = "", $admin = "no")
    {
        $this->name = $name;
        $this->email = $email;
        $this->username = $username;
        $this->password = $password;
        $this->admin = $admin;
    }


    /**
     * @return mixed
     */
    public function getUserid()
    {
        return $this->userid;
    }

    /**
     * @param mixed $userid
     */
    public function setUserid($userid)
    {
        $this->userid = $userid;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param mixed $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return mixed
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param mixed $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return mixed
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @param mixed $created
     */
    public function setCreated($created)
    {
        $this->created = $created;
    }

    /**
     * @return mixed
     */
    public function getAdmin()
    {
        return $this->admin;
    }

    /**
     * @param mixed $admin
     */
    public function setAdmin($admin)
    {
        $this->admin = $admin;
    }

    public function set($name, $value)
    {
        $property = $this->get($name, true);
        if (empty($property)) {
            $property = new UserPropertiesModel($name, $value);
            $this->addProperty($property);
        } else {
            $property->setValue($value);
        }
    }

    /**
     * @param $property
     * @param bool $instance
     * @return \ByJG\Authenticate\Model\UserPropertiesModel|array|string
     */
    public function get($property, $instance = false)
    {
        $result = [];
        foreach ($this->getProperties() as $propertiesModel) {
            if ($propertiesModel->getName() == $property) {
                if ($instance) {
                    return $propertiesModel;
                }
                $result[] = $propertiesModel->getValue();
            }
        }

        if (count($result) == 0) {
            return null;
        }

        if (count($result) == 1) {
            return $result[0];
        }

        return $result;
    }

    /**
     * @return \ByJG\Authenticate\Model\UserPropertiesModel[]
     */
    public function getProperties()
    {
        return $this->propertyList;
    }

    /**
     * @param \ByJG\Authenticate\Model\UserPropertiesModel[] $properties
     */
    public function setProperties(array $properties)
    {
        $this->propertyList = $properties;
    }

    public function addProperty(UserPropertiesModel $property)
    {
        $this->propertyList[] = $property;
    }
}
