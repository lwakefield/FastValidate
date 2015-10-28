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
        $data = ['user.first_name' => 'Johnny', 'user.last_name' => 'Doe'];
        Input::merge($data);
        $model = new User;
        $model->save();
        $this->seeInDatabase('users', ['first_name' => 'Johnny', 'last_name' => 'Doe']);
    }

    //public function testValidatesCorrectly()
    //{
        //$data = ['last_name' => 'Doe'];
        //Input::merge($data);
        //$model = new User;
        //try {
            //$model->save();
        //} catch(ValidationException $e) {
            //$this->assertTrue($e->errors->has('first_name'));
        //}
        //$this->notSeeInDatabase('users', $data);
    //}

    //public function testCustomValidationMessage()
    //{
        //$model = new User;
        //$custom_message = 'You have got to choose a first name!';
        //$model->messages = [
            //'first_name.required' => $custom_message
        //];
        //try {
            //$model->save();
        //} catch(ValidationException $e) {
            //$has_custom_message = in_array($custom_message, $e->errors->get('first_name'));
            //$this->assertTrue($has_custom_message);
        //}
    //}

    //public function testSaveDontPopulate()
    //{
        //Input::merge(['last_name' => 'Doe']);
        //$model = new User;
        //$model->first_name = 'Joe';
        //$model->saveDontPopulate();
        //$this->assertEquals($model->first_name, 'Joe');
        //$this->assertNotEquals($model->last_name, 'Doe');
    //}
    
    //public function testRevertsAfterSaveDontPopulate()
    //{
        //Input::merge(['last_name' => 'Doe']);
        //$model = new User;
        //$model->first_name = 'Joe';
        //$model->saveDontPopulate();
        //$data = ['first_name' => 'Johnny', 'last_name' => 'Doe'];
        //Input::merge($data);
        //$model->save();
        //$this->assertEquals($model->first_name, 'Johnny');
        //$this->assertEquals($model->last_name, 'Doe');
    //}

    public function testCreate()
    {
        $data = ['user.first_name' => 'Johnnie', 'user.last_name' => 'Doe'];
        Input::merge($data);
        $model = User::create();
        $this->seeInDatabase('users', ['first_name' => 'Johnnie', 'last_name' => 'Doe']);
    }

    public function testCreateMany()
    {
        $data = ['user.first_name' => ['Johnnie', 'Tommie'], 'user.last_name' => ['Doe', 'Moe']];
        Input::merge($data);
        $models = User::create();
        $this->seeInDatabase('users', ['first_name' => 'Johnnie', 'last_name' => 'Doe']);
        $this->seeInDatabase('users', ['first_name' => 'Tommie', 'last_name' => 'Moe']);
    }

    //public function testCreateWithAttributes()
    //{
        //$overide_data = ['first_name' => 'Johnnie', 'last_name' => 'Doe'];
        //Input::merge($overide_data);
        //$data = ['first_name' => 'Tommie', 'last_name' => 'Moe'];
        //$model = User::create($data);
        //$this->seeInDatabase('users', $data);
    //}

}

class User extends FastValidate\BaseModel
{
    protected $fillable = ['first_name', 'last_name'];
    protected $rules = [
        'first_name' => 'required',
        'last_name' => ''
    ];
}
