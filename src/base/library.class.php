<?php

namespace Simoole\Base;

use Simoole\Sub;

//中间键基类，可用于make函数实例化使用
class Library
{
    private $process_name = null;

    public function async(string $process_name)
    {
        $this->process_name = $process_name;
        $class_id = createCode(8, false);

        //在子进程中反序列化该类
        Sub::send([
            '__classid' => $class_id,
            '__string' => serialize($this)
        ], $this->process_name);

        //返回一个匿名类
        return new class($process_name, $class_id, get_class($this)){
            private $process_name = '';
            private $class_name = '';

            public function __construct(string $process_name, string $class_id, string $class_name)
            {
                $this->process_name = $process_name;
                $this->class_name = $class_name;
                $this->class_id = $class_id;
            }

            public function __call($name, $params = [])
            {
                //判断该方法是否有返回值
                $is_return = (new \ReflectionClass($this->class_name))->getMethod($name)->hasReturnType();
                $res = Sub::send([
                    '__classid' => $this->class_id,
                    '__actname' => $name,
                    '__params' => $params
                ], $this->process_name, $is_return);
                if($is_return)return $res;
                return $this;
            }

            public function __destruct()
            {
                //手动回收内存
                return Sub::send([
                    '__classid' => $this->class_id
                ], $this->process_name);
            }
        };
    }
}