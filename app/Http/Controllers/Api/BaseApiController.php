<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\JsonApiResponses;
use App\Http\Controllers\Controller;

abstract class BaseApiController extends Controller
{
    use JsonApiResponses;
}