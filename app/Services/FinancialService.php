<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Expense;
use App\Models\Teacher;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialService
{
    /**
     * Calculate net profit (income - expenses)
     */
    public function calculateNetProfit(?string $dateFrom = null, ?string $dateTo = null, ?string $currency = null): array
    {
        $incomeQuery = Bill::where('status', 'paid');
        $expenseQuery = Expense::query();

        if ($dateFrom) {
            $incomeQuery->where('bill_date', '>=', $dateFrom);
            $expenseQuery->where('expense_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $incomeQuery->where('bill_date', '<=', $dateTo);
            $expenseQuery->where('expense_date', '<=', $dateTo);
        }
        if ($currency) {
            $incomeQuery->where('currency', $currency);
            $expenseQuery->where('currency', $currency);
        }

        $totalIncome = (float) $incomeQuery->sum('amount');
        $totalExpenses = (float) $expenseQuery->sum('amount');
        $netProfit = $totalIncome - $totalExpenses;
        $profitMargin = $totalIncome > 0 ? ($netProfit / $totalIncome) * 100 : 0;

        return [
            'income' => $totalIncome,
            'expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'profit_margin' => round($profitMargin, 2),
            'currency' => $currency ?? 'USD',
        ];
    }

    /**
     * Get income breakdown by various dimensions
     */
    public function getIncomeBreakdown(?string $dateFrom = null, ?string $dateTo = null, ?string $currency = null, ?string $groupBy = 'month'): array
    {
        $query = Bill::where('bills.status', 'paid');

        if ($dateFrom) {
            $query->where('bill_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('bill_date', '<=', $dateTo);
        }
        if ($currency) {
            $query->where('currency', $currency);
        }

        switch ($groupBy) {
            case 'month':
                $breakdown = $query->select(
                    DB::raw('DATE_FORMAT(bill_date, "%Y-%m") as period'),
                    DB::raw('SUM(amount) as total')
                )
                    ->groupBy('period')
                    ->orderBy('period')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'period' => $item->period,
                            'amount' => (float) $item->total,
                        ];
                    })
                    ->toArray();
                break;

            case 'teacher':
                $breakdown = $query->select(
                    'teacher_id',
                    DB::raw('SUM(amount) as total')
                )
                    ->with('teacher.user')
                    ->groupBy('teacher_id')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'teacher_id' => $item->teacher_id,
                            'teacher_name' => $item->teacher->user->name ?? 'N/A',
                            'amount' => (float) $item->total,
                        ];
                    })
                    ->toArray();
                break;

            case 'course':
                $breakdown = $query
                    ->join('classes', 'bills.class_id', '=', 'classes.id')
                    ->join('courses', 'classes.course_id', '=', 'courses.id')
                    ->select('courses.id as course_id', 'courses.name as course_name', DB::raw('SUM(bills.amount) as total'))
                    ->groupBy('courses.id', 'courses.name')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'course_id' => $item->course_id,
                            'course_name' => $item->course_name,
                            'amount' => (float) $item->total,
                        ];
                    })
                    ->toArray();
                break;

            default:
                $breakdown = [];
        }

        // Get paid vs pending totals
        $paidTotal = (float) Bill::where('status', 'paid')
            ->when($dateFrom, fn($q) => $q->where('bill_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('bill_date', '<=', $dateTo))
            ->when($currency, fn($q) => $q->where('currency', $currency))
            ->sum('amount');

        $pendingTotal = (float) Bill::where('status', 'pending')
            ->when($dateFrom, fn($q) => $q->where('bill_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('bill_date', '<=', $dateTo))
            ->when($currency, fn($q) => $q->where('currency', $currency))
            ->sum('amount');

        return [
            'breakdown' => $breakdown,
            'paid_total' => $paidTotal,
            'pending_total' => $pendingTotal,
            'currency' => $currency ?? 'USD',
        ];
    }

    /**
     * Get expense breakdown by category and month
     */
    public function getExpenseBreakdown(?string $dateFrom = null, ?string $dateTo = null, ?string $currency = null): array
    {
        $query = Expense::query();

        if ($dateFrom) {
            $query->where('expense_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('expense_date', '<=', $dateTo);
        }
        if ($currency) {
            $query->where('currency', $currency);
        }

        // By category
        $byCategory = $query->select(
            'category',
            DB::raw('SUM(amount) as total')
        )
            ->groupBy('category')
            ->get()
            ->map(function ($item) {
                return [
                    'category' => $item->category,
                    'amount' => (float) $item->total,
                ];
            })
            ->toArray();

        // Calculate total for percentages
        $totalExpenses = array_sum(array_column($byCategory, 'amount'));

        // Add percentages
        $byCategory = array_map(function ($item) use ($totalExpenses) {
            $item['percentage'] = $totalExpenses > 0 ? round(($item['amount'] / $totalExpenses) * 100, 2) : 0;
            return $item;
        }, $byCategory);

        // By month
        $byMonth = Expense::query()
            ->when($dateFrom, fn($q) => $q->where('expense_date', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->where('expense_date', '<=', $dateTo))
            ->when($currency, fn($q) => $q->where('currency', $currency))
            ->select(
                DB::raw('DATE_FORMAT(expense_date, "%Y-%m") as period'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'amount' => (float) $item->total,
                ];
            })
            ->toArray();

        return [
            'by_category' => $byCategory,
            'by_month' => $byMonth,
            'total' => $totalExpenses,
            'currency' => $currency ?? 'USD',
        ];
    }

    /**
     * Get comprehensive financial summary
     */
    public function getFinancialSummary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        // Calculate net profit (default currency or all)
        $profit = $this->calculateNetProfit($dateFrom, $dateTo);

        // Get income breakdown
        $incomeBreakdown = $this->getIncomeBreakdown($dateFrom, $dateTo);
        
        // Get expense breakdown
        $expenseBreakdown = $this->getExpenseBreakdown($dateFrom, $dateTo);

        // Get monthly trends for current year
        $year = $dateTo ? Carbon::parse($dateTo)->format('Y') : date('Y');
        $trends = $this->getMonthlyTrends($year);

        // Get top income sources
        $incomeByTeacher = $this->getIncomeBreakdown($dateFrom, $dateTo, null, 'teacher');
        $incomeByCourse = $this->getIncomeBreakdown($dateFrom, $dateTo, null, 'course');

        return [
            'income' => [
                'total' => $incomeBreakdown['paid_total'],
                'paid' => $incomeBreakdown['paid_total'],
                'pending' => $incomeBreakdown['pending_total'],
                'currency' => $profit['currency'],
            ],
            'expenses' => [
                'total' => $expenseBreakdown['total'],
                'by_category' => array_column($expenseBreakdown['by_category'], 'amount', 'category'),
                'currency' => $expenseBreakdown['currency'],
            ],
            'profit' => [
                'net' => $profit['net_profit'],
                'margin' => $profit['profit_margin'],
                'currency' => $profit['currency'],
            ],
            'trends' => [
                'monthly' => $trends,
            ],
            'breakdown' => [
                'income_by_teacher' => $incomeByTeacher['breakdown'] ?? [],
                'income_by_course' => $incomeByCourse['breakdown'] ?? [],
                'expenses_by_category' => $expenseBreakdown['by_category'],
            ],
        ];
    }

    /**
     * Get monthly trends for a year
     */
    public function getMonthlyTrends(?string $year = null): array
    {
        $year = $year ?? date('Y');
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        // Get monthly income
        $monthlyIncome = Bill::where('status', 'paid')
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(bill_date, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Get monthly expenses
        $monthlyExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(expense_date, "%Y-%m") as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Combine into monthly trends
        $trends = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthKey = sprintf('%s-%02d', $year, $month);
            $income = (float) ($monthlyIncome[$monthKey]->total ?? 0);
            $expenses = (float) ($monthlyExpenses[$monthKey]->total ?? 0);
            $profit = $income - $expenses;

            $trends[] = [
                'month' => $monthKey,
                'income' => $income,
                'expenses' => $expenses,
                'profit' => $profit,
            ];
        }

        return $trends;
    }

    /**
     * Get income statistics grouped by currency with detailed breakdown
     */
    public function getIncomeByCurrency(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Bill::where('status', 'paid');

        if ($dateFrom) {
            $query->where('bill_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('bill_date', '<=', $dateTo);
        }

        $incomeByCurrency = $query->select(
            'currency',
            DB::raw('SUM(amount) as total_income'),
            DB::raw('COUNT(*) as bill_count')
        )
            ->groupBy('currency')
            ->orderBy('total_income', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'currency' => $item->currency,
                    'total_income' => (float) $item->total_income,
                    'bill_count' => (int) $item->bill_count,
                ];
            })
            ->toArray();

        // Get all bills (paid and unpaid) by currency
        $allBillsQuery = Bill::query();
        if ($dateFrom) {
            $allBillsQuery->where('bill_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $allBillsQuery->where('bill_date', '<=', $dateTo);
        }

        $allBillsByCurrency = $allBillsQuery->select(
            'currency',
            'status',
            DB::raw('SUM(amount) as total_amount'),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy('currency', 'status')
            ->get()
            ->groupBy('currency');

        // Get expenses by currency
        $expenseQuery = Expense::query();
        if ($dateFrom) {
            $expenseQuery->where('expense_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $expenseQuery->where('expense_date', '<=', $dateTo);
        }

        $expensesByCurrency = $expenseQuery->select(
            'currency',
            DB::raw('SUM(amount) as total_expenses'),
            DB::raw('COUNT(*) as expense_count')
        )
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        // Get salaries by currency (from teacher salaries)
        $salariesByCurrency = DB::table('classes')
            ->join('teachers', 'classes.teacher_id', '=', 'teachers.id')
            ->where('classes.status', 'attended')
            ->when($dateFrom, function ($q) use ($dateFrom) {
                return $q->where('classes.class_date', '>=', $dateFrom);
            })
            ->when($dateTo, function ($q) use ($dateTo) {
                return $q->where('classes.class_date', '<=', $dateTo);
            })
            ->select(
                'teachers.currency',
                DB::raw('SUM((classes.duration / 60.0) * teachers.hourly_rate) as total_salaries'),
                DB::raw('COUNT(*) as class_count')
            )
            ->groupBy('teachers.currency')
            ->get()
            ->keyBy('currency');

        // Combine all data
        $result = [];
        $allCurrencies = array_unique(array_merge(
            array_column($incomeByCurrency, 'currency'),
            $expensesByCurrency->keys()->toArray(),
            $salariesByCurrency->keys()->toArray()
        ));

        foreach ($allCurrencies as $currency) {
            $income = collect($incomeByCurrency)->firstWhere('currency', $currency);
            $expenses = $expensesByCurrency[$currency] ?? null;
            $salaries = $salariesByCurrency[$currency] ?? null;
            $bills = $allBillsByCurrency[$currency] ?? collect();

            $paidBills = $bills->firstWhere('status', 'paid');
            $unpaidBills = $bills->whereIn('status', ['pending', 'sent']);

            $totalCollected = $income ? $income['total_income'] : 0;
            $totalSalaries = $salaries ? (float) $salaries->total_salaries : 0;
            $totalExpenses = $expenses ? (float) $expenses->total_expenses : 0;
            $paidBillsAmount = $paidBills ? (float) $paidBills->total_amount : 0;
            $paidBillsCount = $paidBills ? (int) $paidBills->count : 0;
            $unpaidBillsAmount = $unpaidBills->sum(function ($bill) {
                return (float) $bill->total_amount;
            });
            $unpaidBillsCount = $unpaidBills->sum(function ($bill) {
                return (int) $bill->count;
            });
            $netProfit = $totalCollected - $totalExpenses - $totalSalaries;

            $result[] = [
                'currency' => $currency,
                'total_collected' => $totalCollected,
                'salaries' => $totalSalaries,
                'expenses' => $totalExpenses,
                'net_profit' => $netProfit,
                'paid_bills_amount' => $paidBillsAmount,
                'paid_bills_count' => $paidBillsCount,
                'unpaid_bills_amount' => $unpaidBillsAmount,
                'unpaid_bills_count' => $unpaidBillsCount,
                'bill_count' => $income ? $income['bill_count'] : 0,
                'expense_count' => $expenses ? (int) $expenses->expense_count : 0,
            ];
        }

        // Sort by total collected descending
        usort($result, function ($a, $b) {
            return $b['total_collected'] <=> $a['total_collected'];
        });

        return $result;
    }

    /**
     * Convert amount from one currency to another
     */
    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency, float $rate): array
    {
        if ($fromCurrency === $toCurrency) {
            return [
                'original_amount' => $amount,
                'converted_amount' => $amount,
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'rate' => 1.0,
            ];
        }

        $convertedAmount = $amount * $rate;

        return [
            'original_amount' => $amount,
            'converted_amount' => round($convertedAmount, 2),
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'rate' => $rate,
        ];
    }
}
