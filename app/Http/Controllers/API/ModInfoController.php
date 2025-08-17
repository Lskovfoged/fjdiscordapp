<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Posttwo\FunnyJunk\FunnyJunk;

class ModInfoController extends Controller
{
    public function __construct()
    {
        $this->fj = new FunnyJunk();
        $this->fj->login(env("FJ_USERNAME"), env("FJ_PASSWORD"));
    }

    public function getModInfo()
    {
        $modInfo = $this->fj->getModInfo();
        return response()->json($modInfo);
    }
}