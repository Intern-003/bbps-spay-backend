<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserCategoryPermission;

class UserCategoryPermissionController extends Controller
{
    // Get categories for a user
    public function getCategories($userId)
    {
        $permissions = UserCategoryPermission::where('user_id', $userId)->first();

        if (!$permissions) {
            return response()->json([
                'status' => false,
                'message' => 'No categories found for this user',
                'categories' => []
            ]);
        }

        return response()->json([
            'status' => true,
            'categories' => $permissions->categories
        ]);
    }

    // Update categories for a user (add)
    public function updateCategories(Request $request, $userId)
    {
        $request->validate([
            'categories' => 'required|array|min:1',
            'categories.*' => 'string',
        ]);

        // Ensure record exists
        $permissions = UserCategoryPermission::firstOrCreate(
            ['user_id' => $userId],
            ['categories' => []]
        );

        // Get existing categories
        $existing = $permissions->categories ?? [];

        // Merge (no duplicates)
        $merged = array_unique(array_merge($existing, $request->categories));

        // Save merged categories
        $permissions->update([
            'categories' => array_values($merged)
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Categories updated successfully',
            'categories' => $permissions->categories
        ]);
    }

    // Remove a single category for a user
    public function removeCategory(Request $request, $userId)
    {
        $request->validate([
            'category' => 'required|string'
        ]);

        $permissions = UserCategoryPermission::where('user_id', $userId)->first();

        if (!$permissions || empty($permissions->categories)) {
            return response()->json([
                'status' => false,
                'message' => 'No categories found for this user'
            ]);
        }

        $categoryToRemove = $request->category;

        // Filter out the category to remove
        $updatedCategories = array_values(array_filter(
            $permissions->categories,
            fn($c) => $c !== $categoryToRemove
        ));

        $permissions->update([
            'categories' => $updatedCategories
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Category removed successfully',
            'categories' => $updatedCategories
        ]);
    }
}
