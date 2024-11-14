<?php
namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CombinedOrder;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\WalletController;
use App\Models\CustomerPackage;
use App\Models\SellerPackage;
use App\Http\Controllers\CustomerPackageController;
use App\Http\Controllers\SellerPackageController;
use App\Http\Controllers\SSLCommerz;
use App\Models\User;
use App\Models\Order;
use Session;
use Auth;
use DB;
use Illuminate\Support\Facades\Cookie;

session_start();

class PaymentController extends Controller
{
    
    
     public function callback(Request $request){

        $trans_id = $request->shopOrderNo;
        $rescode = $request->resCd;
        
        $order = Order::where('combined_order_id', $trans_id)->first();
        if ($order) {
            $user_id = $order->user_id;
            $user = User::find($user_id);
            if ($user) {
                Auth::login($user);
            }
        }
  
        
        
        if($rescode=='0000'){
            if(self::authenticate($request->authorizationId,$trans_id)=="000"){
                return (new CheckoutController)->checkout_done($trans_id,"Successful");
            }else{
                flash(translate('Payment Failed'))->warning();
                return redirect()->route('home');
            }
            
           
        }else{
            flash(translate('Payment Failed'))->warning();
                return redirect()->route('home');
        }
    }
    
    public function wallet_callback(Request $request){
        $trans_id = $request->shopOrderNo;
        
        $rescode = $request->resCd;
        
        $data  = explode(";",$trans_id);
        $userId = $data[0];
        $amount = $data[1];
 
        
        
        $user = User::find($userId);
        Auth::login($user);
        
        $data['amount'] = $amount;
        $data['payment_method'] = 'onlinepay';
        if($rescode=='0000'){
             if(self::authenticate($request->authorizationId,$trans_id)=="000"){
                return (new WalletController)->wallet_payment_done($data, "");
             }else{
                  flash(translate('Payment Failed'))->warning();
                    return redirect()->route('home');
             }
        }else{
            flash(translate('Payment Failed'))->warning();
            return redirect()->route('home');
        }
        
    }
    
     public function customer_package_payment_callback(Request $request){
        $trans_id = $request->shopOrderNo;
        
        $rescode = $request->resCd;
        
        $data  = explode(";",$trans_id);
        $customer_package_id = $data[0];
        $amount = $data[1];
        $userId = $data[2];
 
        $payment = json_encode($request->all());
        
        $user = User::find($userId);
        Auth::login($user);
        $data['customer_package_id'] = $customer_package_id;
        $data['amount'] = $amount;
        $data['payment_method'] = 'onlinepay';
        if($rescode=='0000'){
             if(self::authenticate($request->authorizationId,$trans_id)=="000"){
                return (new CustomerPackageController)->purchase_payment_done($data, $payment);
             }else{
                  flash(translate('Payment Failed'))->warning();
                    return redirect()->route('home');
             }
        }else{
            flash(translate('Payment Failed'))->warning();
            return redirect()->route('home');
        }
        
    }
    
     public function seller_package_payment_callback(Request $request){
        $trans_id = $request->shopOrderNo;
        
        $rescode = $request->resCd;
        
        $data  = explode(";",$trans_id);
        
        $seller_package_id = $data[0];
        $amount = $data[1];
        $userId = $data[2];
 
        $payment = json_encode($request->all());
        
        
        $user = User::find($userId);
        Auth::login($user);
        $data['seller_package_id'] = $seller_package_id;
        $data['amount'] = $amount;
        $data['payment_method'] = 'onlinepay';
        if($rescode=='0000'){
           
             if(self::authenticate($request->authorizationId,$trans_id)=="000"){
                 return (new SellerPackageController)->purchase_payment_done($seller_package_id, json_decode($payment));
             }else{
                  flash(translate('Payment Failed'))->warning();
                    return redirect()->route('home');
             }
        }else{

            flash(translate('Payment Failed'))->warning();
            return redirect()->route('home');
        }
        
    }
    
    
    
    
    
    public function authenticate($authorizationId,$shopOrderNo){
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://pgapi.easypay.co.kr/api/trades/approval',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "mallId": "05568724",
                "shopTransactionId": "'.$shopOrderNo.'",
                "authorizationId" : "'.$authorizationId.'",
                "shopOrderNo": "'.$shopOrderNo.'",
                "approvalReqDate" : "'.date("Ymd").'"
                
            }',
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Cookie: WMONID=mU_2YxKvBiv'
            ),
));

$response = curl_exec($curl);

curl_close($curl);
$data = json_decode($response,TRUE);
return $data['resCd'];
//echo $response;

    }
    
    


    public function fail()
    {
        echo "failed";
        // $request->session()->forget('order_id');
        // $request->session()->forget('payment_data');
        flash(translate('Payment Failed'))->warning();
        return redirect()->route('home');
    }

     public function cancel(Request $request)
    {
        $request->session()->forget('order_id');
        $request->session()->forget('payment_data');
        flash(translate('Payment cancelled'))->error();
    	return redirect()->route('home');
    }

     public function ipn(Request $request)
    {
        #Received all the payement information from the gateway
      if($request->input('tran_id')) #Check transation id is posted or not.
      {

          $tran_id = $request->input('tran_id');

          #Check order status in order tabel against the transaction id or order id.
          $combined_order = CombinedOrder::findOrFail($request->session()->get('combined_order_id'));

                if($order->payment_status =='Pending')
                {
                    $sslc = new SSLCommerz();
                    $validation = $sslc->orderValidate($tran_id, $order->grand_total, 'BDT', $request->all());
                    if($validation == TRUE)
                    {
                        /*
                        That means IPN worked. Here you need to update order status
                        in order table as Processing or Complete.
                        Here you can also sent sms or email for successfull transaction to customer
                        */
                        echo "Transaction is successfully Complete";
                    }
                    else
                    {
                        /*
                        That means IPN worked, but Transation validation failed.
                        Here you need to update order status as Failed in order table.
                        */

                        echo "validation Fail";
                    }

                }
        }
        else
        {
            echo "Inavalid Data";
        }
    }
}
