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
        $result = array_get($input, $class_name, []);
        if (static::inputIntendedForMany($result)) {
            return static::restructureForMany($result);
        }
        return array_get($input, $class_name, []);
    }

    private static function inputIntendedForMany($input)
    {
        foreach ($input as $key => $val) {
            if (is_array($val)) {
                return true;
            }
        }
        return false;
    }

    private static function restructureForMany($input) {
        $count = static::countExpectedModelsFromInput($input);
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $data = [];
            foreach($input as $key => $val) {
                $data[$key] = $val[$i];
            }
            $result[] = $data;
        }
        return $result;
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
}
