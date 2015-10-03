<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
  * wechat api
*/

//define token
define("TOKEN", "398385690");

class Api extends MY_Controller {
	public function __construct() {
		parent::__construct();

		// $this->load->model('Model_gifts');
		// $this->load->model('Model_gift_content');
		// $this->load->model('Model_players');
	}

	public function index(){
		echo "Hello.\n";
	}

	public function receive_wx_msg() {
		if (isset($_GET['echostr'])) {
			$this->valid();
		}else{
			$this->responseMsg();
		}
	}

	public function valid() {
		$echoStr = $_GET["echostr"];
		if($this->checkSignature()){
			echo $echoStr;
			exit;
		}
	}

	public function responseMsg() {
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
		if (!empty($postStr)) {
			$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
			$RX_TYPE = trim($postObj->MsgType);
			switch ($RX_TYPE)
			{
				case "text":
					$resultStr = $this->receiveText($postObj);
					break;
				case "event":
					$resultStr = $this->receiveEvent($postObj);
					break;
				default:
					$resultStr = "unknow msg type: ".$RX_TYPE;
					break;
			}
			echo $resultStr;
		}else {
			echo "";
			exit;
		}
	}

	private function receiveEvent($object) {
		$content_str = '';
		$gift_num = '';
		$wx_id = (array)$object->ToUserName;
		switch ($object->Event)
		{
			case "subscribe":
				$gift_type = MY_Gift_Num::get_gift_type($wx_id[0], 'subscribe');
				$gift_num = $this->subscribe_gift_num($gift_type['game_name'], $gift_type['gift_prefix'],$gift_type['type'],$gift_type['sub_type']);
				$welcome_msg = MY_Prompt_Msg::get_subscribe_msg($gift_type['game_name']);
				$content_str = $welcome_msg.$gift_num;
				break;
		}
		$resultStr = $this->transmitText($object, $content_str);
		return $resultStr;
	}

	public function receiveText($object) {
		$fromUsername = $object->FromUserName;
		$toUsername = $object->ToUserName;
		$keyword = trim($object->Content);
		$time = time();
		$textTpl = "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[%s]]></MsgType>
					<Content><![CDATA[%s]]></Content>
					<FuncFlag>0</FuncFlag>
					</xml>";
		if($keyword == "?" || $keyword == "？") {
			$msgType = "text";
			$contentStr = date("Y-m-d H:i:s",time());
			$resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
			echo $resultStr;
		}
	}

	private function transmitText($object, $content) {
		if (!isset($content) || empty($content))
			return "";

		$textTpl = "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[text]]></MsgType>
					<Content><![CDATA[%s]]></Content>
					</xml>";
		$result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content);
		return $result;
	}

	private function checkSignature() {
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];

		$token = TOKEN;
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );

		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}
	}

	private function subscribe_gift_num($game_name, $gift_prefix, $type, $sub_type) {
		// 产生1~5位随机数
		$random_part = self::create_random_part();
		$gift_arr = array('random_part'=>$random_part,'type'=>$type,'sub_type'=>$sub_type);
		$ret_gift = $this->Model_gifts->create_gift_num($game_name, $gift_arr);
		$base62_id = Base62::encode($ret_gift['id']);
		$base62_random_part = Base62::encode($ret_gift['random_part']);
		$gift_num = $gift_prefix."-".$base62_id."-".$base62_random_part."-".$ret_gift['type']."-".$ret_gift['sub_type'];
		return $gift_num;
	}

	public function create_random_part() {
		$random_part = mt_rand(0, 65536);
		return $random_part;
	}
}