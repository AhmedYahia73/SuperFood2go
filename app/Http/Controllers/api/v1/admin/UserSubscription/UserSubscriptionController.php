<?php

namespace App\Http\Controllers\api\v1\admin\UserSubscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

use App\Models\UserSubscription;

class UserSubscriptionController extends Controller
{
    public function __construct(private UserSubscription $user_subscription){}

    public function view(Request $request){
        $user_subscription = $this->user_subscription 
        ->get(); 

        return response()->json([
            'user_subscription' => $user_subscription, 
        ]);
    }

    public function status_list(Request $request){
        $query = $this->user_subscription->select('id', 'name', 'is_active');
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }
        $user_subscription = $query->get();

        return response()->json([
            'user_subscription' => $user_subscription,
        ]);
    }

    public function status(Request $request, $id = null){
        $subscriptionId = $id ?? $request->id ?? $request->user_subscription_id;

        $validator = Validator::make(array_merge($request->all(), ['id' => $subscriptionId]), [
            'id' => ['required', 'exists:user_subscriptions,id'],
            'is_active' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors(),
            ], 400);
        }

        $subscription = $this->user_subscription->find($subscriptionId);
        if (!$subscription) {
            return response()->json([
                'error' => 'User subscription not found',
            ], 404);
        }

        if ($request->has('is_active')) {
            $subscription->is_active = $request->boolean('is_active');
        } elseif ($request->has('status')) {
            $subscription->is_active = $request->boolean('status');
        } else {
            // Toggle current value if not explicitly specified
            $subscription->is_active = !$subscription->is_active;
        }

        $subscription->save();

        return response()->json([
            'success' => 'You update status success',
            'user_subscription' => $subscription,
        ]);
    }

    public function orders_count(Request $request, $id = null){
        $subscriptionId = $id ?? $request->id ?? $request->user_subscription_id;

        $validator = Validator::make(array_merge($request->all(), ['id' => $subscriptionId]), [
            'id' => ['required', 'exists:user_subscriptions,id'],
            'date' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'endpoint' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors(),
            ], 400);
        }

        $subscription = $this->user_subscription->find($subscriptionId);
        if (!$subscription) {
            return response()->json([
                'error' => 'User subscription not found',
            ], 404);
        }

        if (empty($subscription->back_link)) {
            return response()->json([
                'error' => 'Back link is empty for this subscription',
            ], 400);
        }

        $backLink = rtrim($subscription->back_link, '/');
        // Default endpoint is /admin/home/orders_count at food2go
        $endpoint = $request->input('endpoint', '/admin/home/orders_count');
        $endpoint = '/' . ltrim($endpoint, '/');
        $url = $backLink . $endpoint;

        $params = [];
        $fromDate = $request->date ?? $request->from;
        $toDate = $request->date_to ?? $request->to;

        if ($fromDate) {
            $params['date'] = $fromDate;
        }
        if ($toDate) {
            $params['date_to'] = $toDate;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'password' => 'Food2goRest$@#20203030',
                    'Accept' => 'application/json',
                ])
                ->get($url, $params);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'message' => 'Failed to fetch orders count from food2go',
                'remote_status' => $response->status(),
                'remote_body' => $response->json() ?? $response->body(),
            ], $response->status() >= 400 && $response->status() < 600 ? $response->status() : 502);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to connect to food2go server',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function my_package(Request $request){ 
        $user_subscription = $this->user_subscription
        ->get();

        return response()->json([
            'user_subscription' => $user_subscription,
        ]);
    }

    public function user_subscription(Request $request, $id){
        $user_subscription = $this->user_subscription
        ->where('id', $id)
        ->first();

        return response()->json([
            'user_subscription' => $user_subscription
        ]);
    }

    public function create(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'back_link' => ['required'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) { // if Validate Make Error Return Message Error
            return response()->json([
                'error' => $validator->errors(),
            ], 400);
        }
 
        $this->user_subscription
        ->create([
            'name' => $request->name,
            'back_link' => $request->back_link,
            'from' => $request->from,
            'to' => $request->to,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return response()->json([
            'success' => 'You add data success'
        ]);
    }

    public function modify(Request $request, $id){
        $validator = Validator::make($request->all(), [
            'name' => ['required'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'back_link' => ['required'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) { // if Validate Make Error Return Message Error
            return response()->json([
                'error' => $validator->errors(),
            ], 400);
        }
 
        $updateData = [
            'name' => $request->name,
            'back_link' => $request->back_link,
            'to' => $request->to,
            'from' => $request->from,
        ];
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }

        $this->user_subscription
        ->where('id', $id)
        ->update($updateData);

        return response()->json([
            'success' => 'You update data success'
        ]);
    }

    public function delete(Request $request, $id){
        $this->user_subscription
        ->where('id', $id)
        ->delete();

        return response()->json([
            'success' => 'You delete data success'
        ]);
    }
}
