<?php

namespace App\Http\Controllers\API\External;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use App\Services\PayPalService;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    protected $billingService;
    protected $paypalService;

    public function __construct(BillingService $billingService, PayPalService $paypalService)
    {
        $this->billingService = $billingService;
        $this->paypalService = $paypalService;
    }

    /**
     * Get bill details for public payment page (no auth required)
     */
    public function show(string $token): JsonResponse
    {
        try {
            $bill = $this->billingService->getBillByToken($token);

            // Get classes included in this bill
            $classes = [];
            if ($bill->class_ids && is_array($bill->class_ids)) {
                $classes = \App\Models\ClassInstance::with(['teacher.user', 'course'])
                    ->whereIn('id', $bill->class_ids)
                    ->orderBy('class_date', 'asc')
                    ->orderBy('start_time', 'asc')
                    ->get()
                    ->map(function ($class) use ($bill) {
                        $hours = $class->duration ? ($class->duration / 60) : 0;
                        $cost = $hours * (($class->teacher && $class->teacher->hourly_rate) ? $class->teacher->hourly_rate : 0);
                        return [
                            'id' => $class->id,
                            'date' => $class->class_date ? $class->class_date->format('Y-m-d') : null,
                            'time' => $class->start_time ? \Carbon\Carbon::parse($class->start_time)->format('H:i') : null,
                            'duration' => $class->duration ?? 0,
                            'duration_hours' => round($hours, 2),
                            'teacher' => ($class->teacher && $class->teacher->user) ? $class->teacher->user->name : 'N/A',
                            'course' => $class->course ? $class->course->name : 'N/A',
                            'cost' => round($cost, 2),
                        ];
                    });
            } elseif ($bill->class_id) {
                $class = \App\Models\ClassInstance::with(['teacher.user', 'course'])->find($bill->class_id);
                if ($class) {
                    $hours = $class->duration ? ($class->duration / 60) : 0;
                    $cost = $hours * (($class->teacher && $class->teacher->hourly_rate) ? $class->teacher->hourly_rate : 0);
                    $classes = [[
                        'id' => $class->id,
                        'date' => $class->class_date ? $class->class_date->format('Y-m-d') : null,
                        'time' => $class->start_time ? \Carbon\Carbon::parse($class->start_time)->format('H:i') : null,
                        'duration' => $class->duration ?? 0,
                        'duration_hours' => round($hours, 2),
                        'teacher' => ($class->teacher && $class->teacher->user) ? $class->teacher->user->name : 'N/A',
                        'course' => $class->course ? $class->course->name : 'N/A',
                        'cost' => round($cost, 2),
                    ]];
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'bill' => [
                        'id' => $bill->id,
                        'student' => [
                            'name' => $bill->student ? $bill->student->full_name : 'N/A',
                            'email' => $bill->student ? $bill->student->email : null,
                            'whatsapp' => $bill->student ? $bill->student->whatsapp : null,
                        ],
                        'total_hours' => $bill->total_hours ?? ($bill->duration ? ($bill->duration / 60) : 0),
                        'total_amount' => $bill->amount,
                        'currency' => $bill->currency,
                        'status' => $bill->status,
                        'bill_date' => $bill->bill_date ? $bill->bill_date->format('Y-m-d') : null,
                        'is_custom' => $bill->is_custom,
                        'description' => $bill->description,
                    ],
                    'classes' => $classes,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Payment page error for token: ' . $token);
            \Log::error('Error message: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Bill not found or invalid token: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Download PDF for public payment page (no auth required)
     */
    public function downloadPdf(string $token)
    {
        try {
            $bill = $this->billingService->getBillByToken($token);
            
            // Generate PDF
            $pdfPath = $this->billingService->generateBillPDF($bill->id);
            $fullPath = storage_path('app/' . $pdfPath);

            // Check if file exists using file_exists (more reliable than Storage::exists)
            if (!file_exists($fullPath)) {
                \Log::error('PDF file not found at path: ' . $fullPath);
                return response()->json([
                    'status' => 'error',
                    'message' => 'PDF file not found. Please try again.',
                ], 404);
            }

            // Generate a descriptive filename with student name
            $studentName = ($bill->student && $bill->student->full_name) ? $bill->student->full_name : 'student';
            $sanitizedName = preg_replace('/[^a-z0-9]/i', '_', strtolower($studentName));
            $date = now()->format('Y-m-d');
            $filename = 'bill_' . $sanitizedName . '_' . $bill->id . '_' . $date . '.pdf';

            // Return file download
            return response()->download($fullPath, $filename, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            \Log::error('PDF download error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process payment (placeholder, no real integration)
     */
    public function processPayment(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string|in:credit_card,bank_transfer,paypal,anubpay',
        ]);

        try {
            $bill = $this->billingService->getBillByToken($token);

            if ($bill->status === 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This bill has already been paid',
                ], 400);
            }

            // Placeholder: In real implementation, this would integrate with payment gateway
            // For now, we'll just return a success message
            return response()->json([
                'status' => 'success',
                'message' => 'Payment processed successfully (placeholder - no real payment integration)',
                'data' => [
                    'bill_id' => $bill->id,
                    'payment_method' => $request->input('payment_method'),
                    'note' => 'This is a placeholder. Real payment integration will be implemented later.',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create PayPal payment
     */
    public function createPayPalPayment(Request $request, string $token): JsonResponse
    {
        try {
            $bill = $this->billingService->getBillByToken($token);

            if ($bill->status === 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This bill has already been paid',
                ], 400);
            }

            // Build return and cancel URLs
            // Note: PayPal will automatically append paymentId and PayerID as query parameters
            $baseUrl = config('app.url');
            $returnUrl = $baseUrl . '/payment/' . $token . '?paypal=success';
            $cancelUrl = $baseUrl . '/payment/' . $token . '?paypal=cancel';

            // Create payment description
            $description = 'Payment for bill #' . $bill->id;
            if ($bill->student && $bill->student->full_name) {
                $description .= ' - ' . $bill->student->full_name;
            }

            // Validate PayPal configuration
            $clientId = config('paypal.client_id');
            $clientSecret = config('paypal.client_secret');
            $mode = config('paypal.mode', 'sandbox');
            
            if (empty($clientId) || empty($clientSecret)) {
                \Log::error('PayPal configuration missing. Client ID: ' . ($clientId ? 'set' : 'empty') . ', Secret: ' . ($clientSecret ? 'set' : 'empty'));
                return response()->json([
                    'status' => 'error',
                    'message' => 'PayPal is not configured. Please check your .env file for PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET.',
                ], 500);
            }

            // Create PayPal payment
            $result = $this->paypalService->createPayment(
                $bill->amount,
                $bill->currency ?? config('paypal.currency', 'USD'),
                $description,
                $returnUrl,
                $cancelUrl,
                'BILL-' . $bill->id
            );

            if (!$result['success']) {
                \Log::error('PayPal payment creation failed: ' . ($result['error'] ?? 'Unknown error'));
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create PayPal payment: ' . ($result['error'] ?? 'Unknown error'),
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'payment_id' => $result['payment_id'],
                    'approval_url' => $result['approval_url'],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('PayPal Payment Creation Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create PayPal payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute PayPal payment
     */
    public function executePayPalPayment(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'paymentId' => 'required|string',
            'PayerID' => 'required|string',
        ]);

        try {
            $bill = $this->billingService->getBillByToken($token);

            if ($bill->status === 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This bill has already been paid',
                ], 400);
            }

            // Execute PayPal payment
            $result = $this->paypalService->executePayment(
                $request->input('paymentId'),
                $request->input('PayerID')
            );

            if (!$result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment execution failed: ' . ($result['error'] ?? 'Unknown error'),
                ], 400);
            }

            // Verify payment amount matches bill amount
            $paidAmount = (float) $result['amount'];
            $billAmount = (float) $bill->amount;
            
            if (abs($paidAmount - $billAmount) > 0.01) {
                \Log::error('PayPal payment amount mismatch. Bill: ' . $billAmount . ', Paid: ' . $paidAmount);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment amount mismatch. Please contact support.',
                ], 400);
            }

            // Mark bill as paid
            $this->billingService->markAsPaid(
                $bill->id,
                'paypal',
                now()->format('Y-m-d'),
                null,
                $result['transaction_id'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Payment processed successfully',
                'data' => [
                    'bill_id' => $bill->id,
                    'payment_id' => $result['payment_id'],
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('PayPal Payment Execution Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to execute payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create PayPal order for Smart Buttons
     */
    public function createPayPalOrder(Request $request, string $token): JsonResponse
    {
        try {
            $bill = $this->billingService->getBillByToken($token);

            if ($bill->status === 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This bill has already been paid',
                ], 400);
            }

            // Create order description
            $description = 'Payment for bill #' . $bill->id;
            if ($bill->student && $bill->student->full_name) {
                $description .= ' - ' . $bill->student->full_name;
            }

            // Create PayPal order
            $result = $this->paypalService->createOrder(
                $bill->amount,
                $bill->currency ?? config('paypal.currency', 'USD'),
                $description,
                'BILL-' . $bill->id
            );

            if (!$result['success']) {
                \Log::error('PayPal order creation failed: ' . ($result['error'] ?? 'Unknown error'));
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to create PayPal order: ' . ($result['error'] ?? 'Unknown error'),
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'order_id' => $result['order_id'],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('PayPal Order Creation Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create PayPal order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Capture PayPal order for Smart Buttons
     */
    public function capturePayPalOrder(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'orderID' => 'required|string',
        ]);

        try {
            $bill = $this->billingService->getBillByToken($token);

            if ($bill->status === 'paid') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This bill has already been paid',
                ], 400);
            }

            // Capture PayPal order
            $result = $this->paypalService->captureOrder($request->input('orderID'));

            if (!$result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment capture failed: ' . ($result['error'] ?? 'Unknown error'),
                ], 400);
            }

            // Verify payment amount matches bill amount
            $paidAmount = (float) $result['amount'];
            $billAmount = (float) $bill->amount;
            
            if (abs($paidAmount - $billAmount) > 0.01) {
                \Log::error('PayPal payment amount mismatch. Bill: ' . $billAmount . ', Paid: ' . $paidAmount);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment amount mismatch. Please contact support.',
                ], 400);
            }

            // Mark bill as paid
            $this->billingService->markAsPaid(
                $bill->id,
                'paypal',
                now()->format('Y-m-d'),
                null,
                $result['transaction_id'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Payment processed successfully',
                'data' => [
                    'bill_id' => $bill->id,
                    'order_id' => $result['order_id'],
                    'transaction_id' => $result['transaction_id'] ?? null,
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('PayPal Order Capture Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to capture payment: ' . $e->getMessage(),
            ], 500);
        }
    }
}
