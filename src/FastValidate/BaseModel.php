<?php
namespace FastValidate;

use Illuminate\Database\Eloquent\Model;

use Input;
use Validator;

abstract class BaseModel extends Model
{

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);

        if (app()->environment('testing')) {
            static::boot();
        }
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function (BaseModel $model) {
            $model->validate();
        });
    }

    public static function createFromInput()
    {
        if (static::inputIntendedForMany()) {
            return Input::ajax() ?
                static::createManyFromAjaxInput() :
                static::createManyFromFormInput();
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
        if (Input::ajax()) {
            return Input::get($this_class_name);
        }
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

    private static function createManyFromFormInput()
    {
        $input = static::getRelevantInput();
        $count = static::countExpectedModelsFromInput($input);
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

    private static function createManyFromAjaxInput()
    {
        $input = static::getRelevantInput();
        $models = [];
        foreach ($input as $i) {
            $models[] = static::createFromAttributes($i);
        }
        return $models;
    }

    private static function createFromAttributes($attributes)
    {
        $model = static::getNewInstance();
        $model->populateFromArray($attributes);
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

    private static function getRelationsFromInput()
    {
        $input = static::getRelevantInput();
        return static::getRelationsFromAttributes($input);
    }

    private static function getRelationsFromAttributes($attrs)
    {
        $instance = static::getNewInstance();
        $relations = [];
        foreach ($attrs as $key => $val) {
            if (method_exists($instance, $key) &&
                is_subclass_of($instance->$key(), 'Illuminate\Database\Eloquent\Relations\Relation')) {
                $relations[$key] = $val;
            }
        }
        return $relations;
    }

    private static function inputHasRelations()
    {
        return !empty(static::getRelationsFromInput());
    }

    public function saveFromInput()
    {
        assert(!static::inputIntendedForMany());

        $this->saveWithAttributes(static::getRelevantInput());

        $has_relations = static::inputHasRelations();
        if ($has_relations) {
            foreach (static::getRelationsFromInput() as $key => $val) {
                $relation = $this->$key();
                if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\BelongsTo')) {
                    $model = $relation->getRelated()->createFromAttributes($val);
                    $relation->associate($model);
                } else if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\HasOne')) {
                    $model = $relation->create($val);
                } else if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\HasMany')) {
                    foreach ($val as $attrs) {
                        $relation->create($attrs);
                    }
                }
            }
        }
        return $this;
    }

    private function saveWithAttributes($attrs)
    {
        $this->populateFromArray($attrs);
        $this->save();
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

