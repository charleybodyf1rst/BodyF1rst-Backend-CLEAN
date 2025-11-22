<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    public function getAchievements()
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function getAchievement($id)
    {
        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function getLeaderboard()
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function getFriendsLeaderboard()
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function getRewards()
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function claimReward($id)
    {
        return response()->json(['status' => 200, 'message' => 'Reward claimed']);
    }

    public function getPointsHistory()
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function getChallenges()
    {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function joinChallenge($id)
    {
        return response()->json(['status' => 200, 'message' => 'Challenge joined']);
    }
}