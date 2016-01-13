<?php
//员工等级
function getGrade($userid)
{
	$totalscore = M('member')->where(array('userid'=>$userid))->getField('total_score');
	$list =  M('grade')->select();
	$name ='';
	foreach($list as $k=>$v){
		if($v['score']<=$totalscore){
			$name = $v['name'];
		}
	}
	session('gradename',$name);
}

//月积分
function monthscore($userid)
{
	$userid = 'alan1448036575';
	//查询当前人的当月的总积分，过滤掉被管理员扣除的积分
	$sql = array(
		'userid'=>$userid,
		'addormin'=>1,
		'_string'=>"create_time BETWEEN (UNIX_TIMESTAMP(DATE_FORMAT(now(),'%Y-%m'))) AND UNIX_TIMESTAMP(now())"
	);

	//所有加积分的操作
	$addScores = M('addmin_score')->where($sql)->field('sum(score) as score')->find();
	$sql = array(
		'userid'=>$userid,
		'addormin'=>2,
		'create_by'=>1,
		'_string'=>"create_time BETWEEN (UNIX_TIMESTAMP(DATE_FORMAT(now(),'%Y-%m'))) AND UNIX_TIMESTAMP(now())"
	);

	$minScores = M('addmin_score')->where($sql)->field('sum(score) as score')->find();
	$score = ($addScores['score']*1) - ($minScores['score']*1);
	return $score;
}

/**
 * 积分操作
 * @param $score 积分
 * @param $type 积分类型：1表示A积分；2表示B积分
 * @param $opt 操作：1表示加积分；2表示减积分
 * @param $userid 用户
 * @param $create_by 1:管理员；2：系统
 * @param $remark 备注
 * @return bool
 */
function doScore($score,$type,$opt,$userid,$create_by=2,$remark='')
{
	if(!$userid) return false;
	if(!$type) return false;

	//详细表数据添加
	$scoremodel = M('addmin_score');
	//开启事务
	$scoremodel->startTrans();

	$score_arr = array(
		'userid'=>$userid,
		'score'=>$score,
		'addormin'=>$opt,
		'remark'=>$remark,
		'type'=>$type,
		'create_by'=>$create_by,
		'create_time'=>time()
	);

	$id = $scoremodel->add($score_arr);
	if(!$id)
	{
		$scoremodel->rollback();
		return false;
	}

	$model = M('Member');
	$info = $model->where(array('userid'=>$userid))->find();
	//加积分
	$total = $info['total_score'];
	$bscore = $info['current_score'];
	$ascore = $info['money_score'];
	if($type == 1)
	{
		//A积分
		//B积分加减随同A，A积分加，总积分加，A积分减，总积分不变
		//判断加减
		if($opt == 1)
		{
			$data['total_score'] = $total + $score;
			$data['current_score'] = $bscore + $score;
			$data['money_score'] = $ascore + $score;
		}
		if($opt == 2)
		{
			//减积分
			$data['current_score'] = $bscore - $score;
			$data['money_score'] = $ascore - $score;
		}
		$res = $model->where(array('userid'=>$userid))->save($data);
		if($res === false)
		{
			$scoremodel->rollback();
			return false;
		}
	}

	if($type == 2)
	{
		//B积分
		//B积分加减与A无关，B积分加，总积分加，B积分减，总积分不变
		if($opt == 1)
		{
			//加积分
			$data['total_score'] = $total + $score;
			$data['current_score'] = $bscore + $score;
		}

		if($opt == 2)
		{
			//减积分
			$data['current_score'] = $bscore - $score;
		}

		$res = $model->where(array('userid'=>$userid))->save($data);

		if($res === false)
		{
			$scoremodel->rollback();
			return false;
		}
	}
	$scoremodel->commit();

	getGrade($userid);

	return true;
}

/**
 * 获取积分配置
 * @param $key
 */
function getScore($key)
{
	if(!$key) return false;
	$value = M('Config')->where(array('keyname'=>$key))->getField('keyvalue');
	if($value)
	{
		return $value;
	}
	else
	{
		return false;
	}
}





/**
 * 友好的时间显示
 *
 * @param int    $sTime 待显示的时间
 * @param string $type  类型. normal | mohu | full | ymd | other
 * @param string $alt   已失效
 * @return string
 */
