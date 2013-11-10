<?php
// GAからのデータを保存するためのクラス
class GAPIResult
{
    private $data = array();
    
    function __construct($data)
    {
        $this->data = $data;
    }

    // get～メソッドでデータを取得する
    function __call($name, $value)
    {
        if (preg_match('/^get(.*)/', $name, $match)) {
            $key = lcfirst($match[1]);
            
            return $this->$key;
        }

        $trace = debug_backtrace();
        trigger_error('Undefined method via __call(): ' . $name . '() in ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'], E_USER_ERROR);
        
        return null;
    }

    // データを取得する
    function __get($name)
    {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        $trace = debug_backtrace();
        trigger_error('Undefined property via __get(): ' . $name . ' in ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'], E_USER_NOTICE);
        
        return null;
    }
}
