<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Models\Expense;
use App\Services\FinancialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancialController extends Controller
{
    protected $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    /**
     * Get comprehensive financial summary
     */
    public function summary(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $currency = $request->input('currency');

        $summary = $this->financialService->getFinancialSummary($dateFrom, $dateTo);

        return response()->json([
            'status' => 'success',
            'data' => $summary,
        ]);
    }

    /**
     * Get income breakdown
     */
    public function income(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $currency = $request->input('currency');
        $groupBy = $request->input('group_by', 'month');

        $breakdown = $this->financialService->getIncomeBreakdown($dateFrom, $dateTo, $currency, $groupBy);

        return response()->json([
            'status' => 'success',
            'data' => $breakdown,
        ]);
    }

    /**
     * Get expense breakdown
     */
    public function expenseBreakdown(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $currency = $request->input('currency');

        $breakdown = $this->financialService->getExpenseBreakdown($dateFrom, $dateTo, $currency);

        return response()->json([
            'status' => 'success',
            'data' => $breakdown,
        ]);
    }

    /**
     * Get monthly trends
     */
    public function trends(Request $request): JsonResponse
    {
        $year = $request->input('year');

        $trends = $this->financialService->getMonthlyTrends($year);

        return response()->json([
            'status' => 'success',
            'data' => $trends,
        ]);
    }

    /**
     * Display a listing of expenses
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'category' => $request->input('category', 'all'),
            'currency' => $request->input('currency'),
        ];

        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        $query = Expense::with('creator')
            ->byCategory($filters['category'])
            ->byDateRange($filters['date_from'], $filters['date_to'])
            ->byCurrency($filters['currency'])
            ->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc');

        $expenses = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => $expenses->items(),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    /**
     * Store a newly created expense
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = Expense::create([
            'category' => $request->category,
            'description' => $request->description,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'expense_date' => $request->expense_date,
            'created_by' => Auth::id(),
        ]);

        $expense->load('creator');

        return response()->json([
            'status' => 'success',
            'message' => 'Expense created successfully',
            'data' => $expense,
        ], 201);
    }

    /**
     * Display the specified expense
     */
    public function show(string $id): JsonResponse
    {
        $expense = Expense::with('creator')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $expense,
        ]);
    }

    /**
     * Update the specified expense
     */
    public function update(UpdateExpenseRequest $request, string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);

        $expense->update($request->validated());

        $expense->load('creator');

        return response()->json([
            'status' => 'success',
            'message' => 'Expense updated successfully',
            'data' => $expense,
        ]);
    }

    /**
     * Remove the specified expense
     */
    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Expense deleted successfully',
        ]);
    }

    /**
     * Get income statistics by currency
     */
    public function incomeByCurrency(Request $request): JsonResponse
    {
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $statistics = $this->financialService->getIncomeByCurrency($dateFrom, $dateTo);

        return response()->json([
            'status' => 'success',
            'data' => $statistics,
        ]);
    }

    /**
     * Convert currency
     */
    public function convertCurrency(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'from_currency' => 'required|string|size:3',
            'to_currency' => 'required|string|size:3',
            'rate' => 'required|numeric|min:0',
        ]);

        $result = $this->financialService->convertCurrency(
            $request->amount,
            $request->from_currency,
            $request->to_currency,
            $request->rate
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }
}
