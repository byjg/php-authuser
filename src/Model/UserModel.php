<?php
/**
 * User: jg
 * Date: 09/05/17
 * Time: 16:24
 */

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

    protected $custom = [];

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

    public function set($property, $value)
    {
        $custom = $this->get($property, true);
        if (empty($custom)) {
            $custom = new CustomModel($property, $value);
            $this->addCustomProperty($custom);
        } else {
            $custom->setValue($value);
        }
    }

    /**
     * @param $property
     * @param bool $instance
     * @return \ByJG\Authenticate\Model\CustomModel|array|string
     */
    public function get($property, $instance = false)
    {
        $result = [];
        foreach ($this->getCustomProperties() as $custom) {
            if ($custom->getName() == $property) {
                if ($instance) {
                    return $custom;
                }
                $result[] = $custom->getValue();
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
     * @return mixed
     */
    public function getCustomProperties()
    {
        return $this->custom;
    }

    /**
     * @param mixed $custom
     */
    public function setCustomProperties($custom)
    {
        $this->custom = $custom;
    }

    public function addCustomProperty(CustomModel $custom)
    {
        $this->custom[] = $custom;
    }
}
