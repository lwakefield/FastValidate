<?php
namespace FastValidate\Input;

use Input;

class FormInput
{
    public static function getInputForClass($class_name)
    {
        $input = [];
        foreach (Input::all() as $key => $val) {
            $new_key = str_replace($class_name.'_', $class_name.'.', $key);
            array_set($input, $new_key, $val);
        }
        return array_get($input, $class_name, []);
    }
}
