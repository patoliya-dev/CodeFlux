<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // Show registration form
    public function create()
    {
        return view('users.create');
    }

    // Store user
    public function register(Request $request)
    {
        // Valid
        $request->validate([
            'name' => 'required|string|max:255',
            // 'encoding' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $name = $request->name;
        $imagePath = $request->file('encoding')->getRealPath();
       
        // API endpoint
        $apiUrl = env('PYTHON_API_URL') . "/register"; 
        
        // Prepare POST data
        $postData = [
            'name' => $name,
            'encoding' => new \CURLFile($imagePath, $request->file('encoding')->getMimeType(), $request->file('encoding')->getClientOriginalName())
        ];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        // Optional: Add headers if required (e.g., Authorization)
        // curl_setopt($ch, CURLOPT_HTTPHEADER, [
        //     'Authorization: Bearer YOUR_API_TOKEN'
        // ]);

        // Execute cURL
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return back()->with('error', 'API Error: ' . $error);
        }

        // You can decode JSON response if needed
        $responseData = json_decode($response, true);

        return back()->with('success', 'User registered via API successfully! Response: ' . $response);
    }
}
