<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use App\Models\Scheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchemeController extends Controller
{

        public function getSchemes()
        {
            $schemes = DB::select("
                SELECT 
                    schemes.id AS scheme_id,
                    MAX(schemes.name) AS name,
                    MAX(schemes.blr_id) AS blr_id,
                    MAX(schemes.merchant_id) AS merchant_id,
                    MAX(schemes.commission_type) AS commission_type,
                    MAX(schemes.type) AS type,
                    MAX(schemes.commission_value) AS commission_value,
                    MAX(schemes.status) AS status,
                    MAX(schemes.gst_type) AS gst_type,
                    MAX(schemes.gst_value) AS gst_value,
                    MAX(schemes.created_at) AS created_at,
                    MAX(schemes.updated_at) AS updated_at,
                    CONCAT('[', GROUP_CONCAT(CONCAT('\"', users.name, '\"') ORDER BY users.id), ']') AS user_names_json,
                    CONCAT('[', GROUP_CONCAT(CONCAT('\"', bct.blr_name, '\"') ORDER BY bct.blr_id), ']') AS blr_names_json
                FROM schemes
                JOIN users ON JSON_CONTAINS(schemes.merchant_id, CAST(users.id AS CHAR))
                LEFT JOIN bharat_connect_mdm bct ON JSON_CONTAINS(schemes.blr_id, CONCAT('\"', bct.blr_id, '\"'))
                GROUP BY schemes.id
                ORDER BY schemes.id DESC 
                LIMIT 0, 25
            ");
        
            return response()->json([
                'status' => true,
                'data' => $schemes,
            ]);
        }


    public function createScheme(Request $request)
    {
        try {
            $validatedData = $request->validate([
            'name'             => 'required|string|max:255',
            'commission_type'  => 'nullable|in:wallet',
            'type'             => 'nullable|in:flat,percent',
            'commission_value' => 'nullable|numeric',
            'status'           => 'nullable|boolean',
            'gst_type'         => 'nullable|in:percent',
            'gst_value'        => 'nullable|numeric',
            'merchant_id'      => 'nullable|array',
            'blr_id'           => 'nullable|array',
        ]);
        
        $validatedData['merchant_id'] = json_encode($validatedData['merchant_id'] ?? []);
        $validatedData['blr_id']      = json_encode($validatedData['blr_id'] ?? []);

        $scheme = Scheme::create($validatedData);
            // 3. Success Response
            return response()->json([
                'status'  => true,
                'message' => 'Scheme created successfully',
                'data'    => $scheme
            ], 201);
    
        } catch (\Throwable $th) {
            // 4. Error Handling
            Log::error('Scheme Create Error: ' . $th->getMessage());
    
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while creating the scheme.',
                'error'   => $th->getMessage() 
            ], 500);
        }
}

    public function editScheme($id)
    {
        $scheme = Scheme::find($id);

        if (!$scheme) {
            return response()->json([
                'status' => false,
                'message' => 'Scheme not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $scheme
        ]);
    }

public function updateScheme(Request $request)
{
    try {
        $validatedData = $request->validate([
            'id' => 'required|numeric',
            'name' => 'required|string|max:255',
            'commission_type' => 'nullable|in:wallet',
            'type' => 'nullable|in:flat,percent',
            'commission_value' => 'nullable|numeric',
            'status' => 'nullable|boolean',
            'gst_type' => 'nullable|in:percent',
            'gst_value' => 'nullable|numeric',
            'merchant_id' => 'nullable|array',
            'blr_id' => 'nullable|array',
        ]);

        $scheme = Scheme::find($validatedData['id']);

        if (!$scheme) {
            return response()->json([
                'status' => false,
                'message' => 'Scheme not found',
            ], 404);
        }

        // Fill default values for arrays if needed
        $validatedData['merchant_id'] = $validatedData['merchant_id'] ?? $scheme->merchant_id ?? [];
        $validatedData['blr_id']      = $validatedData['blr_id'] ?? $scheme->blr_id ?? [];

        $scheme->update($validatedData);

        return response()->json([
            'status' => true,
            'message' => 'Scheme updated successfully',
            'data' => $scheme
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    public function deleteScheme($id)
    {
        $scheme = Scheme::find($id);

        if (!$scheme) {
            return response()->json([
                'status' => false,
                'message' => 'Scheme not found',
            ], 404);
        }

        $scheme->delete();

        return response()->json([
            'status' => true,
            'message' => 'Scheme deleted successfully',
        ]);
    }
}
