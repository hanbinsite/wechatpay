<?php
namespace app\api\model;

use think\Cache;
use think\Db;
use think\Model;

class Wechatrefund extends Model
{
    private static $appid  = "wx74a0aa18c6303353";
    private static $mch_id = "1497418362";
    private static $body   = "鲜游舟山拼团过期退款";
    private static $fee    = "0.01";
    private static $order  = "";
    private static $ip     = "120.26.9.106";
    private static $openid = "";
    private static $key    = "9xxzKAlloXhKhHZMGSp674iphLoFGgF8";
    private static $url    = "http://xianyouzhoushan.xiangchengnetwork.com/api/task/receiverefund";
    private static $sslcert= "cert/apiclient_cert.pem";
    private static $sslkey = "cert/apiclient_key.pem";
    private static $posturl= "https://api.mch.weixin.qq.com/secapi/pay/refund";
    private static $transaction_id = "";
    private static $refund_id = "";


    //微信申请开始
    public static function refund($id=0)
    {
        if ($id<=0)
        {
            return 0;
        }
        $info = Db::table("xyzx_refundapply")->where("id",$id)->where("state",1)->where("result","")->find();
        if (!$info)
        {
            return 0;
        }
        $openid = Db::table("xyzx_members")->where("id",$info['members_members_id'])->value("openid");
        self::$openid = $openid;
        //self::$body   = "";
        self::$fee    = floatval($info['money']*100);
        self::$order  = $info['paynumber'];
        self::$refund_id    = $info['refundnumber'];
        self::$transaction_id = $info['transaction_id'];
        $return = self::pay();
        if ($return)
        {
            if ($return['return_code']=="SUCCESS" && $return['result_code']=="SUCCESS")
            {
                Db::table("xyzx_refundapply")->where("id",$id)->update(['state'=>3,'result'=>json_encode($return),'updatetime'=>time()]);
            }
            else
            {
                Db::table("xyzx_refundapply")->where("id",$id)->update(['state'=>4,'result'=>json_encode($return),'updatetime'=>time()]);
            }

            return 1;
        }
        else
        {
            Db::table("xyzx_refundapply")->where("id",$id)->update(['state'=>4,'updatetime'=>time()]);
            return 0;
        }

//        return json_encode($return);
    }
    private static function pay()
    {
        $return  = self::unifiedorder();
        return $return;
    }

    private static function unifiedorder()
    {
        //$url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $parameters = array(
            "appid" => self::$appid,
            "mch_id"=> self::$mch_id,
            "nonce_str" =>self::createNoncestr() ,
            "refund_desc" =>self::$body,//退款原因
            "out_trade_no"=>self::$order,//支付时系统生成订单号
            "transaction_id"=>self::$transaction_id,//微信支付时微信返回订单号
            "out_refund_no"=>self::$refund_id,//退款申请时系统生成的退款单号
            "total_fee"=>self::$fee,
            "refund_fee"=>self::$fee,
            "spbill_create_ip"=>self::$ip,
            //"notify_url" => 'http://www.weixin.qq.com/wxpay/pay.php',
            "notify_url" => self::$url
            //"openid"   => self::$openid,
            //"trade_type"=>"JSAPI"
        );
        $parameters['sign']  = self::getSign($parameters);
        $xmlData =self::arrayToXml($parameters);
        $return  =self::xmlToArray(self::postXmlCurl($xmlData,self::$posturl,60));
        return $return;
    }
    //产生随机字符串，不大于32位
    private static function createNoncestr($length = 32)
    {
        $chars  =  "abcdefghijklmnopqrstuvwxyz0123456789";
        $str    =  "";
        for ($i = 0;$i<$length;$i++)
        {
            $str .= substr($chars,mt_rand(0,strlen($chars)-1),1);
        }
        return $str;
    }
    //生成签名
    private static function getSign($obj)
    {
        foreach ($obj as $key => $val)
        {
            $Parameters[$key] = $val;
        }
        ksort($Parameters);
        $String =self::formatBizQueryParaMap($Parameters,false);
        $String =$String."&key=".self::$key;
        $String =md5($String);
        $result_ = strtoupper($String);
        return $result_;
    }
    //格式化参数
    private static function formatBizQueryParaMap($paraMap,$urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap  as $k => $v)
        {
            if ($urlencode)
            {
                $v = urlencode($v);
            }
            $buff .= $k ."=".$v."&";
        }
        $reqPar = "";
        if (strlen($buff)>0)
        {
            $reqPar = substr($buff,0,strlen($buff)-1);
        }
        return $reqPar;
    }
    //数组转换成xml
    private static function arrayToXml($arr)
    {
        $xml = '<root>';
        foreach ($arr  as $key=>$val)
        {
            if (is_array($val))
            {
                $xml .="<".$key.">".self::arrayToXml($val)."</".$key.">";
            }
            else
            {
                $xml .="<".$key.">".$val."</".$key.">";
            }
        }
        $xml.= "</root>";
        return $xml;
    }
    //xml转换成数组
    private static function xmlToArray($xml)
    {
        libxml_disable_entity_loader(true);
        $xmlstring = simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NOCDATA);
        $val = json_decode(json_encode($xmlstring),true);
        return $val;
    }
    private static function postXmlCurl($xml,$url,$second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        //设置证书
        //使用证书：cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT, self::$sslcert);
        //默认格式为PEM，可以注释
        curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY, self::$sslkey);

        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            return 0;
//            throw new WxPayException("curl出错，错误码:$error");
        }
    }
    //微信退款结束

    public static function getrefundnumber()
    {
        $number = self::generatenumber();
        $bool   = 1;
        while ($bool)
        {
            $count  = Db::table("xyzx_refundapply")->where("refundnumber",$number)->count();
            if ($count)
            {
                $number = self::generatenumber();
            }
            else
            {
                $bool   = 0;
            }
        }
        return $number;
    }
    private static function generatenumber()
    {
        $random = rand(100000000,999999999);
        $number = "T".date("YmdHis",time()).$random;
        return $number;
    }
}