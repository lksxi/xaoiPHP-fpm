<?php
namespace index;

class account extends \xaoi\init{

	public function login(){
		$username = input('post.username');

		$d = [];
		$d['(mobile = ? or number = ?)'] = [$username,$username];
		$d['password'] = \tool\pwd::md5(input('post.password'));
		$d['is_usable'] = 1;
		$user = db('user')->get($d,1);

		if(empty($user))return [-1,null,'账号或密码不正确!'];

		$time = time();
		$token = base64_encode($user['id'].':'.$time.':'.md5($user['id'].$time).base64_encode($user['password']));
		bind('session',new \xaoi\session($token));

		session('user',$user);

		unset($user['password']);

		db('user')->set(['id'=>$user['id']],['last_login_time'=>time()]);

		return [
			0,
			[
				'token'=>[
					'access_token'		=> $token,
					'expires_in'		=> config('xaoi/session.expire')
				],
				'user'=>$user
			]
		];
	}

	/**
	 * 注册
	 */
	public function register(){
		$token = input('post.token');
		bind('session',new \xaoi\session($token));

		$register = session('register');

		$mobile = input('post.mobile');
		$password = \tool\pwd::md5(input('post.password'));
		$number = input('post.number');
		$address_code = input('post.address_code');
		
		$code = input('post.code');

		if($mobile !== $register['mobile'])return [-1,'','请重新获取短信验证码'];

		//验证验证码
		if($code !== $register['code'])return [-1,'','短信验证码错误'];
		
		$info = db('user')->get([
			'mobile'		=> $mobile
		],'count(*) as num',1);
		if($info['num'])return [-1,'','手机号码已使用'];
		
		$info = db('user')->get([
			'number'		=> $number
		],'count(*) as num',1);
		if($info['num'])return [-1,'','警员编号已使用'];

		$data = [];
		$data['mobile']			= $mobile;
		$data['password']		= $password;
		$data['number']			= $number;
		$data['address_code']	= $address_code;
		$data['is_usable']		= 1;

		$data['created_at']		= time();

		db('user')->add($data);

		session('register',null);

		return [0,'','注册成功'];
	}
	

	/**
	 * 发送短信
	 */
	public function send_sms(){
		$mobile = input('post.mobile');

		$token = session_create_id();
		bind('session',new \xaoi\session($token));

		$code = $this->get_code();

		session('register',['code'=>$code,'mobile'=>$mobile]);

		return [0,$token];
	}
	
	private function get_code(){
		return '1234';
	}
}
