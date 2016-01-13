<?php

namespace Think;

use Think\Controller;

class BaseController extends Controller 
{
	private $agentid = '';
	protected $weObj;
	
	protected  $userid;
	protected  $numPerPge = '15';
	
	//初始化类库
	public function _initialize($agentid)
	{
		import('Org.Nexstep.qywechatclass','','.php');
		import('Org.Nexstep.qyerrCode','','.php');
		$this->agentid = $agentid;
		$this->getObj();
		$code = I('code');
		if($code)
		{
			if(!cookie('USERID'))
			{
				$userid = $this->weObj->getUserId($code);
				cookie('USERID',$userid['UserId']);
			}
		}
		
		$this->userid = cookie('USERID');

		//查看session 积分等级
		$grade = session('gradename');
		if(!$grade)
		{
			getGrade();
		}
	}


	//根据userid获取用户相应等级
	/*private function usergrade(){

	}*/
	
	
	private function getObj(){
		$options = array(
				'token'=>C('token'),	//填写应用接口的Token
				'encodingaeskey'=>C('encodingaeskey'),//填写加密用的EncodingAESKey
				'appid'=>C('appid'),	//填写高级调用功能的appid
				'appsecret'=>C('appsecret'), //填写高级调用功能的密钥
				'agentid'=>$this->agentid //应用的id
		);
	
		$this->weObj = new \Wechat($options);
	}
	
	protected function lists($model,$where = array(),$base = array(),$order,$p = 1,$numPerPage,$field,$group)
	{
		if(is_string($model))
		{
			$model = M($model);
		}
		$where = array_filter ( array_merge ( ( array ) $base, ( array ) $where ), function ($val) {
			if ($val === '' || $val === null) {
				return false;
			} else {
				return true;
			}
		} );
		if(empty($order))
		{
			$order = 'id desc';
		}
		if(empty($p))
		{
			$p = 1;
		}
		if(empty($numPerPage))
		{
			$numPerPage = 30;
		}
		if(empty($field))
		{
			$field = '*';
		}
		$count = $model->where($where)->count();
	
		$sql = $model->getLastSql();
		if(empty($group))
		{
			$list = $model->where($where)->order($order)->page($p,$numPerPage)->field($field)->select();
		}
		else
		{
			$list = $model->where($where)->group($group)->order($order)->page($p,$numPerPage)->field($field)->select();
		}
	
		return array('count'=>$count,'list'=>$list,'sql'=>$model->getLastSql());
	}
	
	
	/**
	 *  通过微信上传图片处理并保存到本地服务器返回本地服务器图片地址,多地址以@符号分隔
	 * @param $pic_urls  以','分隔的微信服务器图片地址
	 */
	protected function wx_upload($pic_urls)
	{
		$dir = C('WEIXIN_UPLOAD').date('Y_m_d').'/';//为方便管理图片 保存图片时 已时间作一层目录作区分
		if(!file_exists($dir)){
			mkdir($dir);
		}
		$mediaids = array_filter(explode(',', $pic_urls));
		$url = '';
		foreach ($mediaids as $mediaid)
		{
			$fileinfo = $this->weObj->downloadWeixinFile($mediaid);
			$time = time().substr($mediaid, 9,3);
			$filename = $dir.'wx_'.$time.'.jpg';   //定义图片文件名
			$this->weObj->saveWeixinFile($filename, $fileinfo['body']);
			$url .= $filename.'@';
		}
		return $url;
	}
	
	
	protected function jsSign()
	{
		$auth = $this->weObj->checkAuth();
		$js_ticket = $this->weObj->getJsTicket();
		if (!$js_ticket) {
			return false;
		}
		$url = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		$js_sign = $this->weObj->getJsSign($url);
		return $js_sign;
	}
}

?>