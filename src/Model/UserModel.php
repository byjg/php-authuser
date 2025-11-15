<?php

namespace ByJG\Authenticate\Model;

use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\MapperFunctions\PasswordSha1Mapper;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\HexUuidLiteral;
use ByJG\MicroOrm\MapperFunctions\ReadOnlyMapper;
use InvalidArgumentException;

#[TableAttribute(tableName: 'users')]
class UserModel
{
    #[FieldAttribute(primaryKey: true)]
    protected string|int|HexUuidLiteral|null $userid = null;

    #[FieldAttribute]
    protected ?string $name = null;

    #[FieldAttribute]
    protected ?string $email = null;

    #[FieldAttribute]
    protected ?string $username = null;

    #[FieldAttribute(updateFunction: PasswordSha1Mapper::class)]
    protected ?string $password = null;

    #[FieldAttribute(updateFunction: ReadOnlyMapper::class)]
    protected ?string $created = null;

    #[FieldAttribute]
    protected ?string $admin = null;

    protected ?PasswordDefinition $passwordDefinition = null;

    protected array $propertyList = [];

    /**
     * UserModel constructor.
     *
     * @param string $name
     * @param string $email
     * @param string $username
     * @param string $password
     * @param string $admin
     */
    public function __construct(string $name = "", string $email = "", string $username = "", string $password = "", string $admin = "no")
    {
        $this->name = $name;
        $this->email = $email;
        $this->username = $username;
        $this->setPassword($password);
        $this->admin = $admin;
    }


    /**
     * @return string|int|HexUuidLiteral|null
     */
    public function getUserid(): string|int|HexUuidLiteral|null
    {
        return $this->userid;
    }

    /**
     * @param string|int|HexUuidLiteral|null $userid
     */
    public function setUserid(string|int|HexUuidLiteral|null $userid): void
    {
        $this->userid = $userid;
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
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @param string|null $email
     */
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string|null $username
     */
    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    /**
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param string|null $password
     */
    public function setPassword(?string $password): void
    {
        // Password len equals to 40 means that the password is already encrypted with sha1
        if (!empty($this->passwordDefinition) && !empty($password) && strlen($password) != 40) {
            $match = $this->passwordDefinition->matchPassword($password);
            if ($match != PasswordDefinition::SUCCESS) {
                throw new InvalidArgumentException("Password does not match the password definition [$match]");
            }
        }

        $this->password = $password;
    }

    /**
     * @return string|null
     */
    public function getCreated(): ?string
    {
        return $this->created;
    }

    /**
     * @param string|null $created
     */
    public function setCreated(?string $created): void
    {
        $this->created = $created;
    }

    /**
     * @return string|null
     */
    public function getAdmin(): ?string
    {
        return $this->admin;
    }

    /**
     * @param string|null $admin
     */
    public function setAdmin(?string $admin): void
    {
        $this->admin = $admin;
    }

    public function set(string $name, string|null $value): void
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
     * @param string $property
     * @param bool $instance
     * @return array|string|UserPropertiesModel|null
     */
    public function get(string $property, bool $instance = false): array|string|UserPropertiesModel|null
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
     * @return UserPropertiesModel[]
     */
    public function getProperties(): array
    {
        return $this->propertyList;
    }

    /**
     * @param UserPropertiesModel[] $properties
     */
    public function setProperties(array $properties): void
    {
        $this->propertyList = $properties;
    }

    public function addProperty(UserPropertiesModel $property): void
    {
        $this->propertyList[] = $property;
    }

    public function withPasswordDefinition(PasswordDefinition $passwordDefinition): static
    {
        $this->passwordDefinition = $passwordDefinition;
        return $this;
    }

    public function isAdmin(): bool
    {
        return
            preg_match('/^(yes|YES|[yY]|true|TRUE|[tT]|1|[sS])$/', $this->getAdmin()) === 1
        ;
    }
}
