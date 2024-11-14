<?php
namespace App\Http\Controllers;
use App\Models\BusinessSetting;
use Session;

# IF BROWSE FROM LOCAL HOST, KEEP true
if (!defined("SSLCZ_IS_LOCAL_HOST")) {
    define("SSLCZ_IS_LOCAL_HOST", true);
}

class SSLCommerz
{
    protected $sslc_submit_url;
    protected $sslc_validation_url;
    protected $sslc_mode;
    protected $sslc_data;
    protected $store_id;
    protected $store_pass;
    public $error = "";

    public function __construct()
    {
        if (Session::has("payment_type")) {
            # IF SANDBOX TRUE, THEN IT WILL CONNECT WITH SSLCOMMERZ SANDBOX (TEST) SYSTEM
            if (
                BusinessSetting::where("type", "sslcommerz_sandbox")->first()
                    ->value == 1
            ) {
                define("SSLCZ_IS_SANDBOX", true);
            } else {
                define("SSLCZ_IS_SANDBOX", false);
            }

            $this->setSSLCommerzMode(SSLCZ_IS_SANDBOX ? 1 : 0);
            $this->store_id = env("SSLCZ_STORE_ID");
            $this->store_pass = env("SSLCZ_STORE_PASSWD");
        }
        $this->sslc_submit_url =
            "https://" .
            $this->sslc_mode .
            ".sslcommerz.com/gwprocess/v3/api.php";
        $this->sslc_validation_url =
            "https://" .
            $this->sslc_mode .
            ".sslcommerz.com/validator/api/validationserverAPI.php";
    }

