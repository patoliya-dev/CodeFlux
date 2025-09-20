<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index() {
        return view('attendance.index');
    }

    public function recognize(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $imagePath = $request->file('image')->getRealPath();
        $imageName = $request->file('image')->getClientOriginalName();

        // API endpoint
        $apiUrl = env('PYTHON_API_URL') . "/attendance";

        $postData = [
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
        \Log::info($responseData);
        if (isset($responseData['error'])) {
            return back()->with('error', $responseData['error']);
        }

        $successMessage = 'Attendance recognized via API successfully!';
        if ($responseData['name'] && $responseData['status']) {
            $successMessage = $responseData['name'] . ' marked ' . $responseData['status'] . ' successfully!';
        }

        return back()->with('success', $successMessage);
    }

    public function report(Request $request)
    {
        $startDate = $request->start_date ?? null;
        $endDate = $request->end_date ?? null;
        $userIds = $request->user_id ?? [];
        $exportType = $request->export ?? 'filter'; // filter | csv | pdf

        // Build query params for API
        $queryParams = [];
        if ($startDate) {
            $queryParams['start_date'] = $startDate;
        }
        if ($endDate) {
            $queryParams['end_date'] = $endDate;
        }
        if ($userIds) {
            $queryParams['user_id'] = implode(',', $userIds);
        }

        // API endpoint
        $apiUrl = env('PYTHON_API_URL') . "/report" . ($exportType != 'filter' ? '/' . $exportType : '');
        if (!empty($queryParams)) {
            $apiUrl .= '?' . http_build_query($queryParams);
        }

        // Call API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

        // Export logic
        if ($exportType === 'csv') {
            $fileName = 'attendance_report_' . now()->format('Ymd_His') . '.csv';
            return response()->streamDownload(function () use ($response) {
                echo $response;
            }, $fileName, [
                'Content-Type' => 'text/csv',
            ]);
        }

        if ($exportType === 'pdf') {
            $fileName = 'attendance_report_' . now()->format('Ymd_His') . '.pdf';
            return response()->streamDownload(function () use ($response) {
                echo $response;
            }, $fileName, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        // Default: normal filter view
        $users = User::select('id', 'name')->get();
        return view('attendance.report', [
            'reportData' => $responseData,
            'users' => $users,
            'userIds' => $userIds,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    public function multipleAttendance() {
        return view('attendance.multiple');
    }

    public function multipleRecognize(Request $request)
    {
        $request->validate([
            'images' => 'required',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $images = $request->file('images');
        $postData = [];

        // Curl in PHP doesn't support multiple identical keys by default in POSTFIELDS,
        // so we manually build the post fields like this:
        foreach ($images as $image) {
            $postData['images[]'] = $postData['images[]'] ?? [];
            $postData['images[]'][] = new \CURLFile(
                $image->getRealPath(),
                mime_content_type($image->getRealPath()),
                $image->getClientOriginalName()
            );
        }

        $flattenedPostData = [];
        foreach ($postData as $key => $files) {
            foreach ($files as $file) {
                $flattenedPostData[$key] = $file; // overwrite to keep key the same for each file
                // To keep multiple identical keys, use numeric keys but with correct names:
                // So change key name to "images[]" on every file:
                $flattenedPostData['images[]'] = $file;
            }
        }

        $flattenedPostData = [];
        foreach ($images as $image) {
            $flattenedPostData[] = new \CURLFile(
                $image->getRealPath(),
                mime_content_type($image->getRealPath()),
                $image->getClientOriginalName()
            );
        }

        $client = new \GuzzleHttp\Client();
        $multipartData = [];
        foreach ($images as $image) {
            $multipartData[] = [
                'name' => 'images',
                'contents' => fopen($image->getRealPath(), 'r'),
                'filename' => $image->getClientOriginalName(),
                'headers'  => ['Content-Type' => mime_content_type($image->getRealPath())]
            ];
        }

        try {
            $response = $client->post(env('PYTHON_API_URL') . "/attendance/multi", [
                'multipart' => $multipartData
            ]);
            $responseData = json_decode($response->getBody()->getContents(), true);

            if (isset($responseData['details'])) {
                $successMessages = [];
                foreach ($responseData['details'] as $detail) {
                    $successMessages[] = $detail['name'] . ' marked ' . $detail['last_status'] . ' at ' . $detail['last_timestamp'];
                }
                return back()->with('success', implode('<br>', $successMessages));
            }

            return back()->with('error', $responseData['message'] ?? 'Unknown error');
        } catch (\Exception $e) {
            return back()->with('error', 'No user found with this face');
        }
    }

}
