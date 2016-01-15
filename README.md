## yii2-kannel

This widget will let you send SMS through the HTTP interface exposed by Kannel (http://www.kannel.org/download/kannel-userguide-snapshot/userguide.html#AEN5058).


## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/). 

### Install

Either run

```
$ php composer.phar require d4rkstar/yii2-kannel "dev-master"
```

or add

```
"d4rkstar/yii2-kannel": "dev-master"
```

to the ```require``` section of your `composer.json` file.

### Sample Usage

In the section ```components``` of your `app/config/web.php`, add:

```
'components' => [
    ...
    'kannel' => require(__DIR__ . '/kannel.php'),
]
```

Now, add a configuration file named `app/config/kannel.php`, and add:

```
<?php
use d4rkstar\kannel\HttpSms;

return [
    'class'=>'d4rkstar\kannel\HttpSms',
    'host'=>'127.0.0.1', // replace with your kannel host IP
    'port'=>13013, // replace with your kannel host Port
    'username'=>'kanneluser', // replace with your kannel User
    'password'=>'kannelpass', // replace with your kannel Password
    'dlrMask'=>HttpSms::DLR_ALL, // delivery notifications (read more below)
    'dlrUrl'=>'http://127.0.0.1/delivery.php?id=%s&status=%s' // callback delivery URL 
];
?>
```

Now, anywhere in your application, you can send an SMS:

```
<?php
    $sms_id = 1; // <-- replace this ID with the Id of your DB or unique identifier of the sms
    $from = ''; <-- put your SMS sender number here
    $to = ''; <-- put SMS recipient number here
    $body = 'Hello world!'; 
    $result  = Yii::$app->kannel->sendSingleSms($id, $from, $to, $body);
    
    switch ($result['code']) {
        case '202':
            echo "Success!";
        default:
            echo "Failure!";
    }
?>
```

### Return Values

| code | content                             | meaning |
|------|-------------------------------------|---------|
| 202  | 0: Accepted for delivery            | The message has been accepted and is delivered onward to a SMSC driver. Note that this status does not ensure that the intended recipient receives the message. |
| 202  | 3: Queued for later delivery        | The bearerbox accepted and stored the message, but there was temporarily no SMSC driver to accept the message so it was queued. However, it should be delivered later on. |
| 4xx  | (varies)                            | There was something wrong in the request or Kannel was so configured that the message cannot be in any circumstances delivered. Check the request and Kannel configuration. |
| 503  | Temporal failure, try again later.  |  There was temporal failure in Kannel. Try again later. |

## Delivery Reports

If you set dlrMask and dlrUrl, the HTTP request will ask Kannel to report the delivery status for the SMS. 
Delivery reports are sent to your application through a web callback URL: based on the type of report, Kannel will call the URL you specified in dlrUrl.
So, if the URL carry in an ID parameter, the callback will carry this ID too plus the type of delivery report id.

Delivery reports are:

| constant                            | value | meaning |
|-------------------------------------|------:|---------|
| HttpSms::DLR_DELIVERY_SUCCESS       | 1  | Message gets successfully delivered. |
| HttpSms::DLR_DELIVERY_FAILURE       | 2  | Message gets accepted by the SMSC but the phone rejects the message. |
| HttpSms::DLR_DELIVERY_BUFFERED      | 4  | SMSC can not reach the phone and thus returns a buffered message |
| HttpSms::DLR_DELIVERY_SMSC_SUCCESS  | 8  | Message gets accepted by the SMSC but the phone is off or out of reach. |
| HttpSms::DLR_DELIVERY_SMSC_REJECT   | 16 | Message gets rejected by the SMSC (unknown subscriber, invalid destination number etc). |
| HttpSms::DLR_ALL                    | 31 | All delivery notifications |
