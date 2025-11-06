---
sidebar_position: 6
title: User Properties
---

# User Properties

User properties allow you to store custom key-value data associated with users. This is useful for storing additional information beyond the standard user fields.

## Adding Properties

### Add a Single Property

```php
<?php
$users->addProperty($userId, 'phone', '555-1234');
$users->addProperty($userId, 'department', 'Engineering');
```

:::info Duplicates
`addProperty()` will not add the property if it already exists with the same value.
:::

### Add Multiple Values for the Same Property

Users can have multiple values for the same property:

```php
<?php
$users->addProperty($userId, 'role', 'developer');
$users->addProperty($userId, 'role', 'manager');
```

### Set a Property (Update or Create)

Use `setProperty()` to update an existing property or create it if it doesn't exist:

```php
<?php
// This will update the existing value or create a new one
$users->setProperty($userId, 'phone', '555-5678');
```

## Using UserModel

You can also manage properties directly through the `UserModel`:

```php
<?php
use ByJG\Authenticate\Model\UserPropertiesModel;

$user = $users->getById($userId);

// Set a property value
$user->set('phone', '555-1234');

// Add a property model
$property = new UserPropertiesModel('department', 'Engineering');
$user->addProperty($property);

// Save the user to persist properties
$users->save($user);
```

## Retrieving Properties

### Get a Single Property

```php
<?php
$phone = $users->getProperty($userId, 'phone');
// Returns: '555-1234'
```

### Get Multiple Values

If a property has multiple values, an array is returned:

```php
<?php
$roles = $users->getProperty($userId, 'role');
// Returns: ['developer', 'manager']
```

Returns `null` if the property doesn't exist.

### Get Properties from UserModel

```php
<?php
$user = $users->getById($userId);

// Get property value(s)
$phone = $user->get('phone');

// Get property as UserPropertiesModel instance
$propertyModel = $user->get('phone', true);

// Get all properties
$allProperties = $user->getProperties();
foreach ($allProperties as $property) {
    echo $property->getName() . ': ' . $property->getValue();
}
```

## Checking Properties

### Check if User Has a Property

```php
<?php
// Check if property exists
if ($users->hasProperty($userId, 'phone')) {
    echo "User has a phone number";
}

// Check if property has a specific value
if ($users->hasProperty($userId, 'role', 'admin')) {
    echo "User is an admin";
}
```

:::tip Admin Bypass
The `hasProperty()` method always returns `true` for admin users, regardless of the actual property values.
:::

## Removing Properties

### Remove a Specific Property Value

```php
<?php
// Remove a specific property with a specific value
$users->removeProperty($userId, 'role', 'developer');
```

### Remove All Values of a Property

```php
<?php
// Remove all values of a property for a user
$users->removeProperty($userId, 'phone');
```

### Remove Property from All Users

```php
<?php
// Remove a property from all users
$users->removeAllProperties('temporary_flag');

// Remove a specific value from all users
$users->removeAllProperties('role', 'guest');
```

## Finding Users by Properties

### Find Users with a Specific Property Value

```php
<?php
$engineers = $users->getUsersByProperty('department', 'Engineering');
// Returns array of UserModel objects
```

### Find Users with Multiple Properties

```php
<?php
$users = $users->getUsersByPropertySet([
    'department' => 'Engineering',
    'role' => 'senior',
    'status' => 'active'
]);
// Returns users that have ALL these properties with the specified values
```

## Common Use Cases

### User Roles and Permissions

```php
<?php
// Add multiple roles to a user
$users->addProperty($userId, 'role', 'viewer');
$users->addProperty($userId, 'role', 'editor');
$users->addProperty($userId, 'role', 'admin');

// Check permissions
if ($users->hasProperty($userId, 'role', 'admin')) {
    // Allow admin actions
}

// Get all roles
$roles = $users->getProperty($userId, 'role');
```

### User Preferences

```php
<?php
$users->setProperty($userId, 'theme', 'dark');
$users->setProperty($userId, 'language', 'en');
$users->setProperty($userId, 'timezone', 'America/New_York');

// Retrieve preferences
$theme = $users->getProperty($userId, 'theme');
```

### Multi-tenant Applications

```php
<?php
// User can belong to multiple organizations
$users->addProperty($userId, 'organization', 'org-123');
$users->addProperty($userId, 'organization', 'org-456');

// Find all users in an organization
$orgUsers = $users->getUsersByProperty('organization', 'org-123');

// Check access
if ($users->hasProperty($userId, 'organization', $requestedOrgId)) {
    // Grant access
}
```

## Property Storage

### Database Storage

Properties are stored in a separate table (default: `users_property`):

| Column     | Description              |
|------------|--------------------------|
| customid   | Property ID              |
| userid     | User ID (foreign key)    |
| name       | Property name            |
| value      | Property value           |

### XML/AnyDataset Storage

Properties are stored as fields within each user's record, with arrays used for multiple values.

## Next Steps

- [User Management](user-management.md) - Basic user operations
- [Database Storage](database-storage.md) - Configure property storage
- [Custom Fields](custom-fields.md) - Extend the UserModel
