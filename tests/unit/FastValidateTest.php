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
            withStrings('first_name', 'last_name');
        $I->wantATable('roles')->
            withStrings('title');
        $I->wantATable('profiles')->
            withStrings('title');
        $I->wantATable('posts')->
            withStrings('title', 'content');
        $I->want('users')->belongsTo('roles');
        $I->want('users')->toHaveOne('profiles');
        $I->want('users')->toHaveMany('posts');
        $I->amReadyForMigration();
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

    public function testCreateWithBelongsToRelationFromAjax()
    {
        $this->setInputToAjax();
        $data = ['user' => [
            'first_name' => 'Johnny',
            'role' => ['title' => 'admin']
        ]];
        Input::merge($data);
        $model = User::createFromInput();
        $this->seeInDatabase('users', ['first_name' => 'Johnny']);
        $this->seeInDatabase('roles', ['title' => 'admin']);
    }

    public function testCreateWithHasOneRelationFromAjax()
    {
        $this->setInputToAjax();
        $data = ['user' => [
            'first_name' => 'Johnny',
            'profile' => ['title' => 'my profile']
        ]];
        Input::merge($data);
        $model = User::createFromInput();
        $this->seeInDatabase('users', ['first_name' => 'Johnny']);
        $this->seeInDatabase('profiles', ['title' => 'my profile']);
    }

    public function testCreateWithHasManyRelationFromAjax()
    {
        $this->setInputToAjax();
        $data = ['user' => [
            'first_name' => 'Johnny',
            'posts' => [
                ['title' => 'post 1'],
                ['title' => 'post 2'],
            ]
        ]];
        Input::merge($data);
        $model = User::createFromInput();
        $this->seeInDatabase('users', ['first_name' => 'Johnny']);
        $this->seeInDatabase('posts', ['title' => 'post 1']);
        $this->seeInDatabase('posts', ['title' => 'post 2']);
    }

    public function testUpdateFromInput()
    {
        $data = ['first_name' => 'Johnnie', 'last_name' => 'Doe'];
        $model = User::create($data);

        $data = ['user.id' => $model->id, 'user.first_name' => 'Tommie'];
        Input::merge($data);
        User::updateFromInput();
        $this->seeInDatabase('users', ['id' => $model->id, 'first_name' => 'Tommie']);
    }

    public function testUpdateManyFromInput()
    {
        $data = ['user.first_name' => ['Johnnie', 'Tommie'], 'user.last_name' => ['Doe', 'Moe']];
        Input::merge($data);
        $models = User::createFromInput();

        $data = ['user.id' => [$models[0]->id, $models[1]->id], 'user.first_name' => ['Meseeks', 'Youseeks']];
        Input::merge($data);
        User::updateFromInput();
        $this->seeInDatabase('users', ['id' => $models[0]->id, 'first_name' => 'Meseeks']);
        $this->seeInDatabase('users', ['id' => $models[1]->id, 'first_name' => 'Youseeks']);
    }

    public function testUpdateWithHasManyRelationFromAjax()
    {
        $this->setInputToAjax();
        $data = ['user' => [
            'first_name' => 'Johnny',
            'posts' => [
                ['title' => 'post 1'],
                ['title' => 'post 2'],
            ]
        ]];
        Input::merge($data);
        $model = User::createFromInput();
        $model->load('posts');


        $data = ['user' => [
            'id' => $model->id,
            'first_name' => 'Tommie',
            'posts' => [
                ['id' => $model->posts[0]->id, 'title' => 'updated post 1'],
                ['id' => $model->posts[1]->id, 'title' => 'updated post 2'],
            ]
        ]];
        Input::merge($data);
        $model = User::updateFromInput();
        $model->load('posts');
        $this->seeInDatabase('users', ['id' => $model->id, 'first_name' => 'Tommie']);
        $this->seeInDatabase('posts', ['id' => $model->posts[0]->id, 'title' => 'updated post 1']);
        $this->seeInDatabase('posts', ['id' => $model->posts[1]->id, 'title' => 'updated post 2']);
    }

}

class User extends FastValidate\BaseModel
{
    protected $fillable = ['first_name', 'last_name'];
    protected $rules = [
        'first_name' => 'required',
        'last_name' => ''
    ];
    public function role()
    {
        return $this->belongsTo('Role');
    }
    public function profile()
    {
        return $this->hasOne('Profile');
    }
    public function posts()
    {
        return $this->hasMany('Post');
    }
}
class Profile extends FastValidate\BaseModel
{
    protected $fillable = ['title'];
}
class Role extends FastValidate\BaseModel
{
    protected $fillable = ['title'];
}
class Post extends FastValidate\BaseModel
{
    protected $fillable = ['title'];
}
