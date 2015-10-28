<?php
namespace FastValidate;

use Illuminate\Database\Eloquent\Model;

use Input;
use Validator;

abstract class BaseModel extends Model
{

    protected $auto_populate = false;

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);

        if (app()->environment('testing')) {
            static::boot();
        }
    }

    public function saveFromInput()
    {
        assert(!static::inputIntendedForMany());
        $input = static::getRelevantInput();
        $this->populateFromArray($input);
        $this->save();
        return $this;
    }

    public static function createFromInput()
    {
        if (static::inputIntendedForMany()) {
            return static::createMany();
        }
        $model = static::getNewInstance();
        $model->saveFromInput();
        return $model;
    }

    private static function inputIntendedForMany()
    {
        $input = static::getRelevantInput();
        return is_array(head($input));
    }

    private static function getRelevantInput()
    {
        $input = [];
        $this_class_name = strtolower(class_basename(get_called_class()));
        foreach (Input::all() as $key => $val) {
            if (starts_with($key, $this_class_name.'_')) {
                $new_key = str_replace($this_class_name.'_', '', $key);
                $input[$new_key] = $val;
            } else if (starts_with($key, $this_class_name.'.')) {
                $new_key = str_replace($this_class_name.'.', '', $key);
                $input[$new_key] = $val;
            }
        }
        return $input;
    }

    public static function createMany()
    {
        $instance = static::getNewInstance();
        $instance->auto_populate = true;
        $input = static::getRelevantInput();
        $count = static::countExpectedModelsFromInput($input);
        $models = [];
        for ($i = 0; $i < $count; $i++) {
            $data = [];
            foreach($input as $key => $val) {
                $data[$key] = $val[$i];
            }
            $model = static::getNewInstance();
            $model->populateFromArray($data);
            $model->save();
            $models[] = $model;
        }
        return $models;
    }

    private static function createFromAttributes($attributes)
    {
        $model = static::getNewInstance();
        foreach ($attributes as $key => $val) {
            $model->setAttribute($key, $val);
        }
        $model->save();
        return $model;
    }

    private static function countExpectedModelsFromInput($input) {
        $keys = array_keys($input);
        for ($i = 0; $i < count($keys); $i++) {
            for ($j = $i; $j < count($keys); $j++) {
                assert(count($input[$keys[$i]]) == count($input[$keys[$j]]));
            }
        }
        return count(head($input));
    }

    private static function getNewInstance()
    {
        $class_name = get_called_class();
        return new $class_name;
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function (BaseModel $model) {
            $model->validate();
        });
    }

    protected function populateFromArray($attributes)
    {
        foreach ($attributes as $key => $val) {
            if (in_array($key, $this->fillable)) {
                $this->setAttribute($key, $val);
            }
        }
    }

    protected function validate()
    {
        $messages = empty($this->messages) ? [] : $this->messages;
        if (isset($this->rules)) {
            $validator = Validator::make($this->getAttributes(), $this->rules, $messages);
            if ($validator->fails()) {
                throw new ValidationException('Error validating model', $validator->errors());
            }
        }
    }

}

