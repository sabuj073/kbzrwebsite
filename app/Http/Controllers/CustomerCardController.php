<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomerCard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class CustomerCardController extends Controller
{
    // Store a new card
    public function store(Request $request)
    {
        $request->validate([
            'card_number' => 'required|string',
            'expiry_month' => 'required',
            'expiry_year' => 'required',
        ]);

        $cardType = $this->getCardType($request->input('card_number'));
        
        
        
        
         if(auth()->user() != null) {
            $user_id = Auth::user()->id;
            $card = CustomerCard::create([
            'user_id' => $user_id,
            'card_number' => $request->input('card_number'), // Encrypt the card number
            'expiry_month' => $request->input('expiry_month'),
            'expiry_year' => $request->input('expiry_year'),
            'cvc' => $request->input('card_cvc'),
            'card_type' => $cardType,
        ]);
           
         } else {
            if($request->session()->get('temp_user_id')) {
                $temp_user_id = $request->session()->get('temp_user_id');
            } else {
                $temp_user_id = bin2hex(random_bytes(10));
                $request->session()->put('temp_user_id', $temp_user_id);
            }
            
            
            $card = CustomerCard::create([
            'temp_user_id' => $temp_user_id,
            'card_number' => $request->input('card_number'), // Encrypt the card number
            'expiry_month' => $request->input('expiry_month'),
            'expiry_year' => $request->input('expiry_year'),
            'cvc' => $request->input('card_cvc'),
            'card_type' => $cardType,
        ]);
           
        }
        
        
        
        
        

        return response()->json(['success' => true, 'card' => $card]);
    }

    // Retrieve all saved cards for the user
public function getCards(Request $request)
{
    if (auth()->check()) {
        $user_id = auth()->user()->id;
        $cards = CustomerCard::where('user_id', $user_id)->get();
    } else {
        if ($request->session()->has('temp_user_id')) {
            $temp_user_id = $request->session()->get('temp_user_id');
        } else {
            $temp_user_id = bin2hex(random_bytes(10));
            $request->session()->put('temp_user_id', $temp_user_id);
        }

        $cards = CustomerCard::where('temp_user_id', $temp_user_id)->get();
    }

    foreach ($cards as $card) {
        try {
            // Check if the card number exists and is not null
            if (!empty($card->card_number)) {
                $decryptedCardNumber = $card->card_number;
                $card->card_number = '**** **** **** ' . substr($decryptedCardNumber, -4); // Mask the card number
            } else {
                $card->card_number = 'No card number available';
            }
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Handle decryption failure
            $card->card_number = 'Decryption failed';
        }
    }

    return response()->json(['success' => true, 'cards' => $cards]);
}


public function getCarddetails($id)
{
   
    $card = CustomerCard::where('id', $id)->firstOrFail();
    return response()->json(['success' => true, 'card' => $card]);
}




    // Update a card
    public function update(Request $request, $id)
    {
        $request->validate([
            'card_number' => 'required|string',
            'expiry_month' => 'required',
            'expiry_year' => 'required',
        ]);

        $card = CustomerCard::where('id', $id)->firstOrFail();

        $card->card_number = $request->input('card_number');
        $card->expiry_month = $request->input('expiry_month');
        $card->expiry_year = $request->input('expiry_year');
        $card->cvc = $request->input('card_cvc');
        $card->save();

        return response()->json(['success' => true, 'card' => $card]);
    }

    // Delete a card
    public function delete($id)
    {
        $card = CustomerCard::where('id', $id)->firstOrFail();
        $card->delete();

        return response()->json(['success' => true]);
    }

    // Utility function to get card type
    private function getCardType($cardNumber)
    {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);

        if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $cardNumber)) {
            return 'Visa';
        } elseif (preg_match('/^5[1-5][0-9]{14}$/', $cardNumber)) {
            return 'MasterCard';
        } elseif (preg_match('/^3[47][0-9]{13}$/', $cardNumber)) {
            return 'American Express';
        } elseif (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $cardNumber)) {
            return 'Discover';
        } else {
            return 'Unknown';
        }
    }
}

