<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * @author Xanders
 *
 * @see https://team.xsamtech.com/xanderssamoth
 */
class BaseController extends Controller
{
    /**
     * Handle response
     *
     * @return JsonResponse
     */
    public function handleResponse($result, $msg, $lastPage = null, $count = null, $ad = null)
    {
        // Building the basic response
        $res = [
            'success' => true,
            'message' => $msg,
            'data' => $result,
            'ad' => $ad,
        ];

        // Add conditional keys only if they are defined
        if ($lastPage !== null) {
            $res['lastPage'] = $lastPage;
        }

        if ($count !== null) {
            $res['count'] = $count;
        }

        // Return JSON response
        return response()->json($res, 200);
    }

    /**
     * Handle response error
     *
     * @param  array  $errorMsg
     * @return JsonResponse
     */
    public function handleError($error, $errorMsg = [], $code = 404)
    {
        if (empty($errorMsg)) {
            $res = [
                'success' => false,
                'message' => $error,
            ];

            return response()->json($res, $code);
        }

        if (! empty($errorMsg)) {
            $res = [
                'success' => false,
                'data' => $error,
            ];

            $res['message'] = $errorMsg;

            return response()->json($res, $code);
        }
    }
}
