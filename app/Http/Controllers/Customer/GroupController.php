<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function getGroups() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function createGroup(Request $request) {
        return response()->json(['status' => 200, 'data' => ['id' => 1]]);
    }

    public function getGroup($id) {
        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function updateGroup(Request $request, $id) {
        return response()->json(['status' => 200, 'message' => 'Group updated']);
    }

    public function deleteGroup($id) {
        return response()->json(['status' => 200, 'message' => 'Group deleted']);
    }

    public function joinGroup($id) {
        return response()->json(['status' => 200, 'message' => 'Joined group']);
    }

    public function leaveGroup($id) {
        return response()->json(['status' => 200, 'message' => 'Left group']);
    }

    public function getGroupMembers($id) {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function inviteToGroup($id, Request $request) {
        return response()->json(['status' => 200, 'message' => 'Invitation sent']);
    }
}