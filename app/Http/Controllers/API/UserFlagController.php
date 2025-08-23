<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Posttwo\FunnyJunk\FunnyJunk;

class UserFlagController extends Controller
{
    protected $fj;

    public function __construct()
    {
        $this->fj = new FunnyJunk();
        $this->fj->login(env("FJ_USERNAME"), env("FJ_PASSWORD"));
    }

    public function getReviewedUserFlags(Request $request)
    {
        $page = $request->input("page", 1);
        $flags = $this->fj->getReviewedUserFlags($page);

        return response()->json($flags);
    }

    public function getUnreviewedUserFlags(Request $request)
    {
        $page = $request->input("page", 1);
        $flags = $this->fj->getUnreviewedUserFlags($page);

        return response()->json($flags);
    }
}
