# FastValidate

Validate even faster with FastValidate!

FastValidate requires Laravel >= 5.0

## Installation

To install, simply `composer require lawrence/fast-validate`

## Example

Below is an example of a class that extends the `BaseModel`.

```php
<?php

use FastValidate\BaseModel;

class User extends BaseModel {

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    public function getRulesAttribute(){
        return [
            'name' => 'required',
            'email' => 'required|email|unique:users,email,' . $this->id,
            'password' => 'required|min:8'
        ];
    }

    public function setPasswordAttribute($value){
        $this->attributes['password'] = bcrypt($value);
    }

}
```

Below is an example of how we can use the User class.

```php
<?php

// Creating a new model
$user = new User;
$user->save();

// Updating a model
$user = User::where('name', '=', 'Super User')->firstOrFail();
$user->save();
```

The magic comes from hooking into the _saving_ event hook. When a model is begin saved, it will be grab all relevant input from the Request, validate the input, then populate the model with the input. If the input is not valid, a ValidationException will be thrown, which contains a MessageBag full of the validation errors.

To provide this magic, there is a requirement on the structure of the data.
This requirement follows the format `model_name.attribute_name`.
So for the above `User` example, we will need to send data in the following format:

```php
<?php
Input::merge([
        'user.name' => 'Johnnie',
        'user.email' => 'john@theinternet.com',
        'user.password' => 'correcthorsebatterystaple'
]);
```

In the above `User` example, we have added some complexities. The `getRulesAttribute()` function means we can dynamically change our validation rules. In this case, we want to make sure that an email is unique, but if we are updating a model with the same email address, then `"unique:users,email,$this->id"` will avoid this issue.

The `setPasswordAttribute($value)` function is used, so that we can validate against the password that we are provided, then save the bcrypted value of the password.

Sometimes you might want to create multiple models from the input.
We can do this with a form like shown below:

```html
<form action="create-users" method="post">
    <input type="text" name="user.name[]" value="Johnnie">
    <input type="text" name="user.email[]" value="john@theinternet.com">
    <input type="text" name="user.password[]" value="correcthorsebatterystaple">
    <input type="text" name="user.name[]" value="Tommie">
    <input type="text" name="user.email[]" value="tommie@theinternet.com">
    <input type="text" name="user.password[]" value="Tr0ub4dor&3">
</form>
```

Which produces the following data:

```json
    {
        'name' : ['Johnnie', 'Tommie'],
        'email': ['john@theinternet.com', 'tommie@theinternet.com'],
        'password': ['correcthorsebatterystaple', 'Tr0ub4dor&3']
    }
```
