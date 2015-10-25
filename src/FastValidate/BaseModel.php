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

    public function saveDontPopulate()
    {
        return $this->save(false);
    }

    public function save($populate = true)
    {
        $last_populate = $populate;
        $this->auto_populate = $populate;
        $result = parent::save();
        $this->auto_populate = $last_populate;
        return $result;
    }

    public static function boot()
    {
        parent::boot();

        static::saving(function (BaseModel $model) {
            $model->validate();
            if ($model->auto_populate) {
                $model->populate();
            }
        });
    }

    protected function populate()
    {
        foreach (Input::all() as $key => $val) {
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

        return $this->auto_populate ? 
            array_merge($this->getAttributes(), Input::all()) :
            $this->getAttributes();
    }
}

