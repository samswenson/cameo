<?php
//This auxiliar class is used to stats purpose and it not mean for another thing except testing
//It just only exposes results from the constructor

class statsModule extends statcommMustache
{
    private $data;
    public function __construct( $values)
    {
        $this->data =$values;
    }

    public function templateName() { return "statsModule";}

    public function rows()
    {
        return $this->data;
    }
}