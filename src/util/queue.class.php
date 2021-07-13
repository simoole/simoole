<?php
/**
 * 应用内部队列
 */

namespace Simoole\Util;
use Swoole\Sub;

class Queue
{
    private $proc = null;
    private $qname = null;

    public function __construct(string $name)
    {
        $this->proc = Sub::$procs[0];
        $this->qname = 'queue_' . $name;
    }

    public function push($data)
    {
        Sub::send([
            'type' => MEMORY_QUEUE_PUSH,
            'data' => $data,
            'name' => $this->qname
        ]);
        return true;
    }

    public function pop()
    {
        $res = Sub::send([
            'type' => MEMORY_QUEUE_POP,
            'data' => '',
            'name' => $this->qname
        ], null, true);
        if($res === '[NULL]')return false;
        return $res;
    }

    public function list()
    {
        $res = Sub::send([
            'type' => MEMORY_QUEUE_LIST,
            'data' => '',
            'name' => $this->qname
        ], null, true);
        if($res === '[NULL]')return false;
        return $res;
    }

    public function count()
    {
        $res = Sub::send([
            'type' => MEMORY_QUEUE_COUNT,
            'data' => '',
            'name' => $this->qname
        ], null, true);
        if($res === '[NULL]')return 0;
        return $res;
    }

    public function clear()
    {
        Sub::send([
            'type' => MEMORY_QUEUE_CLEAR,
            'data' => '',
            'name' => $this->qname
        ]);
        return true;
    }
}
