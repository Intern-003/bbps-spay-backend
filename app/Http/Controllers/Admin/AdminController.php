<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function getDashboardData()
    {
        try {
            // 1. Top 10 users by total payout
            $topUsers = DB::table('reports as r')
                ->join('users as u', 'u.id', '=', 'r.user_id')
                ->select(
                    'r.user_id',
                    'u.name as user_name',
                    'u.email as user_email',
                    DB::raw('SUM(r.payout_amount) as total_payout')
                )
                ->where('r.status', 'success')
                ->groupBy('r.user_id', 'u.name', 'u.email') // include all selected columns in GROUP BY
                ->orderByDesc('total_payout')
                ->limit(10)
                ->get();

            // 2. Reports summary
            $summary = [
                'total_reports' => DB::table('reports')->count(),
                'success_count' => DB::table('reports')->where('status', 'success')->count(),
                'failed_count' => DB::table('reports')->where('status', 'failed')->count(),
                'total_users' => DB::table('users')->count(),
            ];

            // 3. Month-wise earnings (last 12 months)
            $months = DB::select("
                WITH RECURSIVE months AS (
                    SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01') AS month_start
                    UNION ALL
                    SELECT DATE_ADD(month_start, INTERVAL 1 MONTH)
                    FROM months
                    WHERE month_start < DATE_FORMAT(CURDATE(), '%Y-%m-01')
                )
                SELECT 
                    DATE_FORMAT(m.month_start, '%Y-%m') AS month_year,
                    IFNULL(SUM(r.payout_amount), 0) AS total_earning
                FROM months m
                LEFT JOIN reports r 
                    ON DATE_FORMAT(r.created_at, '%Y-%m-01') = m.month_start
                    AND r.status = 'success'
                GROUP BY m.month_start
                ORDER BY m.month_start DESC
            ");

            // 4. All successful reports
            $successfulReports = DB::table('reports as r')
                ->join('users as u', 'u.id', '=', 'r.user_id')
                ->where('r.status', 'success')
                ->select(
                    'r.*',
                    'u.name as user_name',
                    'u.email as user_email'
                )
                ->get();

            return response()->json([
                'status' => true,
                'top_users' => $topUsers,
                'summary' => $summary,
                'month_wise_earnings' => $months,
                'successful_reports' => $successfulReports,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // sdsc
}
