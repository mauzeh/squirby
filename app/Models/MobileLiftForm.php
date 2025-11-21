<?php

namespace App\Models;

/**
 * MobileLiftForm Stub
 * 
 * This is a stub class to maintain backward compatibility with LiftLogFormFactory.
 * The mobile_lift_forms table has been removed - users now navigate directly to lift-logs/create.
 * 
 * This class only exists to allow temporary object creation for form ID generation.
 * It does not interact with the database.
 */
class MobileLiftForm
{
    public $id;
    public $user_id;
    public $exercise_id;
    public $date;
    
    private $relations = [];
    
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }
    }
    
    public function setRelation($name, $value)
    {
        $this->relations[$name] = $value;
        return $this;
    }
    
    public function __get($name)
    {
        return $this->relations[$name] ?? null;
    }
}
