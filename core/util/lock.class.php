<?php
namespace Core\Util;

class Lock
{
    static private $queue = [];

    /**
     * 加锁
     * @param string $name 锁名
     * @param int $type 锁类型 0-自旋锁 1-异步锁 2-协程锁(只在同进程中有效)
     * @param int $expire 有效期(秒) 默认30秒，最大60秒
     */
    static public function set(string $name, int $type = 0, int $expire = 0): bool
    {
        $expire = $expire === 0 ? 30 : ($expire > 60 ? 60 : $expire);
        if($type == 2){
            if(!isset(self::$queue[$name])){
                self::$queue[$name] = [];
                \Swoole\Timer::after($expire * 1000, function() use ($name){
                    if(empty(self::$queue[$name]))unset(self::$queue[$name]);
                });
            }else{
                $cid = getcid();
                \Swoole\Timer::after($expire * 1000, function() use ($cid, $name){
                    if(\Swoole\Coroutine::exists($cid))\Swoole\Coroutine::resume($cid);
                    if(empty(self::$queue[$name]))unset(self::$queue[$name]);
                });
                self::$queue[$name][] = $cid;
                \Swoole\Coroutine::yield();
                self::$queue[$name] = array_diff(self::$queue[$name], [$cid]);
            }
        }else{
            $lock = T('__LOCK')->get($name);
            if($lock === false || $lock['timeout'] < time()){
                if(!!$lock)T('__LOCK')->del($name);
                T('__LOCK')->set($name, [
                    'type' => $type?1:0,
                    'timeout' => time() + ($expire ?: 30)
                ]);
                return true;
            }else{
                if($lock['type'] == 0){
                    //自旋锁
                    \Swoole\Coroutine::sleep(0.05);
                    self::set($name, $type, $expire);
                }elseif($lock['type'] == 1){
                    //异步锁
                    return false;
                }
                return false;
            }
        }
    }

    /**
     * 检查锁
     * @param $name 锁名
     * @return bool true|false 是否可以通行 被锁无法通行则返回false
     */
    public static function try(string $name): bool
    {
        $lock = T('__LOCK')->get($name);
        if($lock === false || $lock['timeout'] < time()){
            if(!!$lock)T('__LOCK')->del($name);
            return true;
        }else{
            return false;
        }
    }

    /**
     * 解锁
     * @param string $name 锁名
     * @return bool true|false 是否有锁
     */
    public static function unset(string $name): bool
    {
        if(T('__LOCK')->exist($name))T('__LOCK')->del($name);
        elseif(isset(self::$queue[$name])){
            //唤醒队列中的一个协程
            if($cid = array_shift(self::$queue[$name]))
                if(\Swoole\Coroutine::exists($cid))\Swoole\Coroutine::resume($cid);
            //当队列为空则完全解锁
            if(empty(self::$queue[$name]))unset(self::$queue[$name]);
        }
        return true;
    }
}
