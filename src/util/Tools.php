<?php
/*
 *  工具函数
 *  其他工具函数
 */

/**
 * 日期工具
 */
//获取 $timestamp 所在月份的 最后 $len 个工作日（日期数组），每周工作日天数 $workingdays
function getLastWorkingDateOfMonth(
    $timestamp, 
    $len=3,         //最后 $len 个工作日
    $workingdays=5  //每周工作日天数，默认 5，6-7为休息日
) {
    //$timestamp 月份有多少天，format="t"
    $ld = date("t", $timestamp);
    //$timestamp 月份最后一天是周几，数字 1-7 mon-sun，format="N"
    $lw = date("N", strtotime(date("Y-m", $timestamp)."-".$ld." 12:00:00"));
    if ($lw<$workingdays+1){
        if ($lw<$len) {
            $sd = $ld-(7-$workingdays)-($len-1);
        } else {
            $sd = $ld-($len-1);
        }
    } else {
        $sd = $ld-($lw-$workingdays)-($len-1);
    }
    $ds = [];
    for ($i=$sd;$i<=$ld;$i++) {
        if (date("N", strtotime(date("Y-m", $timestamp)."-".$i." 12:00:00"))<($workingdays+1)){
            $ds[] = $i;
        }
    }
    return $ds;
}
//工作日 timestamp+秒数，返回新的 timestamp，注意：工作日有工作时长
//例如：2023-07-19 11:15:50 + 7*60*60(7小时) = 2023-07-20 10:15:50
function calcWorkingTimestamp (
    $timestamp,         //起始时间
    $seconds,           //要加减的秒数，加上负数=减去
    $st=(8*60+30)*60,   //工作日开始时间，08:30
    $et=18*60*60,        //工作日结束时间，18:00
    $workingdays=6      //每周工作日天数，默认 6，7为休息日
) {
    $tc = $timestamp;
    if (date("N",$tc)>$workingdays) {   //如果起始时间在休息日，则将起始时间设置为之后的第一个工作日的工作开始时间
        $_tc = $tc+((7-$workingdays)*24*60*60);
        $_t0 = strtotime(date("Y-m-d", $_tc)." 00:00:00");
        $tc = $_t0+$st;
    }
    //现在 $tc 肯定不是休息日
    $t0 = strtotime(date("Y-m-d",$tc)." 00:00:00");  //今日 0时
    if ($tc<$t0+$st) {
        $tc = $t0+$st;
    } else if ($tc>=$t0+$et) {
        if (date("N",$tc)==$workingdays) {
            $tc = $t0+$st+((7-$workingdays+1)*24*60*60);
        } else {
            $tc = $t0+$st+(24*60*60);
        }
    }
    //现在 $tc 作为今日，肯定不是休息日，肯定在工作时间段
    $t0 = strtotime(date("Y-m-d",$tc)." 00:00:00");  //今日 0时
    $dw = $et-$st;              //每日工作时长，秒数
    $dp = ceil($seconds/$dw);   //增加的秒数折合几个工作日，向上取整，整除则+1
    if ($dp==$seconds/$dw) {
        $dp += 1;
    }
    $tp = $tc+$seconds;         //加上秒数后的 timestamp
    $ts = $t0+$st;  //今日 工作开始时间
    $te = $t0+$et;  //今日 工作结束时间
    $wc = date("N", $tc);   //今日周几，1-7
    
    if ($tp<=$te) return $tp;       //如果加上秒数后仍在今日工作时间范围内，直接返回
    if ($dp<=1) {                   // 1    如果增加的秒数折合工作日不超过 1 天   
        $tte = $te-$tc;
        $_tp = $t0+(24*60*60)+$st+($seconds-$tte);
        if (date("N",$_tp)>$workingdays) {  //如果计算后为休息日，则向后顺延 (7-workingdays)*24*60*60 秒
            return $_tp+((7-$workingdays)*24*60*60);
        } else {
            return $_tp;
        }
    } else if ($dp>=7) {               // 2    如果增加秒数折合工作日超过 7 天
        $tw = $workingdays-$wc;
        $dpws = floor(($dp-$tw)/7);
        $ds = $dp-1+(($dpws+1)*(7-$workingdays));
        //var_dump("实际过了 ".$ds." 天，含 ".$dpws." 个休息日");
        $ss = ($dp-1)*$dw;
        $scs = $seconds-$ss;
        $_tc = $tc+($ds*24*60*60);
        return calcWorkingTimestamp($_tc, $scs, $st, $et, $workingdays);
    } else {                        // 3    如果增加秒数折合工作日天数在 1-7 之间
        if (7-$wc>$dp) {            // 3.1  如果增加秒数折合天数<今日到休息日的天数
            $_te = $seconds-($te-$tc)-(($dp-1)*$dw);
            return $ts+($dp*24*60*60)+$_te;
        } else  {                   // 3.2  如果增加秒数后，日期超过了休息日日期
            $tw = 7-$wc;
            $pw = $dp-$tw;
            $ds = $dp+(7-$workingdays);
            //var_dump("实际需要天数 ".($ds-1)." 天，24h");
            $_tsc = $te-$tc;
            $scs = $seconds-(($dp-2)*$dw)-$_tsc;
            $_ts = $ts+($ds-1)*24*60*60;
            return $_ts+$scs;
        }
    }
}



//身份证号解析
function parse_idcard($idcard = "")
{
    if (is_notempty_str($idcard) && strlen($idcard)==18) {
        $birth = substr($idcard, 6,4)."-".substr($idcard, 10,2)."-".substr($idcard, 12,2);
        $birth_y = substr($idcard, 6,4);
        $now_y = date("Y",time());
        $sex = substr($idcard, 16,1);
        $sex = (int)$sex;
        $sex = $sex%2;
        return [
            "birthday" => $birth,
            "sex" => $sex==0 ? 2 : 1,   // 1 男，2 女
            "age" => (int)$now_y - (int)$birth_y
        ];
    }
    return null;
}

//调试输出
function break_dump($getkey="", $varToDump=null)
{
    $key = isset($_GET[$getkey]) ? $_GET[$getkey] : null;
    if ($key=="yes") {
        if (is_indexed($varToDump)) {
            foreach ($varToDump as $i => $var) {
                var_dump($var);
            }
        } else {
            var_dump($varToDump);
        }
        //exit;
    }
}