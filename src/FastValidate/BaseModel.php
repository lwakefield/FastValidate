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
        $this->auto_populate = true;
        $this->save();
        $this->auto_populate = false;
        return $this;
    }

    public static function createFromInput()
    {
        if (!empty($attributes)) {
            return static::createFromAttributes($attributes);
        }
        if (static::inputIntendedForMany()) {
            return static::createMany();
        }
        $model = static::getInstance();
        $model->saveFromInput();
        return $model;
    }

    private static function inputIntendedForMany()
    {
        $instance = static::getInstance();
        $input = $instance->getProposedAttributes();
        return is_array(head($input));
    }

    public static function createMany()
    {
        $instance = static::getInstance();
        $input = $instance->getProposedAttributes();
        $count = static::countExpectedModels($input);
        $models = [];
        for ($i = 0; $i < $count; $i++) {
            $data = [];
            foreach($input as $key => $val) {
                $data[$key] = $val[$i];
            }
            $models[] = static::createFromAttributes($data);
        }
        return $models;
    }

    private static function createFromAttributes($attributes)
    {
        $model = static::getInstance();
        foreach ($attributes as $key => $val) {
            $model->setAttribute($key, $val);
        }
        $model->save();
        return $model;
    }

    private static function countExpectedModels($input) {
        $keys = array_keys($input);
        for ($i = 0; $i < count($keys); $i++) {
            for ($j = $i; $j < count($keys); $j++) {
                assert(count($input[$keys[$i]]) == count($input[$keys[$j]]));
            }
        }
        return count(head($input));
    }

    private static function getInstance()
    {
        $class_name = get_called_class();
        return new $class_name;
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function (BaseModel $model) {
            $model->validate();
            if ($model->auto_populate) {
                $model->populateFromArray(
                    $model->getProposedAttributes()
                );
            }
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
            $validator = Validator::make($this->getProposedAttributes(), $this->rules, $messages);
            if ($validator->fails()) {
                throw new ValidationException('Error validating model', $validator->errors());
            }
        }
    }

    protected function getProposedAttributes()
    {
        return $this->getRelevantInput(
            array_merge($this->getAttributes(), Input::all())
        );
    }

    protected function getRelevantInput($attributes)
    {
        $input = [];
        $this_class_name = strtolower(class_basename(get_class($this)));
        foreach ($attributes as $key => $val) {
            $new_key = '';
            if (starts_with($key, $this_class_name.'_')) {
                $new_key = str_replace($this_class_name.'_', '', $key);
            } else if (starts_with($key, $this_class_name.'.')) {
                $new_key = str_replace($this_class_name.'.', '', $key);
            }
            if (!empty($new_key)) {
                $input[$new_key] = $val;
            }
        }
        return $input;
    }
}

