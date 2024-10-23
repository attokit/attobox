<?php
/**
 * Attobox 事件 订阅/触发/处理
 * 事件订阅者可以是：App实例 / Route实例 / Record实例 / RecordSet实例
 * 
 * 订阅事件：
 *      Event::addListener($listener, $event, $once=false)
 * 触发事件：
 *      Event::trigger($event, $triggerBy, ...$args)
 * 取消订阅：
 *      Event::removeListener($listener, $event)
 * 将 $listener 对象内部的 handle***Event() 方法批量创建 事件订阅：
 *      Event::regist($listener)
 */

namespace Atto\Box;

use Atto\Box\App;
use Atto\Box\Route;
use Atto\Box\Record;
use Atto\Box\RecordSet;
use Atto\Box\traits\staticCurrent;

class Event 
{
    //引入trait
    use staticCurrent;

    /**
     * current
     */
    public static $current = null;

    /**
     * 已创建的事件以及订阅者
     */
    public static $event = [
        /*
        "event-name" => [
            event listener,
            可以是：App实例 / Route实例 / Record实例 / RecordSet实例
            ...
        ],
        "event-name-once" => [
            event listener,
            同一事件的 一次性 订阅者
        ],
        */
    ];

    /**
     * 获取所有 $event 事件 的 订阅者
     * @param String $event 事件名称
     * @return Array
     */
    protected static function getListeners($event)
    {
        $evts = self::$event;
        if (!isset($evts[$event]) || !is_notempty_arr($evts[$event])) return [];
        $evt = $evts[$event];
        $ock = $event."-once";
        $once = (!isset($evts[$ock]) || !is_notempty_arr($evts[$ock])) ? [] : $evts[$ock];
        $ls = array_merge($evt, $once);
        if (empty($ls)) return [];
        //return array_merge(array_flip(array_flip($ls)));  //value可能是 object 无法 flip
        return $ls;
    }

    /**
     * 判断是否合法的 listener 
     * @param Object $listener 订阅者
     * @return Bool
     */
    protected static function isLegalListener($listener)
    {
        if (
            $listener instanceof App ||         //App实例
            $listener instanceof Route ||       //Route实例
            $listener instanceof Record ||      //Record数据记录实例
            $listener instanceof RecordSet      //RecordSet数据记录集实例
        ) {
            return true;
        }
        return false;
    }

    /**
     * 订阅事件
     * @param Object $listener 订阅者，可以是：App实例 / Route实例 / Record实例 / RecordSet实例
     * @param String $event 事件名称
     * @param Bool $once 是否一次性事件
     * @return Bool
     */
    public static function addListener($listener, $event, $once=false)
    {
        if (self::isLegalListener($listener)) {
            $ls = self::getListeners($event);
            if (!in_array($listener, $ls)) {
                if (!$once) {
                    if (!isset(self::$event[$event])) self::$event[$event] = [];
                    self::$event[$event][] = $listener;
                } else {
                    $ek = $event."-once";
                    if (!isset(self::$event[$ek])) self::$event[$ek] = [];
                    self::$event[$ek][] = $listener;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * 取消订阅事件
     * @param Object $listener 订阅者，可以是：App实例 / Route实例 / Record实例 / RecordSet实例
     * @param String $event 事件名称
     * @return Bool
     */
    public static function removeListener($listener, $event)
    {
        $evts = self::$event;
        $ek = $event."-once";
        if (!isset($evts[$event]) && !isset($evts[$ek])) return false;
        if (in_array($listener, $evts[$event])) {
            array_splice(self::$event[$event], array_search($listener, $evts[$event]), 1);
            return true;
        } else if (in_array($listener, $evts[$ek])) {
            array_splice(self::$event[$ek], array_search($listener, $evts[$ek]), 1);
            return true;
        }
        return false;
    }

    /**
     * 触发事件
     * @param String $event 事件名称
     * @param Mixed $triggerBy 触发者
     * @param Array $args 传递给 handler 的参数
     * @return Bool
     */
    public static function trigger($event, $triggerBy, ...$args)
    {
        $ls = self::getListeners($event);
        if (empty($ls)) return false;
        /**
         * 每个 listener 对象内部应实现 handler 方法 handleEventNameEvent()
         * 此方法第一个参数应为 触发事件的 对象
         * 此方法应 return void
         */
        for ($i=0;$i<count($ls);$i++) {
            $lsi = $ls[$i];
            if (empty($lsi)) continue;
            if (self::isLegalListener($lsi)==false) continue;
            //handler 方法名： foo-bar  -->  handleFooBarEvent
            $m = "handle".strtocamel($event, true)."Event";
            if (method_exists($lsi, $m)) {
                $lsi->$m($triggerBy, ...$args);
            }
        }
        /**
         * 删除 $event 事件的 一次性订阅
         */
        $ek = $event."-once";
        if (isset(self::$event[$ek])) unset(self::$event[$ek]);
        return true;
    }

    /**
     * 检查 订阅者 对象内部，自动将 handleFooBarEvent() 方法创建为 事件订阅
     * @param Object $listener 要检查的 订阅者 对象
     * @return Bool
     */
    public static function regist($listener)
    {
        if (!self::isLegalListener($listener)) return false;
        //检查 订阅者 对象内部 handle***Event() 方法
        $ms = cls_get_ms(get_class($listener), function($mi) {
            if (substr($mi->name, 0, 6)=="handle" && substr($mi->name, -5)=="Event") {
                return true;
            }
            return false;
        }, "public");
        
        if (empty($ms)) return false;
        foreach ($ms as $mn => $mi) {
            $evt = substr($mi->name, 6, -5);
            $evt = trim(strtosnake($evt, "-"), "-");
            self::addListener($listener, $evt);
        }
        return true;
    }

}