<?php

namespace ByJG\Authenticate\Model;

class CustomModel
{
    protected $userid;
    protected $customid;
    protected $name;
    protected $value;

    /**
     * CustomModel constructor.
     *
     * @param $name
     * @param $value
     */
    public function __construct($name = "", $value = "")
    {
        $this->name = $name;
        $this->value = $value;
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
    public function getCustomid()
    {
        return $this->customid;
    }

    /**
     * @param mixed $customid
     */
    public function setCustomid($customid)
    {
        $this->customid = $customid;
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
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }
}