function friendlyDate($sTime,$type = 'normal',$alt = 'false') {
	if (!$sTime)
		return '';
	//sTime=源时间，cTime=当前时间，dTime=时间差
	$cTime      =   time();
	$dTime      =   $cTime - $sTime;
	$dDay       =   intval(date("z",$cTime)) - intval(date("z",$sTime));
	//$dDay     =   intval($dTime/3600/24);
	$dYear      =   intval(date("Y",$cTime)) - intval(date("Y",$sTime));
	//normal：n秒前，n分钟前，n小时前，日期
	if($type=='normal'){
		if( $dTime < 60 ){
			if($dTime < 10){
				return '刚刚';    //by yangjs
			}else{
				return intval(floor($dTime / 10) * 10)."秒前";
			}
		}elseif( $dTime < 3600 ){
			return intval($dTime/60)."分钟前";
			//今天的数据.年份相同.日期相同.
		}elseif( $dYear==0 && $dDay == 0  ){
			//return intval($dTime/3600)."小时前";
			return '今天'.date('H:i',$sTime);
		}elseif($dYear==0){
			return date("m月d日",$sTime);
		}else{
			return date("Y-m-d",$sTime);
		}
	}elseif($type=='mohu'){
		if( $dTime < 60 ){
			return $dTime."秒前";
		}elseif( $dTime < 3600 ){
			return intval($dTime/60)."分钟前";
		}elseif( $dTime >= 3600 && $dDay == 0  ){
			return intval($dTime/3600)."小时前";
		}elseif( $dDay > 0 && $dDay<=7 ){
			return intval($dDay)."天前";
		}elseif( $dDay > 7 &&  $dDay <= 30 ){
			return intval($dDay/7) . '周前';
		}elseif( $dDay > 30 ){
			return intval($dDay/30) . '个月前';
		}
		//full: Y-m-d , H:i:s
	}elseif($type=='full'){
		return date("Y-m-d , H:i:s",$sTime);
	}elseif($type=='ymd'){
		return date("Y-m-d",$sTime);
	}else{
		if( $dTime < 60 ){
			return $dTime."秒前";
		}elseif( $dTime < 3600 ){
			return intval($dTime/60)."分钟前";
		}elseif( $dTime >= 3600 && $dDay == 0  ){
			return intval($dTime/3600)."小时前";
		}elseif($dYear==0){
			return date("Y-m-d H:i:s",$sTime);
		}else{
			return date("Y-m-d H:i:s",$sTime);
		}
	}
}


function gettable($id, $tab, $col) {
	if($id == 0)
	{
		return null;
	}
	$List = M ( $tab )->field ( $col )->find ( $id );
	return $List [$col];
}

function getInfoByUserid($userid){
	$sql = array('userid'=>$userid);
	$Realname = M ( 'Member' )->where( $sql )->find();
	return $Realname;
}


/**
 * 描述：截取中文字符串
 * @param  $str 被截取的字符串
 * @author 高友龙(复制)  <2014-10-15>
 */
function mbstr($str,$max_len=35)
{
	$ret = $str;
	$leng =  (strlen($str) + mb_strlen($str,'UTF8')) / 2;

	if($leng>$max_len)
	{
		//截取中文字符串
		$ret = mb_substr($str, 0,$max_len, 'utf-8').'...';
	}
	return $ret;
}

//时间戳差值
function timediff($begin_time,$end_time)
{
	if($begin_time < $end_time){
		$starttime = $begin_time;
		$endtime = $end_time;
	}else{
		$starttime = $end_time;
		$endtime = $begin_time;
	}

//计算天数
	$timediff = $endtime-$starttime;
	$days = intval($timediff/86400);
//计算小时数
	$remain = $timediff%86400;
	$hours = intval($remain/3600);
//计算分钟数
	$remain = $remain%3600;
	$mins = intval($remain/60);
//计算秒数
	$secs = $remain%60;
	$res = array("day" => $days,"hour" => $hours,"min" => $mins,"sec" => $secs);
	return $res;
}


/**
* @desc 根据两点间的经纬度计算距离
* @param float $lat 纬度值
* @param float $lng 经度值
*/
function getDistance($lat1, $lng1, $lat2, $lng2,$num = 3)
{
	$earthRadius = 6367000; //approximate radius of earth in meters

	$lat1 = ($lat1 * pi() ) / 180;

	$lng1 = ($lng1 * pi() ) / 180;

	$lat2 = ($lat2 * pi() ) / 180;

	$lng2 = ($lng2 * pi() ) / 180;

	$calcLongitude = $lng2 - $lng1;

	$calcLatitude = $lat2 - $lat1;

	$stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);

	$stepTwo = 2 * asin(min(1, sqrt($stepOne)));

	$calculatedDistance = $earthRadius * $stepTwo;

	$calculatedDistance = round($calculatedDistance)/1000;

	return sprintf("%.{$num}f",$calculatedDistance);

}


