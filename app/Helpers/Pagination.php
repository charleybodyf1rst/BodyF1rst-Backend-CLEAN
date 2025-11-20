<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class Pagination
{
    public static function paginate(Request $request,$data,$name)
    {
        $page = 1;
        $skip = 0;
        $take = (int) $request->query('per_page', 10);

        if ($request->filled('page')) {
            $page = (int) $request->query('page');
            $skip = ($page - 1) * $take;
        }

        $total_records = $data->count();
        $total_pages = ceil($total_records / $take);
        $data = $data->skip($skip)->take($take)->get();

        $response = [
            'status'=> 200,
            'message' => 'All ' . ucwords(str_replace('_', ' ', $name)) . ' Fetched Successfully',
            'total_page' => $total_pages,
            'per_page' => $take,
            'total_records' => $total_records,
            'page' => $page,
            $name => $data
        ];
        return $response;
    }
}
