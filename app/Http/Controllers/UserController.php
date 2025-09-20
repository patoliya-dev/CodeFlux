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
        // Validate input
        $request->validate([
            'name' => 'required|string|max:255|unique:users,name',
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $name = $request->name;
        $imagePath = $request->file('image')->getRealPath();
        $imageName = $request->file('image')->getClientOriginalName();

        // API endpoint
        $apiUrl = env('PYTHON_API_URL') . "/register";

        $postData = [
            'name' => $name,
            'image' => new \CURLFile($imagePath, mime_content_type($imagePath), $imageName)
        ];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        // Execute
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            return back()->with('error', 'API Error: ' . $error);
        }

        $responseData = json_decode($response, true);

        if (isset($responseData['error'])) {
            return back()->with('error', $responseData['error']);
        }

        return back()->with('success', $responseData['message'] ?? 'User registered via API successfully!');
    }
}
