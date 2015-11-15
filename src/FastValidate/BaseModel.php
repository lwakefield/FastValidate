<?php
namespace FastValidate;

use Illuminate\Database\Eloquent\Model;
use FastValidate\Input\FormInput;

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

    public static function updateFromInput()
    {
        $input = static::getRelevantInput();
        if (static::inputIntendedForMany()) {
            $models = [];
            foreach ($input as $attrs) {
                $models[] = static::updateFromAttributes($attrs);
            }
            return $models;
        }
        return static::updateFromAttributes($input);
    }

    public static function createFromInput()
    {
        $input = static::getRelevantInput();
        if (static::inputIntendedForMany()) {
            $models = [];
            foreach ($input as $attrs) {
                $models[] = static::createFromAttributes($attrs);
            }
            return $models;
        }
        return static::createFromAttributes($input);
    }

    private static function inputIntendedForMany()
    {
        $relations = static::getRelationsFromInput();
        $input = array_filter(static::getRelevantInput(), function($val) use ($relations) {
            return !in_array($val, $relations);
        });
        foreach ($input as $val) {
            if (is_array($val)) {
                return true;
            }
        }
        return false;
    }

    private static function createFromAttributes($attributes)
    {
        $model = static::getNewInstance();
        $model->saveWithAttributes($attributes);
        return $model;
    }

    private static function updateFromAttributes($attributes)
    {
        $model = static::getNewInstance();
        $model = $model->find($attributes['id']);
        $model->saveWithAttributes($attributes, true);
        return $model;
    }

    private static function getNewInstance()
    {
        $class_name = get_called_class();
        return new $class_name;
    }

    private static function attributesHaveRelations($attrs)
    {
        return !empty(static::getRelationsFromAttributes($attrs));
    }

    private static function inputHasRelations()
    {
        return !empty(static::getRelationsFromInput());
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

    private static function getRelevantInput()
    {
        $input = [];
        $this_class_name = strtolower(class_basename(get_called_class()));
        if (Input::ajax()) {
            return Input::get($this_class_name);
        }
        return FormInput::getInputForClass($this_class_name);
    }


    public function saveFromInput()
    {
        assert(!static::inputIntendedForMany());
        $this->saveWithAttributes(static::getRelevantInput());
        return $this;
    }

    private function saveWithAttributes($attrs, $update = false)
    {
        $this->populateFromArray($attrs);
        $this->save();
        $this->saveRelations(
            static::getRelationsFromAttributes($attrs),
            $update);
    }

    private function saveRelations($attrs, $update = false)
    {
        if (!Input::ajax()) {
            return;
        }
        foreach ($attrs as $key => $val) {
            $relation = $this->$key();
            if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\BelongsTo')) {
                $model = $update ? 
                    $relation->getRelated()->updateFromAttributes($val) :
                    $relation->getRelated()->createFromAttributes($val);
                $relation->associate($model);
            } else if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\HasOne')) {
                $model = $update ?
                    $relation->getRelated()->updateFromAttributes($val) :
                    $relation->create($val);
            } else if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\HasMany')) {
                foreach ($val as $attrs) {
                    $model = $update ?
                        $relation->getRelated()->updateFromAttributes($attrs) :
                        $relation->getRelated()->createFromAttributes($attrs);
                    $relation->save($model);
                }
            }
        }
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

