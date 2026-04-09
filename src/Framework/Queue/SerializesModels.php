<?php

namespace App\Framework\Queue;

use App\Models\Model;

trait SerializesModels
{
    public function __sleep()
    {
        foreach (get_object_vars($this) as $property => $value) {
            if ($value instanceof Model) {
                $this->{$property} = [
                    '__model_class' => get_class($value),
                    'id' => $value->getKey(),
                ];
            }
        }

        return array_keys(get_object_vars($this));
    }

//    public function __wakeup()
//    {
//        foreach (get_object_vars($this) as $property => $value) {
//            if (is_array($value) && isset($value['__model_class'])) {
//                $class = $value['__model_class'];
//                $this->{$property} = $class::find($value['id']);
//            }
//        }
//    }
}