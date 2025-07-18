<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use App\Models\McuFilemanager;
use Illuminate\Http\Request;
use App\Models\McuFinding;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Bangsamu\Master\Models\Setting;


class SupportTicketController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function getAppSetting($valueTicket)
    {
        return Cache::rememberForever('setting.app.ticket.'.$valueTicket, function () use($valueTicket) {
            // Ambil dari database
            $setting = Setting::where('name', $valueTicket)
                ->where('category', 'support_ticket')
                ->whereNull('deleted_at')
                ->value('value');
            // dd($setting,$valueTicket);
            // Jika tidak ada, fallback ke config
            return $setting;
        });
    }

    public function ticketEmail(Request $request)
    {
        $apiUrl = $this->getAppSetting('app.ticket');//?: config('app.ticket', 'http://192.168.16.205:9016');
        $appCode = $this->getAppSetting('app.APP_CODE');//?: config('SsoConfig.main.APP_CODE')
        $search = $this->getAppSetting('app.search');

        $perPage = 10; // Sesuai dengan logika pagination pada response
        $page = max(1, intval($request->query('page', 1))); // Ambil page dari query, default ke 1
        $offset = ($page - 1) * $perPage;
        // dd($apiUrl);

        $url = $apiUrl . '/api/tickets';
        $params = [
            'limit' => $perPage,
            'offset' => $offset,
            'app_code' => $appCode,
            'search' => $search,
            'order_by' => 'status,created_at',
            'order' => 'desc,desc',
        ];

        // Log full URL for debugging
        $fullUrl = $url . '?' . http_build_query($params);
        Log::debug('Requesting ticket API:', ['url' => $fullUrl]);

        $response = Http::get($url, $params);


        if ($response->failed()) {
            return view('master::support.supportemail', [
                'tickets' => [],
                'error' => 'Failed to fetch tickets.'
            ]);
        }

        $data = $response->json();
        $tickets = $data['data'] ?? [];
        $totalTickets = $data['pagination']['total'] ?? 0;
        $totalPages = ceil($totalTickets / $perPage);

        return view('master::master.support.supportemail',
        [
            'tickets' => $tickets,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'error' => null
        ]);
    }

    public function ticketEmailView(Request $request, $id)
    {
        if (!$id) {
            return response()->json(['success' => false, 'message' => 'Ticket ID is required']);
        }

        $apiUrl = $this->getAppSetting('app.ticket');//?: config('app.ticket', 'http://192.168.16.205:9016');
        $appCode = $this->getAppSetting('app.APP_CODE');//?: config('SsoConfig.main.APP_CODE')
        $search = $this->getAppSetting('app.search');

        $apiUrlViewTicket = $apiUrl . "/api/tickets/" . $id;

        // Initialize cURL session
        $curl = curl_init();

        // Set cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrlViewTicket,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json"
            ],
        ]);

        // Execute cURL request
        $response = curl_exec($curl);
        $err = curl_error($curl);

        // Close cURL session
        curl_close($curl);

        if ($err) {
            return response()->json(['success' => false, 'message' => 'Error fetching ticket data: ' . $err]);
        }

        // Decode the JSON response
        $responseData = json_decode($response, true);

        // Return the response
        return response()->json($responseData);
    }

    public function ticketStore(Request $request){

        $request->validate([
            'email_subject' => 'required',
            'description' => 'required',
        ]);

        $appName = config('app.name');

        $apiUrl = $this->getAppSetting('app.ticket');//?: config('app.ticket', 'http://192.168.16.205:9016');
        $appCode = $this->getAppSetting('app.APP_CODE');//?: config('SsoConfig.main.APP_CODE')
        $search = $this->getAppSetting('app.search');

        // Gather form data
        $subject = $request->subject;

        if (str_contains($subject, '[EMPLOYEE-INTERNAL]')) {
            $cc = ['apps-support@meindo.com','dita.kurniati@meindo.com'];
        }else{
            $cc = $request->input('email_to') ?? ['apps-support@meindo.com'];
        }

        $description = $request->input('description');
        $appCode = $appCode; // From config
        $status = "open";
        $priority = "medium";

        // Prepare data for the API
        $data = [
            'app_code' => $appCode,
            'cc' => implode(',', $cc),
            'subject' => $subject,
            'description' => $description,
            'status' => $status,
            'priority' => $priority,
            'request_by' => auth()->user()->email,
        ];

        // Prepare multipart data (for file uploads)
        $multipart = [];

        // Add form fields to multipart
        foreach ($data as $key => $value) {
            $multipart[] = [
                'name' => $key,
                'contents' => $value,
            ];
        }

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $multipart[] = [
                    'name' => 'attachment[]',
                    'contents' => fopen($file->getPathname(), 'r'),
                    'filename' => $file->getClientOriginalName(),
                ];
            }
        }

        try {
            // Send POST request to the external API
            $response = Http::asMultipart()->post($apiUrl . '/api/tickets', $multipart);

            // Check if the request was successful
            if ($response->successful()) {
                return response()->json(['message' => 'Ticket created successfully!'], 200);
            } else {
                // Log the error for debugging
                Log::error('API Error:', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // Extract error messages if available
                $errorMessages = $response->json('errors');
                if ($errorMessages) {
                    return response()->json(['errors' => $errorMessages], 422);
                }

                return response()->json(['message' => 'Failed to create ticket. Please try again.'], 500);
            }
        } catch (\Exception $e) {
            // Handle exceptions
            // Log::error('Exception:', ['message' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