    public function isMobileDevice()
    {
        $userAgent = $_SERVER["HTTP_USER_AGENT"];
        $mobileKeywords = [
            "mobile",
            "android",
            "iphone",
            "ipod",
            "blackberry",
            "windows phone",
        ];

        foreach ($mobileKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    public function initiate($post_data, $get_pay_options = false)
    {
        if (self::isMobileDevice()) {
            $device_type = "mobile";
        } else {
            $device_type = "pc";
        }

        $amount = $post_data["total_amount"];
        $trans_id = $post_data["tran_id"];
        $combined_order = $post_data["value_b"];
        $type = $post_data["value_c"];
        $user_id = $post_data["value_d"];

        if ($type == "wallet_payment") {
            $combined_order =
                $combined_order . ";" . $amount . ";" . substr(uniqid(time(), true), -3);
            $callback = "https://korearbazar.com/payment/wallet/callback";
        } elseif ($type == "customer_package_payment") {
            $combined_order =
                $combined_order .
                ";" .
                $amount .
                ";" .
                $user_id .
                ";" .
                substr(uniqid(time(), true), -3);
            $callback =
                "https://korearbazar.com/payment/customer_package_payment/callback";
        } elseif ($type == "seller_package_payment") {
            $combined_order =
                $combined_order .
                ";" .
                $amount .
                ";" .
                $user_id .
                ";" .
                substr(uniqid(time(), true), -3);
            $callback =
                "https://korearbazar.com/payment/seller_package_payment/callback";
        } else {
            $callback = "https://korearbazar.com/payment/callback";
        }
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://pgapi.easypay.co.kr/api/trades/webpay",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_POSTFIELDS =>
                '{
     "mallId": "05568724",
     "payMethodTypeCode": "11",
     "currency": "00",
     "amount": "' .
                $amount .
                '",
     "clientTypeCode": "00",
     "returnUrl": "' .
                $callback .
                '",
     "deviceTypeCode": "' .
                $device_type .
                '",
     "shopOrderNo": "' .
                $combined_order .
                '",
     "shopValue1" : "' .
                $combined_order .
                '",
     "shopValue2" : "' .
                $amount .
                '",
     "shopValue3" : "' .
                $amount .
                '",
     "shopValue4" : "' .
                $amount .
                '",
     "shopValue5" : "' .
                $amount .
                '",
     "shopValue6" : "' .
                $amount .
                '",
     "shopValue7" : "test",
     "orderInfo": {
     "goodsName": "Korear Bazar Payment"
     }
    }',
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        $response = json_decode($response);
        $url = $response->authPageUrl;
        echo "<script>window.location.href='" . $url . "'</script>";
        //return redirect($url);
    }

    public function orderValidate(
        $trx_id = "",
        $amount = 0,
        $currency = "BDT",
        $post_data
    ) {
        if ($post_data == "" && $trx_id == "" && !is_array($post_data)) {
            $this->error =
                "Please provide valid transaction ID and post request data";
            return $this->error;
        }
        $validation = $this->validate($trx_id, $amount, $currency, $post_data);
        if ($validation) {
            return true;
        } else {
            return false;
        }
    }

    # SEND CURL REQUEST
    protected function sendRequest($data)
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $this->sslc_submit_url);
        curl_setopt($handle, CURLOPT_POST, 1);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $data);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        if (SSLCZ_IS_LOCAL_HOST) {
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        } else {
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2); // Its default value is now 2
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
        }

        $content = curl_exec($handle);

        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        if ($code == 200 && !curl_errno($handle)) {
            curl_close($handle);
            $sslcommerzResponse = $content;

            # PARSE THE JSON RESPONSE
            $this->sslc_data = json_decode($sslcommerzResponse, true);
            return $this;
        } else {
            curl_close($handle);
            $msg = "FAILED TO CONNECT WITH SSLCOMMERZ API";
            $this->error = $msg;
            return false;
        }
    }

    # SET SSLCOMMERZ PAYMENT MODE - LIVE OR TEST
    protected function setSSLCommerzMode($test)
    {
        if ($test) {
            $this->sslc_mode = "sandbox";
        } else {
            $this->sslc_mode = "securepay";
        }
    }

    # VALIDATE SSLCOMMERZ TRANSACTION
    protected function validate(
        $merchant_trans_id,
        $merchant_trans_amount,
        $merchant_trans_currency,
        $post_data
    ) {
        # MERCHANT SYSTEM INFO
        if ($merchant_trans_id != "" && $merchant_trans_amount != 0) {
            # CALL THE FUNCTION TO CHECK THE RESUKT
            $post_data["store_id"] = $this->store_id;
            $post_data["store_pass"] = $this->store_pass;

            if ($this->SSLCOMMERZ_hash_varify($this->store_pass, $post_data)) {
                $val_id = urlencode($post_data["val_id"]);
                $store_id = urlencode($this->store_id);
                $store_passwd = urlencode($this->store_pass);
                $requested_url =
                    $this->sslc_validation_url .
                    "?val_id=" .
                    $val_id .
                    "&store_id=" .
                    $store_id .
                    "&store_passwd=" .
                    $store_passwd .
                    "&v=1&format=json";

                $handle = curl_init();
                curl_setopt($handle, CURLOPT_URL, $requested_url);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

                if (SSLCZ_IS_LOCAL_HOST) {
                    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
                } else {
                    curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2); // Its default value is now 2
                    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);
                }

                $result = curl_exec($handle);

                $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

                if ($code == 200 && !curl_errno($handle)) {
                    # TO CONVERT AS ARRAY
                    # $result = json_decode($result, true);
                    # $status = $result['status'];

                    # TO CONVERT AS OBJECT
                    $result = json_decode($result);
                    $this->sslc_data = $result;

                    # TRANSACTION INFO
                    $status = $result->status;
                    $tran_date = $result->tran_date;
                    $tran_id = $result->tran_id;
                    $val_id = $result->val_id;
                    $amount = $result->amount;
                    $store_amount = $result->store_amount;
                    $bank_tran_id = $result->bank_tran_id;
                    $card_type = $result->card_type;
                    $currency_type = $result->currency_type;
                    $currency_amount = $result->currency_amount;

                    # ISSUER INFO
                    $card_no = $result->card_no;
                    $card_issuer = $result->card_issuer;
                    $card_brand = $result->card_brand;
                    $card_issuer_country = $result->card_issuer_country;
                    $card_issuer_country_code =
                        $result->card_issuer_country_code;

                    # API AUTHENTICATION
                    $APIConnect = $result->APIConnect;
                    $validated_on = $result->validated_on;
                    $gw_version = $result->gw_version;

                    # GIVE SERVICE
                    if ($status == "VALID" || $status == "VALIDATED") {
                        if ($merchant_trans_currency == "BDT") {
                            if (
                                trim($merchant_trans_id) == trim($tran_id) &&
                                abs($merchant_trans_amount - $amount) < 1 &&
                                trim($merchant_trans_currency) == trim("BDT")
                            ) {
                                return true;
                            } else {
                                # DATA TEMPERED
                                $this->error = "Data has been tempered";
                                return false;
                            }
                        } else {
                            //echo "trim($merchant_trans_id) == trim($tran_id) && ( abs($merchant_trans_amount-$currency_amount) < 1 ) && trim($merchant_trans_currency)==trim($currency_type)";
                            if (
                                trim($merchant_trans_id) == trim($tran_id) &&
                                abs($merchant_trans_amount - $currency_amount) <
                                    1 &&
                                trim($merchant_trans_currency) ==
                                    trim($currency_type)
                            ) {
                                return true;
                            } else {
                                # DATA TEMPERED
                                $this->error = "Data has been tempered";
                                return false;
                            }
                        }
                    } else {
                        # FAILED TRANSACTION
                        $this->error = "Failed Transaction";
                        return false;
                    }
                } else {
                    # Failed to connect with SSLCOMMERZ
                    $this->error = "Faile to connect with SSLCOMMERZ";
                    return false;
                }
            } else {
                # Hash validation failed
                $this->error = "Hash validation failed";
                return false;
            }
        } else {
            # INVALID DATA
            $this->error = "Invalid data";
            return false;
        }
    }

    # FUNCTION TO CHECK HASH VALUE
    protected function SSLCOMMERZ_hash_varify($store_passwd = "", $post_data)
    {
        if (
            isset($post_data) &&
            isset($post_data["verify_sign"]) &&
            isset($post_data["verify_key"])
        ) {
            # NEW ARRAY DECLARED TO TAKE VALUE OF ALL POST
            $pre_define_key = explode(",", $post_data["verify_key"]);

            $new_data = [];
            if (!empty($pre_define_key)) {
                foreach ($pre_define_key as $value) {
                    if (isset($post_data[$value])) {
                        $new_data[$value] = $post_data[$value];
                    }
                }
            }
            # ADD MD5 OF STORE PASSWORD
            $new_data["store_passwd"] = md5($store_passwd);

            # SORT THE KEY AS BEFORE
            ksort($new_data);

            $hash_string = "";
            foreach ($new_data as $key => $value) {
                $hash_string .= $key . "=" . $value . "&";
            }
            $hash_string = rtrim($hash_string, "&");

            if (md5($hash_string) == $post_data["verify_sign"]) {
                return true;
            } else {
                $this->error = "Verification signature not matched";
                return false;
            }
        } else {
            $this->error = "Required data mission. ex: verify_key, verify_sign";
            return false;
        }
    }

    # FUNCTION TO GET IMAGES FROM WEB
    protected function _get_image($gw = "", $source = [])
    {
        $logo = "";
        if (!empty($source) && isset($source["desc"])) {
            foreach ($source["desc"] as $key => $volume) {
                if (isset($volume["gw"]) && $volume["gw"] == $gw) {
                    if (isset($volume["logo"])) {
                        $logo = str_replace("/gw/", "/gw1/", $volume["logo"]);
                        break;
                    }
                }
            }
            return $logo;
        } else {
            return "";
        }
    }

    public function getResultData()
    {
        return $this->sslc_data;
    }
}
