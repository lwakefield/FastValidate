<?php
namespace FastValidate;

use Illuminate\Database\Eloquent\Model;

use Input;
use Validator;

abstract class BaseModel extends Model
{

    protected $auto_populate = true;

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);

        if (app()->environment('testing')) {
            static::boot();
        }
    }

    public static function create($attributes = [])
    {
        $class_name = get_called_class();
        $instance = new $class_name;
        $input = $instance->getRelevantInput(Input::all());
        $input_intended_for_many = is_array(head($input));
        if ($input_intended_for_many) {
            return static::createMany();
        }
        return parent::create();
    }

    public static function createMany()
    {
        $class_name = get_called_class();
        $instance = new $class_name;
        $input = $instance->getRelevantInput(Input::all());
        $keys = array_keys($input);
        $count = $instance->countExpectedModels($input);
        $models = [];
        for ($i = 0; $i < $count; $i++) {
            $data = [];
            $model = new $class_name;
            foreach ($keys as $key) {
                $model->setAttribute($key, $input[$key][$i]);
            }
            $model->auto_populate = false;
            $model->save();
            $model->auto_populate = true;
            $models[] = $model;
        }
        return $models;
    }

    private function countExpectedModels($input) {
        $keys = array_keys($input);
        for ($i = 0; $i < count($keys); $i++) {
            for ($j = $i; $j < count($keys); $j++) {
                assert(count($input[$keys[$i]]) == count($input[$keys[$j]]));
            }
        }
        return count(head($input));
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function (BaseModel $model) {
            $model->validate();
            if ($model->auto_populate) {
                $model->populateFromArray(
                    $model->getRelevantInput(Input::all())
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
        $this_class_name = strtolower(get_class($this));
        foreach ($attributes as $key => $val) {
            if (starts_with($key, $this_class_name.'.')) {
                $new_key = str_replace($this_class_name.'.', '', $key);
                $input[$new_key] = $val;
            }
        }
        return $input;
    }
}