// $string： 明文 或 密文
// $operation：DECODE表示解密,其它表示加密
// $key： 密匙
// $expiry：密文有效期
function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
	// 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
	$ckey_length = 4;
	$AU_KEY = '2347gaoylloveljdfas……&*（';
	// 密匙
	$key = md5($key ? $key : $AU_KEY);

	// 密匙a会参与加解密
	$keya = md5(substr($key, 0, 16));
	// 密匙b会用来做数据完整性验证
	$keyb = md5(substr($key, 16, 16));
	// 密匙c用于变化生成的密文
	$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';
	// 参与运算的密匙
	$cryptkey = $keya.md5($keya.$keyc);
	$key_length = strlen($cryptkey);
	// 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，解密时会通过这个密匙验证数据完整性
	// 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
	$string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
	$string_length = strlen($string);
	$result = '';
	$box = range(0, 255);
	$rndkey = array();
	// 产生密匙簿
	for($i = 0; $i <= 255; $i++) {
		$rndkey[$i] = ord($cryptkey[$i % $key_length]);
	}
	// 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
	for($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}
	// 核心加解密部分
	for($a = $j = $i = 0; $i < $string_length; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		// 从密匙簿得出密匙进行异或，再转成字符
		$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
	}
	if($operation == 'DECODE') {
		// substr($result, 0, 10) == 0 验证数据有效性
		// substr($result, 0, 10) - time() > 0 验证数据有效性
		// substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16) 验证数据完整性
		// 验证数据有效性，请看未加密明文的格式
		if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	} else {
		// 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
		// 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
		return $keyc.str_replace('=', '', base64_encode($result));
	}
}

/**
 * 根据给定的时间求星期几
 * @param unknown $time
 */
function getweekday($time)
{
	$time || $time = time();
	$weekarray=array("日","一","二","三","四","五","六");
	return "星期".$weekarray[date("w",$time)];
}

/**
 * 循环分页
 * @param string $p 当前页
 * @param string $i 当前序号
 * @param string $numPerPage 每页显示条数
 * @return number
 */
function list_num($i,$p,$numPerPage)
{
	return ($p-1)*$numPerPage+$i;
}


/**
 * 描述:产生随机字符串
 * @param res
 * @return json数据
 * @author 高友龙
 * 创建时间  <2014/09/03>
 */
function rand_str($len = 6,$format = 'ALL') {
	switch($format) {
		case 'ALL':
			$chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~'; break;
		case 'CHAR':
			$chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-@#~'; break;
		case 'NUMBER':
			$chars='0123456789'; break;
		case 'CHARNUM':
			$chars='0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'; break;
		default :
			$chars='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~';
			break;
	}
	mt_srand((double)microtime()*1000000*getmypid());
	$password="";
	while(strlen($password)<$len)
		$password.=substr($chars,(mt_rand()%strlen($chars)),1);
	return $password;
}

/**
 * 获取指定月份的第一天开始和最后一天结束的时间戳
 *
 * @param int $y 年份 $m 月份
 * @return array(本月开始时间，本月结束时间)
 */
function mFristAndLast($y="",$m=""){
	if($y=="") $y=date("Y");
	if($m=="") $m=date("m");
	$m=sprintf("%02d",intval($m));
	$y=str_pad(intval($y),4,"0",STR_PAD_RIGHT);

	$m>12||$m<1?$m=1:$m=$m;
	$firstday=strtotime($y.$m."01000000");
	$firstdaystr=date("Y-m-01",$firstday);
	$lastday = strtotime(date('Y-m-d 23:59:59', strtotime("$firstdaystr +1 month -1 day")));
	return array("firstday"=>$firstday,"lastday"=>$lastday);
}

function num2char($num,$mode=true){
	$char = array('零','一','二','三','四','五','六','七','八','九');
	//$char = array('零','壹','贰','叁','肆','伍','陆','柒','捌','玖);
	$dw = array('','十','百','千','','万','亿','兆');
	//$dw = array('','拾','佰','仟','','萬','億','兆');
	$dec = '点';  //$dec = '點';
	$retval = '';
	if($mode){
		preg_match_all('/^0*(\d*)\.?(\d*)/',$num, $ar);
	}else{
		preg_match_all('/(\d*)\.?(\d*)/',$num, $ar);
	}
	if($ar[2][0] != ''){
		$retval = $dec . ch_num($ar[2][0],false); //如果有小数，先递归处理小数
	}
	if($ar[1][0] != ''){
		$str = strrev($ar[1][0]);
		for($i=0;$i<strlen($str);$i++) {
			$out[$i] = $char[$str[$i]];
			if($mode){
				$out[$i] .= $str[$i] != '0'? $dw[$i%4] : '';
				if($str[$i]+$str[$i-1] == 0){
					$out[$i] = '';
				}
				if($i%4 == 0){
					$out[$i] .= $dw[4+floor($i/4)];
				}
			}
		}
		$retval = join('',array_reverse($out)) . $retval;
	}
	return $retval;
}

/**
 * 多维数组排序
 *
 */
function arr_sort($array,$key,$order="asc"){//asc是升序 desc是降序

	$arr_nums=$arr=array();

	foreach($array as $k=>$v){

		$arr_nums[$k]=$v[$key];

	}

	if($order=='asc'){

		asort($arr_nums);

	}else{

		arsort($arr_nums);

	}

	foreach($arr_nums as $k=>$v){

		$arr[$k]=$array[$k];

	}

	return $arr;

}

//此函数可以去掉空格，及换行。
function trimall($str)
{
	$qian=array(" ","　","\t","\n","\r");
	$hou=array("","","","","");
	return str_replace($qian,$hou,$str);
}
