<?php

namespace ByJG\Authenticate\Model;

class UserPropertiesModel
{
    protected ?string $userid = null;
    protected ?string $id = null;
    protected ?string $name = null;
    protected ?string $value = null;

    /**
     * UserPropertiesModel constructor.
     *
     * @param string $name
     * @param string $value
     */
    public function __construct(string $name = "", string $value = "")
    {
        $this->name = $name;
        $this->value = $value;
    }

    /**
     * @return string|null
     */
    public function getUserid(): ?string
    {
        return $this->userid;
    }

    /**
     * @param string|null $userid
     */
    public function setUserid(?string $userid): void
    {
        $this->userid = $userid;
    }

    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string|null $id
     */
    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * @param string|null $value
     */
    public function setValue(?string $value): void
    {
        $this->value = $value;
    }
}
