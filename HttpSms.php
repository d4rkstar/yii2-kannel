<?php
/**
 * Created by PhpStorm.
 * User: brunosalzano
 * Date: 14/01/16
 * Time: 22:22
 */

namespace d4rkstar\kannel;

use Yii;
use yii\base\Component;
use yii\base\Exception;

class HttpSms extends Component {

    // Message gets successfully delivered.
    const DLR_DELIVERY_SUCCESS = 1;

    // Message gets accepted by the SMSC but the phone rejects the message.
    const DLR_DELIVERY_FAILURE = 2;

    // SMSC can not reach the phone and thus returns a buffered message
    const DLR_DELIVERY_BUFFERED = 4;

    // Message gets accepted by the SMSC but the phone is off or out of reach.
    const DLR_DELIVERY_SMSC_SUCCESS = 8;

    // Message gets rejected by the SMSC (unknown subscriber, invalid destination number etc).
    const DLR_DELIVERY_SMSC_REJECT = 16;

    const DLR_ALL = self::DLR_DELIVERY_SUCCESS+self::DLR_DELIVERY_FAILURE+self::DLR_DELIVERY_BUFFERED+
        self::DLR_DELIVERY_SMSC_SUCCESS + self::DLR_DELIVERY_SMSC_REJECT;

    /**
     * @var string Kannel HTTP Host
     */
    public $host = '';

    /**
     * @var int Kannel HTTP Port
     */
    public $port = 13013;

    /**
     * @var string Kannel HTTP Username
     */
    public $username = '';

    /**
     * @var string Kannel HTTP Password
     */
    public $password = '';

    /**
     * @var int Delivery Mask
     * @see http://www.kannel.org/download/kannel-userguide-snapshot/userguide.html#delivery-reports
     */
    public $dlrMask = 0;

    /**
     * @var string Delivery Url
     */
    public $dlrUrl = '';


    public $checkUrl = '';


    public function getGUID($parenthesis=true){
        if (function_exists('com_create_guid')){
            return com_create_guid();
        }else{
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = ($parenthesis ? chr(123) : "")// "{"
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .($parenthesis ? chr(125) : "");// "}"
            return $uuid;
        }
    }

    /**
     *
     * @return int
     * @throws Exception
     */
    public function checkKannel() {
        if (Yii::$app->params['smsDemoMode']==true) return 2;
        if (empty($this->checkUrl)) {
            throw new Exception("Cannot check if kannel status without a checkUrl");
        }
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->checkUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $output = curl_exec($ch);
            curl_close($ch);

            $obj = json_decode($output);
            Yii::info('Check kannel output is: ' . print_r($obj, true));
            if (is_object($obj) && isset($obj->numOfProcesses) ) {
                if ($obj->socket != true) return 0;
                return $obj->numOfProcesses;
            } else {
                return 0;
            }
        }
        catch(Exception $e) {
            return 0;
        }
    }

    /***
     * @param $id string - Application Id for the message. The id will be used to build the URL for the delivery report
     * @param $sender string - SMS sender phone number or name
     * @param $recipient string - SMS recipient phone number
     * @param $body string - Body of the SMS
     * @return array - Array with following keys: 'code' = http_code, 'content' = http_body, 'error' = curl http error
     * @throws Exception
     */
    public function sendSingleSms($id, $sender, $recipient, $body) {

        if (empty($sender)) {
            throw new Exception("Parameter sender is mandatory");
        }
        if (empty($recipient)) {
            throw new Exception("Parameter recipient is mandatory");
        }
        if (empty($body)) {
            throw new Exception("Parameter body is mandatory");
        }

        $kannelUrl = 'http://%s:%d/cgi-bin/sendsms'; // ?username=%s&password=%s&from=%s&to=%s&text=%s';
        $formatParams = [];
        if (!empty($this->username)) {
            $formatParams[] = sprintf('username=%s',$this->username);
        }
        if (!empty($this->password)) {
            $formatParams[] = sprintf('password=%s',$this->password);
        }
        if (!empty($sender)) {
            $formatParams[]=sprintf('from=%s',urlencode($sender));
        }
        if (!empty($recipient)) {
            $formatParams[]=sprintf('to=%s',urlencode($recipient));
        }
        if (!empty($body)) {
            $formatParams[]=sprintf('body=%s',urlencode($body));
        }

        if ($this->dlrMask>0) {

            if (empty($this->dlrUrl)) {
                throw new Exception("Can use a dlrMask<>0 without a dlrUrl");
            }
            if (empty($id)) {
                throw new Exception("Can use a dlrMask<>0 without a non empty id");
            }

            $deliveryurl = sprintf($this->dlrUrl, $id, '%d');
            $formatParams[] = sprintf('dlrurl=%s',urlencode($deliveryurl));
            $formatParams[] = sprintf('dlrmask=%d',$this->dlrMask);

        }

        $url = vsprintf($kannelUrl, [$this->host,$this->port]).'?'.implode('&', $formatParams);

        Yii::info('Build url: ' . $url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, false);    // we want headers
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $url);


        $http_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $http_error = curl_error($ch);
        curl_close($ch);

        /*
         Status	Body	                            Meaning
        ------------------------------------------------------------
         202	0: Accepted for delivery            The message has been accepted and is delivered onward to a SMSC driver. Note that this status does not ensure that the intended recipient receives the message.
         202	3: Queued for later delivery	    The bearerbox accepted and stored the message, but there was temporarily no SMSC driver to accept the message so it was queued. However, it should be delivered later on.
         4xx	(varies)                            There was something wrong in the request or Kannel was so configured that the message cannot be in any circumstances delivered. Check the request and Kannel configuration.
         503	Temporal failure, try again later.  There was tempor    al failure in Kannel. Try again later.
         */

        return [
            'code'=>$http_code,
            'content'=>$http_content,
            'error'=>$http_error,
        ];

    }
}