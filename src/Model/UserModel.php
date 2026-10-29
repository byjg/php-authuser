<?php

namespace ByJG\Authenticate\Model;

use ByJG\Authenticate\Definition\PasswordDefinition;
use ByJG\Authenticate\MapperFunctions\PasswordBcryptMapper;
use ByJG\MicroOrm\Attributes\FieldAttribute;
use ByJG\MicroOrm\Attributes\TableAttribute;
use ByJG\MicroOrm\Literal\Literal;
use ByJG\MicroOrm\Trait\CreatedAt;
use ByJG\MicroOrm\Trait\DeletedAt;
use ByJG\MicroOrm\Trait\UpdatedAt;
use InvalidArgumentException;

#[TableAttribute(tableName: 'users')]
class UserModel
{
    use CreatedAt;
    use UpdatedAt;
    use DeletedAt;
    #[FieldAttribute(primaryKey: true)]
    protected string|int|Literal|null $userid = null;

    #[FieldAttribute]
    protected ?string $name = null;

    #[FieldAttribute]
    protected ?string $email = null;

    #[FieldAttribute]
    protected ?string $username = null;

    #[FieldAttribute(updateFunction: PasswordBcryptMapper::class)]
    protected ?string $password = null;

    #[FieldAttribute]
    protected ?string $role = null;

    protected ?PasswordDefinition $passwordDefinition = null;

    protected array $propertyList = [];

    /**
     * UserModel constructor.
     *
     * @param string $name
     * @param string $email
     * @param string $username
     * @param string $password
     * @param string $role
     */
    public function __construct(string $name = "", string $email = "", string $username = "", string $password = "", string $role = "")
    {
        $this->name = $name;
        $this->email = $email;
        $this->username = $username;
        $this->setPassword($password);
        $this->role = $role;
    }


    /**
     * @return string|int|Literal|null
     */
    public function getUserid(): string|int|Literal|null
    {
        return $this->userid;
    }

    /**
     * @param string|int|Literal|null $userid
     */
    public function setUserid(string|int|Literal|null $userid): void
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
        // A hash (read from the database) is not checked against the password definition
        if (!empty($this->passwordDefinition) && !empty($password) && !$this->isPasswordHash($password)) {
            $match = $this->passwordDefinition->matchPassword($password);
            if ($match != PasswordDefinition::SUCCESS) {
                throw new InvalidArgumentException("Password does not match the password definition [$match]");
            }
        }

        $this->password = $password;
    }

    /**
     * Whether the value is a hash, by the password mapper UsersService registers on the
     * password definition; the default mapper when there is none.
     */
    protected function isPasswordHash(string $password): bool
    {
        $mapper = $this->passwordDefinition?->getPasswordMapper() ?? PasswordBcryptMapper::class;
        if (is_string($mapper)) {
            $mapper = new $mapper();
        }
        return $mapper->isPasswordEncrypted($password);
    }

    /**
     * @return string|null
     */
    public function getRole(): ?string
    {
        return $this->role;
    }

    /**
     * @param string|null $role
     */
    public function setRole(?string $role): void
    {
        $this->role = $role;
    }

    /**
     * Check if user has a specific role
     *
     * @param string $role Role name to check
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return $this->role !== null && $this->role !== '' && strcasecmp($this->role, $role) === 0;
    }

    public function set(string $name, string|null $value): void
    {
        $property = $this->get($name, true);
        if (empty($property)) {
            $property = new UserPropertiesModel($name, $value);
            $this->addProperty($property);
        } elseif ($property instanceof UserPropertiesModel) {
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
        $this->setPassword($this->password);
        return $this;
    }
}
