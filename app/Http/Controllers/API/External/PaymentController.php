<?php

namespace App\Http\Controllers\API\External;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    protected $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
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
                        $hours = $class->duration / 60;
                        $cost = $hours * ($class->teacher->hourly_rate ?? 0);
                        return [
                            'id' => $class->id,
                            'date' => $class->class_date->format('Y-m-d'),
                            'time' => \Carbon\Carbon::parse($class->start_time)->format('H:i'),
                            'duration' => $class->duration,
                            'duration_hours' => round($hours, 2),
                            'teacher' => $class->teacher->user->name ?? 'N/A',
                            'course' => $class->course->name ?? 'N/A',
                            'cost' => round($cost, 2),
                        ];
                    });
            } elseif ($bill->class_id) {
                $class = \App\Models\ClassInstance::with(['teacher.user', 'course'])->find($bill->class_id);
                if ($class) {
                    $hours = $class->duration / 60;
                    $cost = $hours * ($class->teacher->hourly_rate ?? 0);
                    $classes = [[
                        'id' => $class->id,
                        'date' => $class->class_date->format('Y-m-d'),
                        'time' => \Carbon\Carbon::parse($class->start_time)->format('H:i'),
                        'duration' => $class->duration,
                        'duration_hours' => round($hours, 2),
                        'teacher' => $class->teacher->user->name ?? 'N/A',
                        'course' => $class->course->name ?? 'N/A',
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
                            'name' => $bill->student->full_name,
                            'email' => $bill->student->email,
                            'whatsapp' => $bill->student->whatsapp,
                        ],
                        'total_hours' => $bill->total_hours ?? ($bill->duration / 60),
                        'total_amount' => $bill->amount,
                        'currency' => $bill->currency,
                        'status' => $bill->status,
                        'bill_date' => $bill->bill_date->format('Y-m-d'),
                        'is_custom' => $bill->is_custom,
                        'description' => $bill->description,
                    ],
                    'classes' => $classes,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bill not found or invalid token',
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
            $studentName = $bill->student->full_name ?? 'student';
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
}
