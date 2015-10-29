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

    public function setInputToAjax()
    {
        Input::instance()->headers->set('X-Requested-With', 'XMLHttpRequest');
    }

    public function testSaveFromInput()
    {
        $data = ['user.first_name' => 'Johnny', 'user.last_name' => 'Doe'];
        Input::merge($data);
        $model = new User;
        $model->saveFromInput();
        $this->seeInDatabase('users', ['first_name' => 'Johnny', 'last_name' => 'Doe']);
    }

    public function testValidatesCorrectly()
    {
        $data = ['user.last_name' => 'Doe'];
        Input::merge($data);
        try {
            User::createFromInput();
        } catch(ValidationException $e) {
            $this->assertTrue($e->errors->has('first_name'));
        }
        $this->notSeeInDatabase('users', ['last_name' => 'Doe']);
    }

    public function testCustomValidationMessage()
    {
        $model = new User;
        $custom_message = 'You have got to choose a first name!';
        $model->messages = [
            'first_name.required' => $custom_message
        ];
        try {
            $model->saveFromInput();
        } catch(ValidationException $e) {
            $has_custom_message = in_array($custom_message, $e->errors->get('first_name'));
            $this->assertTrue($has_custom_message);
        }
    }

    public function testCreate()
    {
        $data = ['first_name' => 'Johnnie', 'last_name' => 'Doe'];
        $model = User::create($data);
        $this->seeInDatabase('users', $data);
    }

    public function testCreateFromInput()
    {
        $data = ['user.first_name' => 'Johnnie', 'user.last_name' => 'Doe'];
        Input::merge($data);
        $model = User::createFromInput();
        $this->seeInDatabase('users', ['first_name' => 'Johnnie', 'last_name' => 'Doe']);
    }

    public function testCreateManyFromInput()
    {
        $data = ['user.first_name' => ['Johnnie', 'Tommie'], 'user.last_name' => ['Doe', 'Moe']];
        Input::merge($data);
        $models = User::createFromInput();
        $this->seeInDatabase('users', ['first_name' => 'Johnnie', 'last_name' => 'Doe']);
        $this->seeInDatabase('users', ['first_name' => 'Tommie', 'last_name' => 'Moe']);
    }

    public function testSaveFromAjax()
    {
        $this->setInputToAjax();
        $data = ['user' => ['first_name' => 'Johnny', 'last_name' => 'Doe']];
        Input::merge($data);
        $model = new User;
        $model->saveFromInput();
        $this->seeInDatabase('users', ['first_name' => 'Johnny', 'last_name' => 'Doe']);
    }

    public function testCreateFromAjax()
    {
        $this->setInputToAjax();
        $data = ['user' => ['first_name' => 'Johnny', 'last_name' => 'Doe']];
        Input::merge($data);
        User::createFromInput();
        $this->seeInDatabase('users', ['first_name' => 'Johnny', 'last_name' => 'Doe']);
    }

    public function testCreateManyFromAjax()
    {
        $this->setInputToAjax();
        $data = ['user' => [
            ['first_name' => 'Johnny', 'last_name' => 'Doe'],
            ['first_name' => 'Tommie', 'last_name' => 'Moe']
        ]];
        Input::merge($data);
        $models = User::createFromInput();
        $this->seeInDatabase('users', ['first_name' => 'Johnny', 'last_name' => 'Doe']);
        $this->seeInDatabase('users', ['first_name' => 'Tommie', 'last_name' => 'Moe']);
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
