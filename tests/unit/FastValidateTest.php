<?php

use FastValidate\BaseModel;
use FastValidate\ValidationException;

class FastValidateTest extends Illuminate\Foundation\Testing\TestCase
{

    public function createApplication()
    {
        $app = require __DIR__.'/../../vendor/laravel/laravel/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    public function setUp()
    {
        parent::setUp();

        $this->app['config']->set('database.default', 'testing');

        $this->app['config']->set('database.connections.testing', array(
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ));
        $this->request = $this->app['request'];

        $I = $this->getMockForAbstractClass('FastMigrate\FastMigrator');
        $I->wantATable('users')->
            withStrings('first_name', 'last_name')->
            amReadyForMigration();
    }

    public function testSavesCorrectly()
    {
        $data = ['first_name' => 'Johnny', 'last_name' => 'Doe'];
        Input::merge($data);
        $model = new User;
        $model->save();
        $this->seeInDatabase('users', $data);
    }

    public function testValidatesCorrectly()
    {
        $data = ['last_name' => 'Doe'];
        Input::merge($data);
        $model = new User;
        try {
            $model->save();
        } catch(ValidationException $e) {
            $this->assertTrue($e->errors->has('first_name'));
        }
        $this->notSeeInDatabase('users', $data);
    }

    public function testCustomValidationMessage()
    {
        $model = new User;
        $custom_message = 'You have got to choose a first name!';
        $model->messages = [
            'first_name.required' => $custom_message
        ];
        try {
            $model->save();
        } catch(ValidationException $e) {
            $has_custom_message = in_array($custom_message, $e->errors->get('first_name'));
            $this->assertTrue($has_custom_message);
        }
    }
    
}

class User extends FastValidate\BaseModel
{
    protected $fillable = ['first_name', 'last_name'];
    protected $rules = [
        'first_name' => 'required',
        'last_name' => ''
    ];
}
