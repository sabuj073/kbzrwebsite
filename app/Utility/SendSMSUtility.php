<?php

namespace App\Utility;

use App\Models\OtpConfiguration;
use App\Utility\MimoUtility;
use Twilio\Rest\Client;

class SendSMSUtility
{
    
    
    public static function getToken(){
        $curl = curl_init();
                      $code = base64_encode("korearbazar:98d33b73893aa95f9d5878806a3f551b");  //tried by removing spaces before and after of api key but output same
                      curl_setopt_array($curl, array(
                      CURLOPT_URL => "https://sms.gabia.com/oauth/token",
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => "",
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 0,
                      CURLOPT_FOLLOWLOCATION => false,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => "POST",
                      CURLOPT_POSTFIELDS => "grant_type=client_credentials",
                      CURLOPT_HTTPHEADER => array(
                      "Content-Type: application/x-www-form-urlencoded",
                      "Authorization:Basic ".$code
                      ),
                      ));

                      $response = curl_exec($curl);
                      $err = curl_error($curl);

                      curl_close($curl);

                      if ($err) {
                        dd("cURL Error #:" . $err);
                      } else {
                        return json_decode($response)->access_token;
                      } 
    }
    
    
    public static function sendSMS($to, $from, $text, $template_id)
    {
             $token = self::getToken();
             $to = str_replace("+82","",$to);
             if(strlen($to)==10){
                 $to = "0".$to;
             }
            //  $to = substr($to, 3);
                      $curl = curl_init();
                      $code = base64_encode("korearbazar:".$token);
                      curl_setopt_array($curl, array(
                      CURLOPT_URL => "https://sms.gabia.com/api/send/lms",
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => "",
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 0,
                      CURLOPT_FOLLOWLOCATION => false,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => "POST",
                      CURLOPT_POSTFIELDS =>
                      "phone=".$to."&callback=01027293780&message=".$text."&refkey=".$code."&subject=Korearbazar",
                      CURLOPT_HTTPHEADER => array(
                      "Content-Type: application/x-www-form-urlencoded",
                      "Authorization: Basic ".$code,
                      ),
                      ));

                      $response = curl_exec($curl);
                      $err = curl_error($curl);

                      curl_close($curl);

                      if ($err) {
                      echo "cURL Error #:" . $err;
                      } else {
                        return json_decode($response);
                      }
                      return true;
    }
}
