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

    public static function createFromInput()
    {
        if (static::inputIntendedForMany()) {
            return static::createManyFromInput();
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

    private static function createManyFromInput()
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
        $model->saveWithAttributes($attributes);
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

        $has_relations = static::inputHasRelations();
        $is_ajax = Input::ajax();
        if ($has_relations && $is_ajax) {
            foreach (static::getRelationsFromInput() as $key => $val) {
                $relation = $this->$key();
                if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\BelongsTo')) {
                    $model = $relation->getRelated()->createFromAttributes($val);
                    $relation->associate($model);
                } else if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\HasOne')) {
                    $model = $relation->create($val);
                } else if (is_a($relation, 'Illuminate\Database\Eloquent\Relations\HasMany')) {
                    foreach ($val as $attrs) {
                        $model = $relation->getRelated()->createFromAttributes($attrs);
                        $relation->save($model);
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

