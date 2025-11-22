<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function getSubscription() {
        return response()->json(['status' => 200, 'data' => ['status' => 'active']]);
    }

    public function getPlans() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function subscribe(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Subscribed']);
    }

    public function upgrade(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Upgraded']);
    }

    public function downgrade(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Downgraded']);
    }

    public function cancel() {
        return response()->json(['status' => 200, 'message' => 'Cancelled']);
    }

    public function resume() {
        return response()->json(['status' => 200, 'message' => 'Resumed']);
    }

    public function getInvoices() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function getInvoice($id) {
        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function getPaymentMethods() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function addPaymentMethod(Request $request) {
        return response()->json(['status' => 200, 'message' => 'Payment method added']);
    }

    public function deletePaymentMethod($id) {
        return response()->json(['status' => 200, 'message' => 'Payment method deleted']);
    }

    public function getPaymentHistory() {
        return response()->json(['status' => 200, 'data' => []]);
    }
}